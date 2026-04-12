import { motion } from "framer-motion";
import { Trash2, Info } from "lucide-react";
import { useState } from "react";
import type { Medicine } from "@/lib/medicine-store";
import { useAuth } from "@/contexts/auth-context";
import { t } from "@/lib/i18n";
import { Button } from "@/components/ui/button";
import { Dialog, DialogContent, DialogHeader, DialogTitle } from "@/components/ui/dialog";

interface Props {
  medicines: Medicine[];
  onRemove: (id: string) => void;
}

export default function MedicineList({ medicines }: Props) {
  const { lang, user } = useAuth();
  const isPatient = user?.role === "patient";
  const [selected, setSelected] = useState<Medicine | null>(null);

  if (medicines.length === 0) {
    return (
      <div className="text-center py-12 text-muted-foreground">
        <p className="text-4xl mb-3">💊</p>
        <p className="text-base">{t(lang, "medicineList.emptyTitle")}</p>
        <p className="text-sm mt-1">{t(lang, "medicineList.emptyHint")}</p>
      </div>
    );
  }

  const purposeLabel = t(lang, "medicineDialog.purpose");
  const purposeBody = lang === "en" ? selected?.purpose : selected?.purposeFil;
  const precBody = lang === "en" ? selected?.precautions : selected?.precautionsFil;

  return (
    <>
      <div className="grid gap-3">
        {medicines.map((med, i) => {
          const detail =
            lang === "fil"
              ? med.purposeFil?.trim() || med.frequency
              : med.purpose?.trim() || med.frequency;
          return (
          <motion.div
            key={med.id}
            initial={{ opacity: 0, y: 10 }}
            animate={{ opacity: 1, y: 0 }}
            transition={{ delay: i * 0.05 }}
            className="flex items-center gap-3 p-4 rounded-2xl bg-card border border-border"
          >
            <div
              className="w-12 h-12 rounded-xl flex items-center justify-center text-lg shrink-0"
              style={{ backgroundColor: med.color + "22" }}
            >
              {med.icon}
            </div>
            <div className="flex-1 min-w-0">
              <p className="font-semibold text-foreground">{med.name}</p>
              <p className="text-sm text-muted-foreground line-clamp-2 break-words leading-snug">
                {med.dosage} · {detail}
              </p>
            </div>
            <Button
              type="button"
              size="sm"
              variant="secondary"
              className="rounded-xl shrink-0 gap-1.5 h-9"
              onClick={() => setSelected(med)}
            >
              <Info className="w-4 h-4" />
              {t(lang, "medicineList.info")}
            </Button>
            {!isPatient && (
              <Button
                type="button"
                size="icon"
                variant="ghost"
                className="rounded-xl shrink-0 text-muted-foreground hover:text-destructive h-9 w-9"
                onClick={() => onRemove(med.id)}
              >
                <Trash2 className="w-4 h-4" />
              </Button>
            )}
          </motion.div>
          );
        })}
      </div>

      <Dialog open={!!selected} onOpenChange={(open) => !open && setSelected(null)}>
        {selected && (
          <DialogContent className="sm:max-w-md">
            <DialogHeader>
              <DialogTitle className="flex items-center gap-3">
                <span className="text-2xl">{selected.icon}</span>
                {selected.name}
              </DialogTitle>
            </DialogHeader>
            <div className="space-y-4 mt-2">
              <div className="grid grid-cols-2 gap-3">
                <div className="p-3 rounded-xl bg-secondary">
                  <p className="text-xs text-muted-foreground">{t(lang, "medicineDialog.generic")}</p>
                  <p className="text-sm font-medium text-foreground">{selected.genericName}</p>
                </div>
                <div className="p-3 rounded-xl bg-secondary">
                  <p className="text-xs text-muted-foreground">{t(lang, "medicineDialog.dosage")}</p>
                  <p className="text-sm font-medium text-foreground">{selected.dosage}</p>
                </div>
              </div>
              <div className="p-3 rounded-xl bg-primary/5">
                <p className="text-xs font-semibold text-primary uppercase tracking-wider mb-1">{purposeLabel}</p>
                <p className="text-sm text-foreground">{purposeBody}</p>
              </div>
              <div className="p-3 rounded-xl bg-accent/10">
                <p className="text-xs font-semibold text-accent uppercase tracking-wider mb-1">
                  {t(lang, "medicineDialog.precautions")}
                </p>
                <p className="text-sm text-foreground">{precBody}</p>
              </div>
              {selected.notes ? (
                <div className="p-3 rounded-xl bg-secondary">
                  <p className="text-xs text-muted-foreground mb-1">{t(lang, "medicineDialog.notes")}</p>
                  <p className="text-sm text-foreground">{selected.notes}</p>
                </div>
              ) : null}
              <div className="p-3 rounded-xl bg-secondary">
                <p className="text-xs text-muted-foreground mb-1">{t(lang, "medicineDialog.schedule")}</p>
                <p className="text-sm font-medium text-foreground">
                  {selected.frequency} · {selected.times.join(", ")}
                </p>
              </div>
              <button
                type="button"
                onClick={() => {
                  onRemove(selected.id);
                  setSelected(null);
                }}
                className="flex items-center gap-2 text-destructive text-sm hover:underline mx-auto"
              >
                <Trash2 className="w-4 h-4" /> {t(lang, "medicineList.remove")}
              </button>
            </div>
          </DialogContent>
        )}
      </Dialog>
    </>
  );
}
