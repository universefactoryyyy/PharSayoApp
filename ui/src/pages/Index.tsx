import { useState, useMemo, useEffect } from "react";
import { motion, AnimatePresence } from "framer-motion";
import { Pill, CheckCircle2, ClipboardList } from "lucide-react";
import { useAuth } from "@/contexts/auth-context";
import { t } from "@/lib/i18n";
import {
  useMedicineStore,
  formatYmd,
  timeKeyFromScheduledSql,
  type Medicine,
  type MedicineScheduleSlot,
} from "@/lib/medicine-store";
import { apiGetAdherenceHistory } from "@/lib/api";
import { formatScheduleTime12h } from "@/lib/utils";
import BottomNav from "@/components/BottomNav";
import ScheduleCard from "@/components/ScheduleCard";
import MedicineList from "@/components/MedicineList";
import MedicineScanner from "@/components/MedicineScanner";
import ProgressRing from "@/components/ProgressRing";
import ProfileTab from "@/components/ProfileTab";

function getCurrentTime() {
  const now = new Date();
  return `${String(now.getHours()).padStart(2, "0")}:${String(now.getMinutes()).padStart(2, "0")}`;
}

function weekdayKeyFromDate(d: Date): string {
  return d.toLocaleDateString("en-US", { weekday: "short" });
}

function dayCsvIncludesDay(daysCsv: string, d: Date): boolean {
  const key = weekdayKeyFromDate(d);
  return daysCsv.split(",").some((part) => part.trim().toLowerCase() === key.toLowerCase());
}

function isDateInRange(d: Date, start?: string | null, end?: string | null): boolean {
  const target = new Date(d);
  target.setHours(0, 0, 0, 0);

  if (start) {
    const s = new Date(start);
    s.setHours(0, 0, 0, 0);
    if (target < s) return false;
  }
  if (end) {
    const e = new Date(end);
    e.setHours(0, 0, 0, 0);
    if (target > e) return false;
  }
  return true;
}

function weekDaysMonStart(ref: Date): Date[] {
  const d = new Date(ref);
  d.setHours(0, 0, 0, 0);
  const day = d.getDay();
  const diff = day === 0 ? -6 : 1 - day;
  d.setDate(d.getDate() + diff);
  return Array.from({ length: 7 }, (_, i) => {
    const x = new Date(d);
    x.setDate(d.getDate() + i);
    return x;
  });
}

function sameCalendarDate(a: Date, b: Date): boolean {
  return a.getFullYear() === b.getFullYear() && a.getMonth() === b.getMonth() && a.getDate() === b.getDate();
}

type SlotRow = { medicine: Medicine; slot: MedicineScheduleSlot };

