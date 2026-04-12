import { Home, ScanLine, Clock, User } from "lucide-react";
import { useAuth } from "@/contexts/auth-context";
import { t } from "@/lib/i18n";

interface Props {
  active: string;
  onNavigate: (tab: string) => void;
}

const tabs = [
  { id: "home", key: "nav.home" as const, icon: Home },
  { id: "scanner", key: "nav.scan" as const, icon: ScanLine },
  { id: "schedule", key: "nav.schedule" as const, icon: Clock },
  { id: "profile", key: "nav.profile" as const, icon: User },
];

export default function BottomNav({ active, onNavigate }: Props) {
  const { lang } = useAuth();
  return (
    <nav className="fixed bottom-0 left-0 right-0 z-50 bg-card/95 backdrop-blur-lg border-t border-border">
      <div className="max-w-lg md:max-w-2xl mx-auto flex items-center justify-around h-16">
        {tabs.map((tab) => {
          const isActive = active === tab.id;
          const Icon = tab.icon;
          return (
            <button
              key={tab.id}
              onClick={() => onNavigate(tab.id)}
              className={`flex flex-col items-center gap-0.5 px-3 py-1.5 rounded-xl transition-colors ${
                isActive
                  ? "text-primary"
                  : "text-muted-foreground hover:text-foreground"
              }`}
            >
              <div className={`p-1.5 rounded-xl transition-colors ${isActive ? "bg-primary/10" : ""}`}>
                <Icon className="w-5 h-5" strokeWidth={isActive ? 2.5 : 2} />
              </div>
              <span className="text-[10px] font-semibold">{t(lang, tab.key)}</span>
            </button>
          );
        })}
      </div>
    </nav>
  );
}
