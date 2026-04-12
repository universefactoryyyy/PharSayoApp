import { Pill, Syringe, Bandage, FlaskConical, Activity, Heart } from "lucide-react";

export default function MedicineIcon({ icon, className }: { icon: string; className?: string }) {
  switch (icon) {
    case "Pill":
      return <Pill className={className} />;
    case "Syringe":
      return <Syringe className={className} />;
    case "Bandage":
      return <Bandage className={className} />;
    case "Flask":
      return <FlaskConical className={className} />;
    case "Activity":
      return <Activity className={className} />;
    case "Heart":
      return <Heart className={className} />;
    default:
      return <Pill className={className} />;
  }
}
