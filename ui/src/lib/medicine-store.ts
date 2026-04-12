import { useCallback, useEffect, useState } from "react";
import { useAuth } from "@/contexts/auth-context";
import {
  apiConfirmAdherence,
  apiDeleteMedication,
  apiGetAdherenceHistory,
  apiGetMedications,
  apiGetSchedules,
  apiPatientAddMedicationFromScan,
  type ApiMedication,
  type ApiSchedule,
} from "@/lib/api";

export type MedicineScheduleSlot = {
  scheduleId: number;
  time: string;
  daysOfWeek: string;
  notes: string;
};

export interface Medicine {
  id: string;
  name: string;
  genericName: string;
  dosage: string;
  frequency: string;
  times: string[];
  /** One entry per DB schedule row (time + prescribed days + notes). */
  scheduleSlots: MedicineScheduleSlot[];
  purpose: string;
  purposeFil: string;
  precautions: string;
  precautionsFil: string;
  notes: string;
  /** Per-dose notes from schedule rows, keyed by HH:MM (first match wins if duplicate times). */
  scheduleNotesByTime: Record<string, string>;
  color: string;
  icon: string;
  /** Keys for today's date only (medId|time); other days use adherence API in schedule tab. */
  takenToday: string[];
  streak: number;
  addedAt: string;
}

const COLORS = [
  "hsl(168, 58%, 38%)",
  "hsl(30, 85%, 55%)",
  "hsl(280, 45%, 55%)",
  "hsl(200, 60%, 48%)",
  "hsl(340, 55%, 52%)",
  "hsl(152, 55%, 42%)",
];

const ICONS = ["💊", "💉", "🩹", "🧴", "🫁", "❤️"];

const TAKEN_KEY = "pharsayo-taken-v1";