export default function Index() {
  const { user, lang } = useAuth();
  const isPatient = user?.role === "patient";
  const [activeTab, setActiveTab] = useState("home");
  const { medicines, addMedicine, removeMedicine, markTaken, markUntaken } = useMedicineStore();

  const [weekTakenMap, setWeekTakenMap] = useState<Record<string, string[]>>({});

  const today = new Date();
  const locale = lang === "en" ? "en-US" : "fil-PH";
  const dateStr = today.toLocaleDateString(locale, {
    weekday: "long",
    month: "long",
    day: "numeric",
  });

  const todaysSlots = useMemo(() => {
    const todayD = new Date();
    const out: SlotRow[] = [];
    medicines.forEach((med) => {
      med.scheduleSlots.forEach((slot) => {
        if (dayCsvIncludesDay(slot.daysOfWeek, todayD) && isDateInRange(todayD, slot.startDate, slot.endDate)) {
          out.push({ medicine: med, slot });
        }
      });
    });
    return out.sort((a, b) => a.slot.time.localeCompare(b.slot.time));
  }, [medicines]);

  const hasPrescribedSlots = useMemo(() => medicines.some((m) => m.scheduleSlots.length > 0), [medicines]);

  const weekDays = useMemo(() => weekDaysMonStart(new Date()), []);

  const weekByDay = useMemo(() => {
    return weekDays.map((dayDate) => {
      const ymd = formatYmd(dayDate);
      const slots: SlotRow[] = [];
      medicines.forEach((med) => {
        med.scheduleSlots.forEach((slot) => {
          if (dayCsvIncludesDay(slot.daysOfWeek, dayDate) && isDateInRange(dayDate, slot.startDate, slot.endDate)) {
            slots.push({ medicine: med, slot });
          }
        });
      });
      slots.sort((a, b) => a.slot.time.localeCompare(b.slot.time));
      return { dayDate, ymd, slots };
    });
  }, [medicines, weekDays]);

  useEffect(() => {
    if (!isPatient || !user?.id || activeTab !== "schedule") return;
    let cancelled = false;
    (async () => {
      const days = weekDaysMonStart(new Date());
      const entries = await Promise.all(days.map((d) => apiGetAdherenceHistory(user.id, formatYmd(d))));
      if (cancelled) return;
      const next: Record<string, string[]> = {};
      days.forEach((d, i) => {
        const ymd = formatYmd(d);
        const keys: string[] = [];
        for (const log of entries[i]) {
          const ok = log.taken === true || log.taken === 1 || log.taken === "1";
          if (!ok) continue;
          keys.push(`${log.medication_id}|${timeKeyFromScheduledSql(log.scheduled_time)}`);
        }
        next[ymd] = keys;
      });
      setWeekTakenMap(next);
    })();
    return () => {
      cancelled = true;
    };
  }, [isPatient, user?.id, activeTab, medicines.length]);

  useEffect(() => {
    const ymd = formatYmd(new Date());
    const fromMeds = new Set(medicines.flatMap((m) => m.takenToday.map((ti) => `${m.id}|${ti}`)));
    setWeekTakenMap((w) => {
      const prev = new Set(w[ymd] ?? []);
      fromMeds.forEach((k) => prev.add(k));
      return { ...w, [ymd]: [...prev] };
    });
  }, [medicines]);

  const isSlotTakenForDate = (ymd: string, medId: string, time: string) => {
    const k = `${medId}|${time}`;
    const todayYmd = formatYmd(new Date());
    if (ymd === todayYmd) {
      const med = medicines.find((m) => m.id === medId);
      if (med?.takenToday.includes(time)) return true;
    }
    return (weekTakenMap[ymd] ?? []).includes(k);
  };

  const currentTime = getCurrentTime();
  const totalDoses = todaysSlots.length;
  const takenDoses = todaysSlots.filter((s) => s.medicine.takenToday.includes(s.slot.time)).length;

  return (
    <div className="min-h-screen bg-background pb-20">
      <AnimatePresence mode="wait">
        {activeTab === "home" && (
          <motion.div key="home" initial={{ opacity: 0 }} animate={{ opacity: 1 }} exit={{ opacity: 0 }}>
            <header className="px-5 pt-6 pb-4">
              <div className="max-w-lg md:max-w-2xl mx-auto flex items-center gap-3">
                <div className="flex items-center justify-center w-10 h-10 rounded-xl bg-primary/15 text-primary shrink-0">
                  <Pill className="w-6 h-6" strokeWidth={2.2} />
                </div>
                <div>
                  <h1 className="text-xl font-bold text-foreground">PharSayo</h1>
                  <p className="text-xs text-muted-foreground">{dateStr}</p>
                </div>
              </div>
            </header>

            {isPatient && (
              <section className="px-5 pb-4">
                <div className="max-w-lg md:max-w-2xl mx-auto rounded-2xl border border-primary/25 bg-primary/5 p-4 text-sm text-muted-foreground leading-relaxed">
                  <p className="font-semibold text-foreground mb-1">{t(lang, "home.bannerTitle")}</p>
                  <p>{t(lang, "home.bannerBody")}</p>
                </div>
              </section>
            )}

            <section className="px-5 pb-4">
              <div className="max-w-lg md:max-w-2xl mx-auto bg-card rounded-2xl border border-border p-5">
                <ProgressRing taken={takenDoses} total={totalDoses} label={t(lang, "progress.takenLabel")} />
                <p className="text-center text-sm text-muted-foreground mt-2">
                  {!hasPrescribedSlots
                    ? t(lang, "home.noMeds")
                    : totalDoses === 0
                      ? t(lang, "home.noMedsToday")
                      : takenDoses === totalDoses
                        ? (
                          <span className="flex items-center justify-center gap-1.5 text-emerald-600 font-bold">
                            <CheckCircle2 className="w-4 h-4" />
                            {t(lang, "home.allTaken")}
                          </span>
                        )
                        : `${totalDoses - takenDoses} ${t(lang, "home.dosesLeft")}`}
                </p>
              </div>
            </section>

            <section className="px-5">
              <div className="max-w-lg md:max-w-2xl mx-auto">
                <h2 className="font-semibold text-foreground mb-3">{t(lang, "home.scheduleToday")}</h2>
                {!hasPrescribedSlots ? (
                  <div className="text-center py-8 text-muted-foreground">
                    <ClipboardList className="w-10 h-10 mx-auto mb-2 opacity-20" />
                    <p className="text-sm">{t(lang, "home.noMeds")}</p>
                  </div>
                ) : todaysSlots.length === 0 ? (
                  <div className="text-center py-8 text-muted-foreground rounded-2xl border border-dashed border-border">
                    <p className="text-sm">{t(lang, "home.noMedsToday")}</p>
                    <button
                      type="button"
                      onClick={() => setActiveTab("schedule")}
                      className="text-sm text-primary font-medium py-3 hover:underline"
                    >
                      {t(lang, "home.viewAll")} {"->"}
                    </button>
                  </div>
                ) : (
                  <div className="grid gap-2.5">
                    {todaysSlots.slice(0, 4).map((s) => {
                      const isTaken = s.medicine.takenToday.includes(s.slot.time);
                      const isPast = s.slot.time < currentTime && !isTaken;
                      return (
                        <ScheduleCard
                          key={`${s.medicine.id}-${s.slot.scheduleId}`}
                          medicine={s.medicine}
                          time={s.slot.time}
                          scheduleNote={s.slot.notes}
                          isTaken={isTaken}
                          isPast={isPast}
                          readOnly={false}
                          onToggle={() =>
                            isTaken ? markUntaken(s.medicine.id, s.slot.time) : markTaken(s.medicine.id, s.slot.time)
                          }
                        />
                      );
                    })}
                    {(todaysSlots.length > 4 || hasPrescribedSlots) && (
                      <button
                        type="button"
                        onClick={() => setActiveTab("schedule")}
                        className="text-sm text-primary font-medium py-2 hover:underline"
                      >
                        {t(lang, "home.viewAll")} {"->"}
                      </button>
                    )}
                  </div>
                )}
              </div>
            </section>
          </motion.div>
        )}

        {activeTab === "scanner" && (
          <motion.div key="scanner" initial={{ opacity: 0 }} animate={{ opacity: 1 }} exit={{ opacity: 0 }}>
            <header className="px-5 pt-6 pb-4">
              <div className="max-w-lg md:max-w-2xl mx-auto">
                <h1 className="text-xl font-bold text-foreground">{t(lang, "scanner.pageTitle")}</h1>
                <p className="text-xs text-muted-foreground">{t(lang, "scanner.pageSubtitle")}</p>
              </div>
            </header>
            <section className="px-5">
              <div className="max-w-lg md:max-w-2xl mx-auto">
                <MedicineScanner canSave={false} />
              </div>
            </section>
          </motion.div>
        )}

        {activeTab === "schedule" && (
          <motion.div key="schedule" initial={{ opacity: 0 }} animate={{ opacity: 1 }} exit={{ opacity: 0 }}>
            <header className="px-5 pt-6 pb-4 bg-primary/5 border-b border-primary/10">
              <div className="max-w-lg md:max-w-2xl mx-auto">
                <h1 className="text-xl font-bold text-foreground">{t(lang, "schedule.pageTitle")}</h1>
                <div className="mt-2 flex items-center gap-2">
                  <div className="px-3 py-1 bg-primary text-primary-foreground text-[10px] font-bold uppercase tracking-wider rounded-lg shadow-sm">
                    {today.toLocaleDateString(locale, { weekday: "long" })}
                  </div>
                  <p className="text-sm font-bold text-primary">
                    {today.toLocaleDateString(locale, { month: "long", day: "numeric" })}, {today.getFullYear()}
                  </p>
                </div>
                <p className="text-xs text-muted-foreground mt-2 font-medium italic opacity-80">
                  {t(lang, "schedule.pageSubtitle")}
                </p>
              </div>
            </header>
            <section className="px-5">
              <div className="max-w-lg md:max-w-2xl mx-auto space-y-6">
                <div className="rounded-2xl border border-border bg-gradient-to-b from-muted/30 to-card/80 overflow-hidden shadow-sm">
                  <div className="flex flex-wrap items-center justify-between gap-2 px-4 py-3 border-b border-border/80 bg-background/95">
                    <h2 className="font-semibold text-foreground text-sm sm:text-base">{t(lang, "schedule.weekViewTitle")}</h2>
                  </div>
                  <div className="p-3 sm:p-4 space-y-6">
                    {!hasPrescribedSlots ? (
                      <div className="text-center py-8 text-muted-foreground">
                        <p className="text-sm">{t(lang, "schedule.empty")}</p>
                      </div>
                    ) : (
                      weekByDay.map(({ dayDate, ymd, slots }) => {
                        const isToday = sameCalendarDate(dayDate, new Date());
                        const dayTitle = dayDate.toLocaleDateString(locale, {
                          weekday: "long",
                          month: "short",
                          day: "numeric",
                        });
                        return (
                          <div key={ymd} className="rounded-xl border border-border/80 bg-card/40 overflow-hidden">
                            <div
                              className={`px-3 py-2 text-xs font-bold uppercase tracking-wide ${isToday ? "bg-primary/15 text-primary" : "bg-muted/50 text-muted-foreground"}`}
                            >
                              {dayTitle}
                              {isToday ? ` - ${t(lang, "schedule.today")}` : ""}
                            </div>
                            <div className="p-3 space-y-3">
                              {slots.length === 0 ? (
                                <p className="text-xs text-muted-foreground py-2">{t(lang, "schedule.empty")}</p>
                              ) : (
                                <ul className="m-0 list-none space-y-3 p-0">
                                  {slots.map((s) => {
                                    const taken = isSlotTakenForDate(ymd, s.medicine.id, s.slot.time);
                                    const isPast = isToday && s.slot.time < currentTime && !taken;
                                    const readOnly = !isToday;
                                    return (
                                      <li key={`${s.slot.scheduleId}-${ymd}`} className="flex gap-3 sm:gap-4 items-start">
                                        <div className="flex w-[3.25rem] shrink-0 flex-col items-center sm:w-20">
                                          <span className="mt-3 inline-flex min-w-[3.5rem] justify-center rounded-lg bg-primary/12 px-2 py-1.5 font-mono text-xs font-bold tabular-nums text-primary ring-1 ring-primary/20">
                                            {formatScheduleTime12h(s.slot.time)}
                                          </span>
                                        </div>
                                        <div className="min-w-0 flex-1">
                                          <ScheduleCard
                                            medicine={s.medicine}
                                            time={s.slot.time}
                                            scheduleNote={s.slot.notes}
                                            isTaken={taken}
                                            isPast={isPast}
                                            showInlineTime={false}
                                            readOnly={readOnly}
                                            onToggle={() =>
                                              taken
                                                ? markUntaken(s.medicine.id, s.slot.time, ymd)
                                                : markTaken(s.medicine.id, s.slot.time, ymd)
                                            }
                                          />
                                        </div>
                                      </li>
                                    );
                                  })}
                                </ul>
                              )}
                            </div>
                          </div>
                        );
                      })
                    )}
                  </div>
                </div>

                <div className="rounded-2xl border border-border bg-card/60 p-3 shadow-sm sm:p-4">
                  <div className="mb-3 px-1">
                    <h2 className="font-semibold text-foreground">{t(lang, "schedule.medicineList")}</h2>
                    <p className="mt-0.5 text-xs text-muted-foreground">{t(lang, "schedule.medicinesHeadingHint")}</p>
                  </div>
                  <MedicineList medicines={medicines} onRemove={removeMedicine} />
                </div>
              </div>
            </section>
          </motion.div>
        )}

        {activeTab === "profile" && (
          <motion.div key="profile" initial={{ opacity: 0 }} animate={{ opacity: 1 }} exit={{ opacity: 0 }}>
            <header className="px-5 pt-6 pb-4">
              <div className="max-w-lg md:max-w-2xl mx-auto">
                <h1 className="text-xl font-bold text-foreground">{t(lang, "profile.pageTitle")}</h1>
              </div>
            </header>
            <section className="px-5">
              <div className="max-w-lg md:max-w-2xl mx-auto">
                <ProfileTab />
              </div>
            </section>
          </motion.div>
        )}
      </AnimatePresence>

      <BottomNav active={activeTab} onNavigate={setActiveTab} />
    </div>
  );
}
