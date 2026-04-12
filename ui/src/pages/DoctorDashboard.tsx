import { useCallback, useEffect, useMemo, useState, type ReactNode } from "react";
import { Check, ChevronsUpDown, Clock, HelpCircle, RefreshCw, UserRound, XCircle } from "lucide-react";
import { useAuth } from "@/contexts/auth-context";
import {
  apiChangePassword,
  apiDoctorLinkPatient,
  apiDoctorPatients,
  apiDoctorPrescribe,
  apiDoctorSchedulesFlat,
  apiDoctorSearchLinkablePatients,
  apiScheduleDelete,
  apiScheduleUpdate,
  apiUpdateProfile,
  type ApiDoctorLinkablePatientRow,
  type ApiDoctorPatientRow,
  type ApiDoctorScheduleRow,
  type DoctorAdherenceToday,
  type Lang,
} from "@/lib/api";
import { t } from "@/lib/i18n";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Textarea } from "@/components/ui/textarea";
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select";
import {
  Command,
  CommandEmpty,
  CommandGroup,
  CommandInput,
  CommandItem,
  CommandList,
} from "@/components/ui/command";
import { Popover, PopoverContent, PopoverTrigger } from "@/components/ui/popover";
import {
  Dialog,
  DialogContent,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from "@/components/ui/dialog";
import { toast } from "sonner";
import { Badge } from "@/components/ui/badge";
import { cn } from "@/lib/utils";

function DoctorAdherenceBadge({
  lang,
  status,
  confirmedTime,
}: {
  lang: Lang;
  status: DoctorAdherenceToday;
  confirmedTime?: string | null;
}) {
  const iconCls = "w-3 h-3 shrink-0";

  let badge: ReactNode = null;
  switch (status) {
    case "not_scheduled_today":
      badge = (
        <Badge variant="outline" className="font-normal text-muted-foreground border-border gap-1 py-0.5">
          {t(lang, "doctor.adherenceNotScheduledToday")}
        </Badge>
      );
      break;
    case "pending":
      badge = (
        <Badge
          variant="secondary"
          className="font-medium gap-1 py-0.5 bg-amber-500/12 text-amber-950 dark:text-amber-50 border border-amber-500/25"
        >
          <Clock className={iconCls} />
          {t(lang, "doctor.adherencePending")}
        </Badge>
      );
      break;
    case "marked_not_taken":
      badge = (
        <Badge variant="destructive" className="font-medium gap-1 py-0.5">
          <XCircle className={iconCls} />
          {t(lang, "doctor.adherenceMarkedNotTaken")}
        </Badge>
      );
      break;
    case "taken_on_time":
      badge = (
        <Badge className="font-medium gap-1 py-0.5 bg-emerald-600 hover:bg-emerald-600/90 text-white border-0">
          <Check className={iconCls} />
          {t(lang, "doctor.adherenceTakenOnTime")}
        </Badge>
      );
      break;
    case "taken_late":
      badge = (
        <Badge
          variant="secondary"
          className="font-medium gap-1 py-0.5 bg-orange-500/15 text-orange-950 dark:text-orange-50 border border-orange-500/30"
        >
          <Clock className={iconCls} />
          {t(lang, "doctor.adherenceTakenLate")}
        </Badge>
      );
      break;
    case "taken_time_unknown":
      badge = (
        <Badge variant="secondary" className="font-medium gap-1 py-0.5 border-border">
          <HelpCircle className={iconCls} />
          {t(lang, "doctor.adherenceTakenUnknown")}
        </Badge>
      );
      break;
    default:
      badge = null;
  }

  if (!badge) return null;

  const showTimeLine =
    Boolean(confirmedTime?.trim()) &&
    (status === "taken_on_time" || status === "taken_late" || status === "taken_time_unknown");

  return (
    <div className="flex flex-col gap-1 items-start">
      {badge}
      {showTimeLine ? (
        <p className="text-[11px] text-muted-foreground font-mono tabular-nums leading-tight">
          {t(lang, "doctor.adherenceConfirmedAt").replace("{time}", confirmedTime!.trim())}
        </p>
      ) : null}
    </div>
  );
}

function DoctorLinkPatientPanel({
  doctorId,
  lang,
  onDone,
}: {
  doctorId: number;
  lang: Lang;
  onDone: () => void | Promise<void>;
}) {
  const [open, setOpen] = useState(false);
  const [query, setQuery] = useState("");
  const [hits, setHits] = useState<ApiDoctorLinkablePatientRow[]>([]);
  const [searchBusy, setSearchBusy] = useState(false);
  const [pick, setPick] = useState<ApiDoctorLinkablePatientRow | null>(null);
  const [linkBusy, setLinkBusy] = useState(false);

  useEffect(() => {
    const q = query.trim();
    if (q.length < 2) {
      setHits([]);
      return;
    }
    const timer = window.setTimeout(() => {
      void (async () => {
        setSearchBusy(true);
        try {
          const list = await apiDoctorSearchLinkablePatients(doctorId, q);
          setHits(list);
        } catch {
          setHits([]);
        } finally {
          setSearchBusy(false);
        }
      })();
    }, 300);
    return () => window.clearTimeout(timer);
  }, [query, doctorId]);

  const summary =
    pick != null
      ? `${pick.name} (@${(pick.username || "").trim() || "—"}) · ${pick.phone}`
      : t(lang, "doctor.linkPatientPickIdle");

  const runLink = async () => {
    if (!pick) {
      toast.message(t(lang, "doctor.linkPatientPickIdle"));
      return;
    }
    setLinkBusy(true);
    try {
      await apiDoctorLinkPatient(doctorId, {
        patient_username: (pick.username || "").trim(),
        patient_phone: (pick.username || "").trim() ? "" : (pick.phone || "").trim(),
      });
      toast.success(lang === "en" ? "Patient linked." : "Na-link ang pasyente.");
      setPick(null);
      setQuery("");
      setHits([]);
      setOpen(false);
      await onDone();
    } catch (e) {
      toast.error(e instanceof Error ? e.message : "Link failed");
    } finally {
      setLinkBusy(false);
    }
  };

  return (
    <div className="flex flex-col sm:flex-row gap-2">
      <Popover open={open} onOpenChange={setOpen}>
        <PopoverTrigger asChild>
          <Button
            type="button"
            variant="outline"
            role="combobox"
            aria-expanded={open}
            className="flex-1 justify-between rounded-xl h-11 font-normal min-w-0"
          >
            <span className="truncate text-left text-sm">{summary}</span>
            <ChevronsUpDown className="ml-2 h-4 w-4 shrink-0 opacity-50" />
          </Button>
        </PopoverTrigger>
        <PopoverContent className="w-[var(--radix-popover-trigger-width)] min-w-[min(100%,22rem)] p-0" align="start">
          <Command shouldFilter={false}>
            <CommandInput placeholder={t(lang, "doctor.linkPatientSearch")} value={query} onValueChange={setQuery} />
            <CommandList>
              {query.trim().length < 2 && (
                <div className="py-6 text-center text-xs text-muted-foreground px-3">
                  {t(lang, "doctor.linkPatientMinChars")}
                </div>
              )}
              {query.trim().length >= 2 && searchBusy && (
                <div className="py-4 text-center text-xs text-muted-foreground">…</div>
              )}
              {query.trim().length >= 2 && !searchBusy && hits.length === 0 && (
                <CommandEmpty>{t(lang, "admin.noUserMatch")}</CommandEmpty>
              )}
              <CommandGroup>
                {hits.map((p) => (
                  <CommandItem
                    key={p.id}
                    value={`${p.id} ${p.username ?? ""} ${p.name} ${p.phone}`}
                    onSelect={() => {
                      setPick(p);
                      setOpen(false);
                    }}
                  >
                    <span className="font-mono text-xs">@{p.username || "—"}</span>
                    <span className="text-muted-foreground truncate"> — {p.name}</span>
                    <span className="ml-auto shrink-0 pl-2 text-xs text-muted-foreground">{p.phone}</span>
                  </CommandItem>
                ))}
              </CommandGroup>
            </CommandList>
          </Command>
        </PopoverContent>
      </Popover>
      <Button type="button" className="rounded-xl shrink-0 h-11" disabled={linkBusy} onClick={() => void runLink()}>
        {t(lang, "doctor.linkAction")}
      </Button>
    </div>
  );
}

const DEFAULT_DAYS = "Mon,Tue,Wed,Thu,Fri,Sat,Sun";

function parseTimes(raw: string): string[] {
  return raw
    .split(/[,;\s]+/)
    .map((s) => s.trim())
    .filter(Boolean);
}

/** HH:MM for API / edit dialog (24-hour). */
function formatScheduleTime(raw: string): string {
  const s = (raw || "").trim().slice(0, 8);
  const m = s.match(/^(\d{1,2}):(\d{2})/);
  if (!m) return (raw || "").slice(0, 5);
  return `${m[1].padStart(2, "0")}:${m[2].padStart(2, "0")}`;
}

/** 12-hour clock for list display only. */
function formatScheduleTime12h(raw: string): string {
  const s = (raw || "").trim().slice(0, 8);
  const m = s.match(/^(\d{1,2}):(\d{2})/);
  if (!m) return (raw || "").slice(0, 5);
  let h = parseInt(m[1], 10);
  const min = m[2].padStart(2, "0");
  const ap = h >= 12 ? "PM" : "AM";
  h = h % 12;
  if (h === 0) h = 12;
  return `${h}:${min} ${ap}`;
}

function patientScheduleGroupKey(r: ApiDoctorScheduleRow): string {
  const u = (r.patient_username || "").trim().toLowerCase();
  if (u) return `u:${u}`;
  const n = (r.patient_name || "").trim().toLowerCase();
  return `n:${n}`;
}

function patientScheduleGroupSortLabel(r: ApiDoctorScheduleRow): string {
  return `${(r.patient_name || "").toLowerCase()}\0${(r.patient_username || "").toLowerCase()}`;
}

function scheduleNotesFromLines(times: string[], lines: string[]): string[] {
  return times.map((_, i) => (lines[i] ?? "").trim());
}

export default function DoctorDashboard() {
  const { user, logout, lang, setLang, applyUser } = useAuth();
  const [patients, setPatients] = useState<ApiDoctorPatientRow[]>([]);
  const [flatSched, setFlatSched] = useState<ApiDoctorScheduleRow[]>([]);
  const [loading, setLoading] = useState(true);
  const [schedLoading, setSchedLoading] = useState(false);
  const [schedSearch, setSchedSearch] = useState("");
  const [busyScheduleId, setBusyScheduleId] = useState<number | null>(null);

  const [patientId, setPatientId] = useState<string>("");
  const [rxPatOpen, setRxPatOpen] = useState(false);
  const [medName, setMedName] = useState("");
  const [dosage, setDosage] = useState("");
  const [daysOfWeek, setDaysOfWeek] = useState(DEFAULT_DAYS);
  const [timesStr, setTimesStr] = useState("08:00, 20:00");
  const [purposeEn, setPurposeEn] = useState("");
  const [precEn, setPrecEn] = useState("");
  const [notes, setNotes] = useState("");
  const [rxBusy, setRxBusy] = useState(false);

  const [editRow, setEditRow] = useState<ApiDoctorScheduleRow | null>(null);
  const [editTime, setEditTime] = useState("");
  const [editDays, setEditDays] = useState("");
  const [editNotes, setEditNotes] = useState("");
  const [editBusy, setEditBusy] = useState(false);

  const [profileOpen, setProfileOpen] = useState(false);
  const [profName, setProfName] = useState("");
  const [profUsername, setProfUsername] = useState("");
  const [profPhone, setProfPhone] = useState("");
  const [profAge, setProfAge] = useState("");
  const [profBusy, setProfBusy] = useState(false);
  const [curPw, setCurPw] = useState("");
  const [newPw, setNewPw] = useState("");
  const [pwBusy, setPwBusy] = useState(false);

  useEffect(() => {
    if (!profileOpen || !user) return;
    setProfName(user.name ?? "");
    setProfUsername(user.username ?? "");
    setProfPhone(user.phone ?? "");
    setProfAge("");
    setCurPw("");
    setNewPw("");
  }, [profileOpen, user]);

  const loadPatients = useCallback(async () => {
    if (!user?.id) return;
    setLoading(true);
    try {
      const list = await apiDoctorPatients(user.id);
      setPatients(list);
      setPatientId((prev) => {
        if (list.length === 0) return "";
        if (prev && list.some((p) => String(p.id) === prev)) return prev;
        return String(list[0].id);
      });
    } catch (e) {
      toast.error(e instanceof Error ? e.message : "Failed to load patients");
      setPatients([]);
    } finally {
      setLoading(false);
    }
  }, [user?.id]);

  const loadSchedules = useCallback(async () => {
    if (!user?.id) return;
    setSchedLoading(true);
    try {
      const rows = await apiDoctorSchedulesFlat(user.id);
      setFlatSched(rows);
    } catch (e) {
      toast.error(e instanceof Error ? e.message : "Failed to load schedules");
      setFlatSched([]);
    } finally {
      setSchedLoading(false);
    }
  }, [user?.id]);

  const filteredSchedules = useMemo(() => {
    const q = schedSearch.trim().toLowerCase();
    if (!q) return flatSched;
    return flatSched.filter((r) => {
      const hay = [r.patient_name, r.patient_username, r.medicine_name].filter(Boolean).join(" ").toLowerCase();
      return hay.includes(q);
    });
  }, [flatSched, schedSearch]);

  const scheduleGroups = useMemo(() => {
    const map = new Map<string, ApiDoctorScheduleRow[]>();
    for (const r of filteredSchedules) {
      const k = patientScheduleGroupKey(r);
      const prev = map.get(k);
      if (prev) prev.push(r);
      else map.set(k, [r]);
    }
    for (const arr of map.values()) {
      arr.sort((a, b) => (a.reminder_time || "").localeCompare(b.reminder_time || ""));
    }
    return [...map.entries()].sort(([, rowsA], [, rowsB]) =>
      patientScheduleGroupSortLabel(rowsA[0]).localeCompare(patientScheduleGroupSortLabel(rowsB[0])),
    );
  }, [filteredSchedules]);

  useEffect(() => {
    void loadPatients();
  }, [loadPatients]);

  useEffect(() => {
    void loadSchedules();
  }, [loadSchedules]);

  const onPrescribe = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!user?.id) return;
    const pid = parseInt(patientId, 10);
    if (!pid || !medName.trim()) {
      toast.message(lang === "en" ? "Choose a patient and enter a medicine name." : "Pumili ng pasyente at ilagay ang gamot.");
      return;
    }
    const times = parseTimes(timesStr);
    if (!times.length) {
      toast.message(lang === "en" ? "Add at least one time (e.g. 08:00)." : "Maglagay ng oras (hal. 08:00).");
      return;
    }
    const days = daysOfWeek.trim() || DEFAULT_DAYS;
    setRxBusy(true);
    try {
      await apiDoctorPrescribe({
        doctor_id: user.id,
        patient_user_id: pid,
        name: medName.trim(),
        dosage: dosage.trim(),
        days_of_week: days,
        frequency: days,
        frequency_fil: days,
        purpose_en: purposeEn.trim(),
        purpose_fil: purposeEn.trim(),
        precautions_en: precEn.trim(),
        precautions_fil: precEn.trim(),
        medication_notes: notes.trim(),
        schedule_notes: times.map(() => ""), // Empty notes
        times,
      });
      toast.success(lang === "en" ? "Prescription saved." : "Na-save ang reseta.");
      setMedName("");
      setDosage("");
      setDaysOfWeek(DEFAULT_DAYS);
      setTimesStr("08:00, 20:00");
      setPurposeEn("");
      setPrecEn("");
      setNotes("");
      await loadSchedules();
    } catch (err) {
      toast.error(err instanceof Error ? err.message : "Save failed");
    } finally {
      setRxBusy(false);
    }
  };

  const onSaveProfile = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!user?.id) return;
    setProfBusy(true);
    try {
      const payload: {
        user_id: number;
        name: string;
        username: string;
        phone: string;
        language_preference: Lang;
        age?: number | null;
      } = {
        user_id: user.id,
        name: profName.trim(),
        username: profUsername.trim(),
        phone: profPhone.trim(),
        language_preference: lang,
      };
      if (profAge.trim() !== "") {
        const a = parseInt(profAge, 10);
        if (!Number.isNaN(a)) payload.age = a;
      }
      const { user: u } = await apiUpdateProfile(payload);
      applyUser(u);
      toast.success(lang === "en" ? "Profile saved." : "Na-save ang profile.");
      setProfileOpen(false);
    } catch (err) {
      toast.error(err instanceof Error ? err.message : "Update failed");
    } finally {
      setProfBusy(false);
    }
  };

  const onChangePassword = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!user?.id) return;
    setPwBusy(true);
    try {
      await apiChangePassword({
        user_id: user.id,
        current_password: curPw,
        new_password: newPw,
      });
      setCurPw("");
      setNewPw("");
      toast.success(lang === "en" ? "Password updated." : "Na-update ang password.");
    } catch (err) {
      toast.error(err instanceof Error ? err.message : "Could not change password");
    } finally {
      setPwBusy(false);
    }
  };

  const openEdit = (r: ApiDoctorScheduleRow) => {
    setEditRow(r);
    setEditTime(formatScheduleTime(r.reminder_time));
    setEditDays(r.days_of_week || "");
    setEditNotes(r.schedule_notes ?? "");
  };

  const saveEdit = async () => {
    if (!user?.id || !editRow) return;
    setEditBusy(true);
    try {
      await apiScheduleUpdate({
        actor_id: user.id,
        actor_role: "doctor",
        schedule_id: editRow.schedule_id,
        reminder_time: editTime.trim(),
        days_of_week: editDays.trim(),
        notes: editNotes,
      });
      toast.success(lang === "en" ? "Saved." : "Na-save.");
      setEditRow(null);
      await loadSchedules();
    } catch (e) {
      toast.error(e instanceof Error ? e.message : "Save failed");
    } finally {
      setEditBusy(false);
    }
  };

  const deleteSchedule = async (id: number) => {
    if (!user?.id) return;
    setBusyScheduleId(id);
    try {
      await apiScheduleDelete({ actor_id: user.id, actor_role: "doctor", schedule_id: id });
      toast.success(lang === "en" ? "Deleted." : "Nabura.");
      await loadSchedules();
    } catch (e) {
      toast.error(e instanceof Error ? e.message : "Delete failed");
    } finally {
      setBusyScheduleId(null);
    }
  };

  const patientLabel = (p: ApiDoctorPatientRow) =>
    `${p.name}${p.username ? ` (@${p.username})` : ""} · ${p.phone}`;

  return (
    <div className="min-h-screen bg-background px-4 py-8 pb-24">
      <div className="max-w-lg mx-auto space-y-8">
        <div className="flex items-center justify-between gap-3">
          <div className="flex items-center gap-3 min-w-0">
            <div className="w-12 h-12 rounded-2xl bg-primary/15 flex items-center justify-center text-primary shrink-0">
              <UserRound className="w-7 h-7" />
            </div>
            <div className="min-w-0">
              <h1 className="text-xl font-bold text-foreground truncate">{t(lang, "doctor.title")}</h1>
              <p className="text-sm text-muted-foreground truncate">
                {user?.name && !user.name.toLowerCase().startsWith("dr.") && !user.name.toLowerCase().startsWith("dr ")
                  ? `Dr. ${user.name}`
                  : user?.name}
              </p>
            </div>
          </div>
          <div className="flex items-center gap-2 shrink-0">
            <Button variant="outline" size="sm" className="rounded-xl" onClick={() => setProfileOpen(true)}>
              {t(lang, "profile.editTitle")}
            </Button>
            <Button variant="outline" size="sm" className="rounded-xl" onClick={() => logout()}>
              Sign out
            </Button>
          </div>
        </div>

        <section className="rounded-2xl border border-border bg-card p-5 space-y-3">
          <h2 className="font-semibold text-foreground">{t(lang, "doctor.linkPatient")}</h2>
          <p className="text-xs text-muted-foreground leading-relaxed">{t(lang, "doctor.linkHint")}</p>
          {user?.id ? (
            <DoctorLinkPatientPanel
              doctorId={user.id}
              lang={lang}
              onDone={async () => {
                await loadPatients();
                await loadSchedules();
              }}
            />
          ) : null}
        </section>

        <section className="rounded-2xl border border-border bg-card p-5 space-y-4">
          <h2 className="font-semibold text-foreground">{t(lang, "doctor.prescribeTitle")}</h2>
          <p className="text-xs text-muted-foreground leading-relaxed">{t(lang, "doctor.prescribeHint")}</p>

          {loading ? (
            <p className="text-sm text-muted-foreground">Loading patients…</p>
          ) : patients.length === 0 ? (
            <p className="text-sm text-muted-foreground">
              {lang === "en" ? "No linked patients. Link one above." : "Walang naka-link. Mag-link muna sa itaas."}
            </p>
          ) : (
            <form className="space-y-3" onSubmit={onPrescribe}>
              <div className="space-y-2">
                <Label>{t(lang, "doctor.patient")}</Label>
                <Popover open={rxPatOpen} onOpenChange={setRxPatOpen}>
                  <PopoverTrigger asChild>
                    <Button
                      type="button"
                      variant="outline"
                      role="combobox"
                      aria-expanded={rxPatOpen}
                      className="w-full justify-between rounded-xl h-11 font-normal"
                    >
                      <span className="truncate text-left text-sm">
                        {(() => {
                          const p = patients.find((x) => String(x.id) === patientId);
                          return p ? patientLabel(p) : "—";
                        })()}
                      </span>
                      <ChevronsUpDown className="ml-2 h-4 w-4 shrink-0 opacity-50" />
                    </Button>
                  </PopoverTrigger>
                  <PopoverContent className="w-[var(--radix-popover-trigger-width)] min-w-[min(100%,22rem)] p-0" align="start">
                    <Command>
                      <CommandInput placeholder={t(lang, "doctor.linkPatientSearch")} />
                      <CommandList>
                        <CommandEmpty>{t(lang, "admin.noUserMatch")}</CommandEmpty>
                        <CommandGroup>
                          {patients.map((p) => (
                            <CommandItem
                              key={p.id}
                              value={`${p.name} ${p.username ?? ""} ${p.phone} ${p.id}`}
                              onSelect={() => {
                                setPatientId(String(p.id));
                                setRxPatOpen(false);
                              }}
                            >
                              <span className="font-mono text-xs">@{p.username || "—"}</span>
                              <span className="text-muted-foreground truncate"> — {p.name}</span>
                              <span className="ml-auto shrink-0 pl-2 text-xs text-muted-foreground">{p.phone}</span>
                            </CommandItem>
                          ))}
                        </CommandGroup>
                      </CommandList>
                    </Command>
                  </PopoverContent>
                </Popover>
              </div>
              <div className="space-y-2">
                <Label>{t(lang, "doctor.medName")}</Label>
                <Input className="rounded-xl h-11" value={medName} onChange={(e) => setMedName(e.target.value)} required />
              </div>
              <div className="space-y-2">
                <Label>{t(lang, "doctor.dosage")}</Label>
                <Input className="rounded-xl h-11" value={dosage} onChange={(e) => setDosage(e.target.value)} />
              </div>
              <div className="space-y-2">
                <Label>{t(lang, "doctor.howOftenDays")}</Label>
                <Input
                  className="rounded-xl h-11 font-mono text-sm"
                  value={daysOfWeek}
                  onChange={(e) => setDaysOfWeek(e.target.value)}
                  placeholder={DEFAULT_DAYS}
                />
                <p className="text-[11px] text-muted-foreground leading-relaxed">{t(lang, "doctor.howOftenDaysHint")}</p>
              </div>
              <div className="space-y-2">
                <Label>{t(lang, "doctor.times")}</Label>
                <Input className="rounded-xl h-11 font-mono" value={timesStr} onChange={(e) => setTimesStr(e.target.value)} />
              </div>
              <div className="space-y-2">
                <Label>{t(lang, "doctor.purpose")}</Label>
                <Textarea className="rounded-xl min-h-[72px] text-sm" value={purposeEn} onChange={(e) => setPurposeEn(e.target.value)} />
              </div>
              <div className="space-y-2">
                <Label>{t(lang, "doctor.warnings")}</Label>
                <Textarea className="rounded-xl min-h-[72px] text-sm" value={precEn} onChange={(e) => setPrecEn(e.target.value)} />
              </div>
              <div className="space-y-2">
                <Label>{t(lang, "doctor.medNotesExtra")}</Label>
                <Textarea className="rounded-xl min-h-[80px]" value={notes} onChange={(e) => setNotes(e.target.value)} />
              </div>
              <Button type="submit" className="w-full h-12 rounded-xl font-semibold" disabled={rxBusy}>
                {rxBusy ? "…" : t(lang, "doctor.saveRx")}
              </Button>
            </form>
          )}
        </section>

        <section className="rounded-2xl border border-border bg-card p-5 space-y-3">
          <div className="flex items-start justify-between gap-2">
            <div className="min-w-0 space-y-1">
              <h2 className="font-semibold text-foreground">{t(lang, "doctor.schedulesTitle")}</h2>
              <p className="text-[11px] text-muted-foreground leading-relaxed">{t(lang, "doctor.schedulesTodayHint")}</p>
            </div>
            <Button
              type="button"
              variant="ghost"
              size="sm"
              className="rounded-lg h-8 gap-1.5 shrink-0 text-xs"
              disabled={schedLoading}
              onClick={() => void loadSchedules()}
            >
              <RefreshCw className={cn("w-3.5 h-3.5", schedLoading && "animate-spin")} />
              {t(lang, "doctor.refreshSchedules")}
            </Button>
          </div>
          {schedLoading ? (
            <p className="text-sm text-muted-foreground">Loading…</p>
          ) : flatSched.length === 0 ? (
            <p className="text-sm text-muted-foreground">{t(lang, "doctor.scheduleEmpty")}</p>
          ) : (
            <div className="space-y-3">
              <Input
                type="search"
                className="rounded-xl h-10 text-sm"
                value={schedSearch}
                onChange={(e) => setSchedSearch(e.target.value)}
                placeholder={t(lang, "doctor.schedulesSearchPlaceholder")}
                aria-label={t(lang, "doctor.schedulesSearchPlaceholder")}
              />
              {filteredSchedules.length === 0 ? (
                <p className="text-sm text-muted-foreground">{t(lang, "doctor.schedulesNoMatch")}</p>
              ) : (
                <div className="space-y-4 max-h-[55vh] overflow-y-auto pr-0.5">
                  {scheduleGroups.map(([groupKey, rows]) => {
                    const head = rows[0];
                    return (
                      <div
                        key={groupKey}
                        className="rounded-xl border border-border bg-muted/20 overflow-hidden"
                      >
                        <div className="px-3 py-2 border-b border-border/80 bg-muted/40">
                          <p className="font-semibold text-sm text-foreground truncate">{head.patient_name}</p>
                          {head.patient_username ? (
                            <p className="text-xs text-muted-foreground truncate font-mono">@{head.patient_username}</p>
                          ) : null}
                        </div>
                        <div className="p-2 space-y-2">
                          {rows.map((r) => (
                            <div
                              key={r.schedule_id}
                              className="rounded-lg border border-border bg-card p-3 text-sm flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:justify-between sm:items-start"
                            >
                              <div className="min-w-0 flex-1 space-y-2">
                                <div>
                                  <p className="text-foreground font-medium">
                                    {r.medicine_name} · {formatScheduleTime12h(r.reminder_time)}
                                  </p>
                                  {r.schedule_notes ? (
                                    <p className="text-xs text-muted-foreground mt-1">{r.schedule_notes}</p>
                                  ) : null}
                                </div>
                                <DoctorAdherenceBadge
                                  lang={lang}
                                  status={r.adherence_today}
                                  confirmedTime={r.adherence_confirmed_time}
                                />
                              </div>
                              <div className="flex gap-2 shrink-0">
                                <Button size="sm" variant="outline" className="rounded-lg h-8" onClick={() => openEdit(r)}>
                                  {t(lang, "doctor.editSchedule")}
                                </Button>
                                <Button
                                  size="sm"
                                  variant="destructive"
                                  className="rounded-lg h-8"
                                  disabled={busyScheduleId === r.schedule_id}
                                  onClick={() => void deleteSchedule(r.schedule_id)}
                                >
                                  {t(lang, "admin.delete")}
                                </Button>
                              </div>
                            </div>
                          ))}
                        </div>
                      </div>
                    );
                  })}
                </div>
              )}
            </div>
          )}
        </section>
      </div>

      <Dialog open={profileOpen} onOpenChange={setProfileOpen}>
        <DialogContent className="sm:max-w-md rounded-2xl max-h-[90vh] overflow-y-auto">
          <DialogHeader>
            <DialogTitle>{t(lang, "profile.editTitle")}</DialogTitle>
          </DialogHeader>
          <form onSubmit={onSaveProfile} className="space-y-3 py-2">
            <div className="space-y-2">
              <Label htmlFor="doc-prof-name">{t(lang, "profile.fullName")}</Label>
              <Input
                id="doc-prof-name"
                value={profName}
                onChange={(e) => setProfName(e.target.value)}
                required
                className="rounded-xl h-11"
              />
            </div>
            <div className="space-y-2">
              <Label htmlFor="doc-prof-user">{t(lang, "profile.username")}</Label>
              <Input
                id="doc-prof-user"
                value={profUsername}
                onChange={(e) => setProfUsername(e.target.value)}
                required
                minLength={3}
                maxLength={32}
                className="rounded-xl h-11 font-mono"
              />
            </div>
            <div className="space-y-2">
              <Label htmlFor="doc-prof-phone">{t(lang, "profile.phone")}</Label>
              <Input
                id="doc-prof-phone"
                value={profPhone}
                onChange={(e) => setProfPhone(e.target.value)}
                required
                className="rounded-xl h-11"
              />
            </div>
            <div className="space-y-2">
              <Label htmlFor="doc-prof-age">
                {t(lang, "profile.age")} ({lang === "en" ? "optional" : "opsyonal"})
              </Label>
              <Input
                id="doc-prof-age"
                type="number"
                min={1}
                max={120}
                value={profAge}
                onChange={(e) => setProfAge(e.target.value)}
                className="rounded-xl h-11"
              />
            </div>
            <div className="space-y-2">
              <Label>{t(lang, "profile.language")}</Label>
              <Select value={lang} onValueChange={(v) => void setLang(v as Lang)}>
                <SelectTrigger className="rounded-xl h-11">
                  <SelectValue />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="fil">Filipino</SelectItem>
                  <SelectItem value="en">English</SelectItem>
                </SelectContent>
              </Select>
            </div>
            <DialogFooter className="gap-2 pt-2">
              <Button type="button" variant="outline" className="rounded-xl" onClick={() => setProfileOpen(false)}>
                {t(lang, "admin.cancel")}
              </Button>
              <Button type="submit" className="rounded-xl" disabled={profBusy}>
                {t(lang, "profile.save")}
              </Button>
            </DialogFooter>
          </form>
          <form onSubmit={onChangePassword} className="space-y-3 border-t border-border pt-4 mt-2">
            <h3 className="font-semibold text-foreground text-sm">{t(lang, "profile.passwordTitle")}</h3>
            <div className="space-y-2">
              <Label htmlFor="doc-cur-pw">{t(lang, "profile.currentPassword")}</Label>
              <Input
                id="doc-cur-pw"
                type="password"
                autoComplete="current-password"
                value={curPw}
                onChange={(e) => setCurPw(e.target.value)}
                required
                className="rounded-xl h-11"
              />
            </div>
            <div className="space-y-2">
              <Label htmlFor="doc-new-pw">{t(lang, "profile.newPassword")}</Label>
              <Input
                id="doc-new-pw"
                type="password"
                autoComplete="new-password"
                value={newPw}
                onChange={(e) => setNewPw(e.target.value)}
                required
                minLength={6}
                className="rounded-xl h-11"
              />
            </div>
            <Button type="submit" variant="secondary" className="w-full rounded-xl h-11" disabled={pwBusy}>
              {t(lang, "profile.changePassword")}
            </Button>
          </form>
        </DialogContent>
      </Dialog>

      <Dialog open={!!editRow} onOpenChange={(o) => !o && setEditRow(null)}>
        <DialogContent className="sm:max-w-md rounded-2xl">
          <DialogHeader>
            <DialogTitle>{t(lang, "doctor.editSchedule")}</DialogTitle>
          </DialogHeader>
          <div className="space-y-3 py-2">
            <div className="space-y-2">
              <Label>{t(lang, "admin.time")}</Label>
              <Input className="rounded-xl font-mono" value={editTime} onChange={(e) => setEditTime(e.target.value)} />
            </div>
            <div className="space-y-2">
              <Label>{t(lang, "admin.days")}</Label>
              <Input className="rounded-xl font-mono text-sm" value={editDays} onChange={(e) => setEditDays(e.target.value)} />
            </div>
            <div className="space-y-2">
              <Label>{t(lang, "admin.notes")}</Label>
              <Textarea className="rounded-xl min-h-[80px]" value={editNotes} onChange={(e) => setEditNotes(e.target.value)} />
            </div>
          </div>
          <DialogFooter className="gap-2">
            <Button variant="outline" className="rounded-xl" onClick={() => setEditRow(null)}>
              {t(lang, "admin.cancel")}
            </Button>
            <Button className="rounded-xl" disabled={editBusy} onClick={() => void saveEdit()}>
              {t(lang, "admin.save")}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </div>
  );
}