export function formatYmd(d: Date): string {
  return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, "0")}-${String(d.getDate()).padStart(2, "0")}`;
}

function padTime(t: string): string {
  const s = (t || "08:00:00").slice(0, 8);
  const parts = s.split(":");
  const h = parts[0]?.padStart(2, "0") ?? "08";
  const m = parts[1]?.padStart(2, "0") ?? "00";
  return `${h}:${m}`;
}

export function timeKeyFromScheduledSql(sql: string): string {
  const m = String(sql || "").match(/(\d{1,2}):(\d{2})/);
  if (!m) return "08:00";
  return `${m[1].padStart(2, "0")}:${m[2]}`;
}

function getTakenKeys(userId: number, ymd: string): string[] {
  const raw = localStorage.getItem(`${TAKEN_KEY}:${userId}:${ymd}`);
  if (!raw) return [];
  try {
    return JSON.parse(raw) as string[];
  } catch {
    return [];
  }
}

function setTakenKeys(userId: number, ymd: string, keys: string[]) {
  localStorage.setItem(`${TAKEN_KEY}:${userId}:${ymd}`, JSON.stringify(keys));
}

function keyFor(medId: string, time: string) {
  return `${medId}|${time}`;
}

function mapApiToMedicine(
  m: ApiMedication,
  scheds: ApiSchedule[],
  _lang: "fil" | "en",
  idx: number,
  takenKeys: string[],
): Medicine {
  const mySched = scheds.filter((s) => s.medication_id === m.id);
  const scheduleSlots: MedicineScheduleSlot[] = mySched.map((s) => ({
    scheduleId: s.id,
    time: padTime(s.reminder_time),
    daysOfWeek: (s.days_of_week || "Mon,Tue,Wed,Thu,Fri,Sat,Sun").trim(),
    notes: (s.notes && String(s.notes).trim()) || "",
  }));
  const times = scheduleSlots.length ? [...new Set(scheduleSlots.map((s) => s.time))] : [];
  const purposeEn = m.purpose_en || "";
  const purposeFil = m.purpose_fil || "";
  const precEn = m.precautions_en || "";
  const precFil = m.precautions_fil || "";
  const idStr = String(m.id);
  const takenToday = takenKeys.filter((k) => k.startsWith(`${idStr}|`)).map((k) => k.split("|")[1]);
  const scheduleNotesByTime: Record<string, string> = {};
  scheduleSlots.forEach((s) => {
    if (s.notes && !scheduleNotesByTime[s.time]) {
      scheduleNotesByTime[s.time] = s.notes;
    }
  });
  const purposeFromApi = typeof m.purpose === "string" ? m.purpose : "";
  const precFromApi = typeof m.precautions === "string" ? m.precautions : "";

  const freqLocalized = (m.frequency && String(m.frequency).trim()) || "";

  return {
    id: idStr,
    name: m.name,
    genericName: m.name,
    dosage: m.dosage || "",
    frequency: freqLocalized,
    times,
    scheduleSlots,
    purpose: purposeFromApi || purposeEn,
    purposeFil: purposeFil.trim() ? purposeFil : purposeFromApi || purposeEn,
    precautions: precFromApi || precEn,
    precautionsFil: precFil.trim() ? precFil : precFromApi || precEn,
    notes: (m.notes && String(m.notes)) || "",
    scheduleNotesByTime,
    color: COLORS[idx % COLORS.length],
    icon: ICONS[idx % ICONS.length],
    takenToday,
    streak: 0,
    addedAt: new Date().toISOString(),
  };
}

export function useMedicineStore() {
  const { user, lang } = useAuth();
  const [medicines, setMedicines] = useState<Medicine[]>([]);
  const [refreshing, setRefreshing] = useState(false);

  const refresh = useCallback(async () => {
    if (!user?.id || user.role !== "patient") {
      setMedicines([]);
      return;
    }
    setRefreshing(true);
    try {
      const [meds, scheds] = await Promise.all([
        apiGetMedications(user.id, lang),
        apiGetSchedules(user.id),
      ]);
      const todayYmd = formatYmd(new Date());
      let takenKeys = getTakenKeys(user.id, todayYmd);
      try {
        const logs = await apiGetAdherenceHistory(user.id, todayYmd);
        const fromServer = new Set<string>();
        for (const log of logs) {
          const ok = log.taken === true || log.taken === 1 || log.taken === "1";
          if (!ok) continue;
          const tk = timeKeyFromScheduledSql(log.scheduled_time);
          fromServer.add(`${log.medication_id}|${tk}`);
        }
        const merged = new Set([...takenKeys, ...fromServer]);
        takenKeys = [...merged];
        setTakenKeys(user.id, todayYmd, takenKeys);
      } catch {
        /* keep local cache */
      }
      const list = meds.map((m, i) => mapApiToMedicine(m, scheds, lang, i, takenKeys));
      setMedicines(list);
    } catch (e) {
      console.error(e);
      setMedicines([]);
    } finally {
      setRefreshing(false);
    }
  }, [user?.id, user?.role, lang]);

  useEffect(() => {
    void refresh();
  }, [refresh]);

  const addMedicine = useCallback(
    async (med: Omit<Medicine, "id" | "color" | "icon" | "takenToday" | "streak" | "addedAt" | "scheduleSlots">) => {
      if (!user?.id || user.role !== "patient") return;
      const lines = med.times.map((t) => (med.scheduleNotesByTime[t] ?? "").trim());
      await apiPatientAddMedicationFromScan({
        user_id: user.id,
        name: med.name,
        dosage: med.dosage,
        frequency: med.frequency,
        frequency_fil: med.frequency,
        purpose_en: med.purpose,
        purpose_fil: med.purposeFil,
        precautions_en: med.precautions,
        precautions_fil: med.precautionsFil,
        notes: med.notes,
        times: med.times,
        days_of_week: "Mon,Tue,Wed,Thu,Fri,Sat,Sun",
        schedule_notes: lines,
      });
      await refresh();
    },
    [user?.id, user?.role, refresh],
  );

  const removeMedicine = useCallback(
    async (id: string) => {
      if (!user?.id || user.role !== "patient") return;
      await apiDeleteMedication(user.id, parseInt(id, 10));
      await refresh();
    },
    [user?.id, user?.role, refresh],
  );

  const markTaken = useCallback(
    async (id: string, time: string, dateYmd?: string) => {
      if (!user?.id || user.role !== "patient") return;
      const dStr = dateYmd ?? formatYmd(new Date());
      const scheduled_time = `${dStr} ${time}:00`;
      await apiConfirmAdherence({
        user_id: user.id,
        medication_id: parseInt(id, 10),
        scheduled_time,
        taken: true,
      });
      const k = keyFor(id, time);
      const next = [...new Set([...getTakenKeys(user.id, dStr), k])];
      setTakenKeys(user.id, dStr, next);
      const todayYmd = formatYmd(new Date());
      if (dStr === todayYmd) {
        setMedicines((prev) =>
          prev.map((m) =>
            m.id === id && !m.takenToday.includes(time) ? { ...m, takenToday: [...m.takenToday, time] } : m,
          ),
        );
      }
    },
    [user?.id, user?.role],
  );

  const markUntaken = useCallback(
    async (id: string, time: string, dateYmd?: string) => {
      if (!user?.id || user.role !== "patient") return;
      const dStr = dateYmd ?? formatYmd(new Date());
      const scheduled_time = `${dStr} ${time}:00`;
      await apiConfirmAdherence({
        user_id: user.id,
        medication_id: parseInt(id, 10),
        scheduled_time,
        taken: false,
      });
      const k = keyFor(id, time);
      const next = getTakenKeys(user.id, dStr).filter((x) => x !== k);
      setTakenKeys(user.id, dStr, next);
      const todayYmd = formatYmd(new Date());
      if (dStr === todayYmd) {
        setMedicines((prev) =>
          prev.map((m) => (m.id === id ? { ...m, takenToday: m.takenToday.filter((t) => t !== time) } : m)),
        );
      }
    },
    [user?.id, user?.role],
  );

  return { medicines, addMedicine, removeMedicine, markTaken, markUntaken, refresh, refreshing };
}

export { COLORS, ICONS };
