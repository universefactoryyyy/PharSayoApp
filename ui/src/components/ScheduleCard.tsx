import { motion } from "framer-motion";
import { Check, Clock, AlertTriangle, Lock } from "lucide-react";
import type { Medicine } from "@/lib/medicine-store";
import { useAuth } from "@/contexts/auth-context";
import { t } from "@/lib/i18n";
import { formatScheduleTime12h } from "@/lib/utils";
import MedicineIcon from "@/components/MedicineIcon";
import {
  AlertDialog,
  AlertDialogAction,
  AlertDialogCancel,
  AlertDialogContent,
  AlertDialogDescription,
  AlertDialogFooter,
  AlertDialogHeader,
  AlertDialogTitle,
  AlertDialogTrigger,
} from "@/components/ui/alert-dialog";

interface Props {
  medicine: Medicine;
  time: string;
  isTaken: boolean;
  isPast: boolean;
  onToggle: () => void;
  /** When false, time is shown by the parent (e.g. schedule timeline). Default true. */
  showInlineTime?: boolean;
  /** Override per-row schedule note from DB. */
  scheduleNote?: string;
  /** If true, patient cannot confirm or un-confirm (not the calendar day of this dose). */
  readOnly?: boolean;
}

export default function ScheduleCard({
  medicine,
  time,
  isTaken,
  isPast,
  onToggle,
  showInlineTime = true,
  scheduleNote: scheduleNoteProp,
  readOnly = false,
}: Props) {
  const { lang } = useAuth();
  const statusBg = isTaken
    ? "bg-success/10 border-success/30"
    : isPast
      ? "bg-amber-50/90 border-amber-200/80 dark:bg-amber-950/25 dark:border-amber-800/40"
      : "bg-muted/20 border-border";

  const purposeLine = lang === "en" ? medicine.purpose : medicine.purposeFil;
  const precLine = lang === "en" ? medicine.precautions : medicine.precautionsFil;
  const rowScheduleNote =
    (scheduleNoteProp && scheduleNoteProp.trim() ? scheduleNoteProp : "") ||
    medicine.scheduleNotesByTime[time] ||
    medicine.notes;
  const purposeForCard =
    lang === "fil"
      ? medicine.purposeFil && medicine.purposeFil.trim()
        ? medicine.purposeFil
        : ""
      : medicine.purpose && medicine.purpose.trim()
        ? medicine.purpose
        : "";
  const instruction = rowScheduleNote || purposeForCard || medicine.frequency;
  const subLine = [medicine.dosage, instruction].filter(Boolean).join(" - ");

  if (readOnly) {
    return (
      <div
        className={`w-full flex items-start gap-4 p-4 rounded-2xl border-2 text-left ${statusBg} opacity-95`}
      >
        <div
          className="w-13 h-13 rounded-xl flex items-center justify-center text-xl shrink-0 mt-0.5"
          style={{
            backgroundColor: medicine.color + "22",
            color: medicine.color,
          }}
        >
          {isTaken ? <Check className="w-6 h-6" /> : <Lock className="w-5 h-5 opacity-70" />}
        </div>
        <div className="flex-1 min-w-0">
          <p className="font-semibold text-foreground text-base leading-snug">{medicine.name}</p>
          <p className="text-sm text-muted-foreground line-clamp-2 break-words hyphens-auto mt-0.5">{subLine}</p>
          {medicine.prescribedByDoctorName && (
            <p className="text-[11px] text-primary/80 font-medium mt-1">
              {t(lang, "scheduleCard.prescribedBy")}: {medicine.prescribedByDoctorName}
            </p>
          )}
          {!isTaken ? (
            <p className="text-[11px] text-muted-foreground mt-2">{t(lang, "schedule.viewOnlyOtherDay")}</p>
          ) : null}
        </div>
        {showInlineTime ? (
          <div className="flex items-center gap-1.5 text-sm text-muted-foreground shrink-0 pt-1">
            <Clock className="w-3.5 h-3.5" />
            {formatScheduleTime12h(time)}
          </div>
        ) : null}
      </div>
    );
  }

  if (!isTaken) {
    return (
      <AlertDialog>
        <AlertDialogTrigger asChild>
          <motion.button
            layout
            initial={{ opacity: 0, y: 10 }}
            animate={{ opacity: 1, y: 0 }}
            type="button"
            className={`w-full flex items-start gap-4 p-4 rounded-2xl border-2 transition-colors text-left ${statusBg}`}
          >
            <div
              className="w-13 h-13 rounded-xl flex items-center justify-center shrink-0 mt-0.5"
              style={{
                backgroundColor: medicine.color + "22",
                color: isPast ? "hsl(32 95% 44%)" : medicine.color,
              }}
            >
              {isPast ? (
                <AlertTriangle className="w-5 h-5" />
              ) : (
                <MedicineIcon icon={medicine.icon} className="w-6 h-6" />
              )}
            </div>
            <div className="flex-1 min-w-0">
              <p className="font-semibold text-foreground text-base leading-snug">{medicine.name}</p>
              <p className="text-sm text-muted-foreground line-clamp-2 break-words hyphens-auto mt-0.5">{subLine}</p>
              {medicine.prescribedByDoctorName && (
                <p className="text-[11px] text-primary/80 font-medium mt-1">
                  {t(lang, "scheduleCard.prescribedBy")}: {medicine.prescribedByDoctorName}
                </p>
              )}
            </div>
            {showInlineTime ? (
              <div className="flex items-center gap-1.5 text-sm text-muted-foreground shrink-0 pt-1">
                <Clock className="w-3.5 h-3.5" />
                {formatScheduleTime12h(time)}
              </div>
            ) : null}
          </motion.button>
        </AlertDialogTrigger>
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle className="text-lg">{t(lang, "scheduleCard.confirmTitle")}</AlertDialogTitle>
            <AlertDialogDescription asChild>
              <div className="space-y-2 max-h-[min(60vh,320px)] overflow-y-auto text-left">
                <span className="block">
                  <strong>{medicine.name}</strong> ({medicine.dosage}) — {formatScheduleTime12h(time)}
                </span>
                {rowScheduleNote ? <span className="block text-sm text-foreground">{rowScheduleNote}</span> : null}
                <span className="block text-sm break-words whitespace-pre-wrap">{purposeLine}</span>
                <span className="block text-sm text-muted-foreground break-words whitespace-pre-wrap">{precLine}</span>
                {!rowScheduleNote && medicine.frequency ? (
                  <span className="block text-xs text-muted-foreground border-t border-border pt-2 break-words">
                    {medicine.frequency}
                  </span>
                ) : null}
              </div>
            </AlertDialogDescription>
          </AlertDialogHeader>
          <AlertDialogFooter>
            <AlertDialogCancel>{t(lang, "scheduleCard.confirmNo")}</AlertDialogCancel>
            <AlertDialogAction onClick={onToggle}>
              <Check className="w-4 h-4 mr-1" /> {t(lang, "scheduleCard.confirmYes")}
            </AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>
    );
  }

  return (
    <motion.button
      layout
      type="button"
      initial={{ opacity: 0, y: 10 }}
      animate={{ opacity: 1, y: 0 }}
      onClick={onToggle}
      className={`w-full flex items-center gap-4 p-4 rounded-2xl border-2 transition-colors text-left ${statusBg}`}
    >
      <div
        className="w-13 h-13 rounded-xl flex items-center justify-center shrink-0"
        style={{ backgroundColor: medicine.color + "22", color: medicine.color }}
      >
        <Check className="w-6 h-6" />
      </div>
      <div className="flex-1 min-w-0">
        <p className="font-semibold line-through text-muted-foreground text-base">{medicine.name}</p>
        <p className="text-sm text-muted-foreground">
          {medicine.dosage} · {t(lang, "scheduleCard.takenLine")}
        </p>
        {medicine.prescribedByDoctorName && (
          <p className="text-[10px] text-muted-foreground/60 mt-0.5">
            {t(lang, "scheduleCard.prescribedBy")}: {medicine.prescribedByDoctorName}
          </p>
        )}
      </div>
      {showInlineTime ? (
        <div className="flex items-center gap-1.5 text-sm text-muted-foreground shrink-0">
          <Clock className="w-3.5 h-3.5" />
          {formatScheduleTime12h(time)}
        </div>
      ) : null}
    </motion.button>
  );
}
