import { useState } from "react";
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogTrigger } from "@/components/ui/dialog";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Plus, X } from "lucide-react";
import { toast } from "sonner";

export type NewMedicinePayload = {
  name: string;
  genericName: string;
  dosage: string;
  frequency: string;
  times: string[];
  purpose: string;
  purposeFil: string;
  precautions: string;
  precautionsFil: string;
  notes: string;
};

interface Props {
  onAdd: (med: NewMedicinePayload) => void | Promise<void>;
}

export default function AddMedicineDialog({ onAdd }: Props) {
  const [open, setOpen] = useState(false);
  const [saving, setSaving] = useState(false);
  const [name, setName] = useState("");
  const [genericName, setGenericName] = useState("");
  const [dosage, setDosage] = useState("");
  const [frequency, setFrequency] = useState("Isang beses araw-araw");
  const [times, setTimes] = useState(["08:00"]);
  const [purpose, setPurpose] = useState("");
  const [precautions, setPrecautions] = useState("");
  const [notes, setNotes] = useState("");

  const reset = () => {
    setName("");
    setGenericName("");
    setDosage("");
    setFrequency("Isang beses araw-araw");
    setTimes(["08:00"]);
    setPurpose("");
    setPrecautions("");
    setNotes("");
  };

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!name || !dosage) return;
    setSaving(true);
    try {
      await Promise.resolve(
        onAdd({
          name,
          genericName,
          dosage,
          frequency,
          times,
          purpose,
          purposeFil: purpose,
          precautions,
          precautionsFil: precautions,
          notes,
        })
      );
      reset();
      setOpen(false);
      toast.success("Naidagdag ang gamot.");
    } catch (err) {
      toast.error(err instanceof Error ? err.message : "Hindi maidagdag ang gamot.");
    } finally {
      setSaving(false);
    }
  };

  return (
    <Dialog open={open} onOpenChange={setOpen}>
      <DialogTrigger asChild>
        <Button size="lg" className="rounded-full gap-2 shadow-lg shadow-primary/25">
          <Plus className="w-5 h-5" /> Magdagdag ng Gamot
        </Button>
      </DialogTrigger>
      <DialogContent className="sm:max-w-md max-h-[85vh] overflow-y-auto">
        <DialogHeader>
          <DialogTitle>Magdagdag ng Bagong Gamot</DialogTitle>
        </DialogHeader>
        <form onSubmit={(e) => void handleSubmit(e)} className="space-y-3 mt-2">
          <div className="space-y-1.5">
            <Label htmlFor="name">Pangalan ng Gamot</Label>
            <Input id="name" placeholder="hal. Metformin" value={name} onChange={(e) => setName(e.target.value)} />
          </div>
          <div className="space-y-1.5">
            <Label htmlFor="generic">Generic Name</Label>
            <Input
              id="generic"
              placeholder="hal. Metformin HCl"
              value={genericName}
              onChange={(e) => setGenericName(e.target.value)}
            />
          </div>
          <div className="grid grid-cols-2 gap-3">
            <div className="space-y-1.5">
              <Label htmlFor="dosage">Dosage</Label>
              <Input id="dosage" placeholder="hal. 500mg" value={dosage} onChange={(e) => setDosage(e.target.value)} />
            </div>
            <div className="space-y-1.5">
              <Label htmlFor="freq">Dalas</Label>
              <Input id="freq" value={frequency} onChange={(e) => setFrequency(e.target.value)} />
            </div>
          </div>
          <div className="space-y-1.5">
            <Label>Oras ng Pag-inom</Label>
            <div className="flex flex-wrap gap-2">
              {times.map((t, i) => (
                <div key={i} className="flex items-center gap-1">
                  <Input
                    type="time"
                    value={t}
                    onChange={(e) => {
                      const n = [...times];
                      n[i] = e.target.value;
                      setTimes(n);
                    }}
                    className="w-28"
                  />
                  {times.length > 1 && (
                    <button
                      type="button"
                      onClick={() => setTimes(times.filter((_, j) => j !== i))}
                      className="text-muted-foreground hover:text-destructive"
                    >
                      <X className="w-4 h-4" />
                    </button>
                  )}
                </div>
              ))}
              <Button type="button" variant="outline" size="sm" onClick={() => setTimes([...times, "12:00"])}>
                <Plus className="w-3 h-3 mr-1" /> Dagdagan
              </Button>
            </div>
          </div>
          <div className="space-y-1.5">
            <Label htmlFor="purpose">Para Saan</Label>
            <Input
              id="purpose"
              placeholder="hal. Pampababa ng asukal sa dugo"
              value={purpose}
              onChange={(e) => setPurpose(e.target.value)}
            />
          </div>
          <div className="space-y-1.5">
            <Label htmlFor="precautions">Mga Babala</Label>
            <Input
              id="precautions"
              placeholder="hal. Inumin kasabay ng pagkain"
              value={precautions}
              onChange={(e) => setPrecautions(e.target.value)}
            />
          </div>
          <div className="space-y-1.5">
            <Label htmlFor="notes">Mga Tala</Label>
            <Input id="notes" placeholder="hal. Inumin pagkatapos kumain" value={notes} onChange={(e) => setNotes(e.target.value)} />
          </div>
          <Button type="submit" className="w-full h-12 rounded-xl text-base" disabled={saving}>
            {saving ? "Sine-save..." : "Idagdag ang Gamot"}
          </Button>
        </form>
      </DialogContent>
    </Dialog>
  );
}
