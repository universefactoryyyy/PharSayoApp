import { useEffect, useState } from "react";
import { useNavigate } from "react-router-dom";
import { useAuth } from "@/contexts/auth-context";
import {
  apiChangePassword,
  apiUpdateProfile,
  type Lang,
} from "@/lib/api";
import { t } from "@/lib/i18n";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select";
import { toast } from "sonner";
import { Pill, Camera, Bell, CheckCircle2, Globe2, Hospital } from "lucide-react";

export default function ProfileTab() {
  const { user, lang, setLang, applyUser, logout } = useAuth();
  const navigate = useNavigate();

  const [name, setName] = useState("");
  const [username, setUsername] = useState("");
  const [phone, setPhone] = useState("");
  const [age, setAge] = useState("");
  const [profileBusy, setProfileBusy] = useState(false);
  const [curPw, setCurPw] = useState("");
  const [newPw, setNewPw] = useState("");
  const [pwBusy, setPwBusy] = useState(false);

  useEffect(() => {
    if (!user) return;
    setName(user.name ?? "");
    setUsername(user.username ?? "");
    setPhone(user.phone ?? "");
    setAge("");
  }, [user]);

  const onLang = async (l: Lang) => {
    await setLang(l);
  };

  const onLogout = () => {
    logout();
    navigate("/login", { replace: true });
  };

  const onSaveProfile = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!user?.id) return;
    setProfileBusy(true);
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
        name: name.trim(),
        username: username.trim(),
        phone: phone.trim(),
        language_preference: lang,
      };
      if (age.trim() !== "") {
        const a = parseInt(age, 10);
        if (!Number.isNaN(a)) payload.age = a;
      }
      const { user: u } = await apiUpdateProfile(payload);
      applyUser(u);
      toast.success(lang === "en" ? "Profile saved." : "Na-save ang profile.");
    } catch (err) {
      toast.error(err instanceof Error ? err.message : "Update failed");
    } finally {
      setProfileBusy(false);
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

  const displayName = user?.role === "doctor" && user.name && !user.name.toLowerCase().startsWith("dr.") && !user.name.toLowerCase().startsWith("dr ")
    ? `Dr. ${user.name}`
    : user?.name ?? "PharSayo";

  return (
    <div className="space-y-6">
      <div className="flex flex-col items-center text-center">
        <div className="flex items-center justify-center w-20 h-20 rounded-2xl bg-primary/15 text-primary mb-4">
          <Pill className="w-11 h-11" strokeWidth={2.2} />
        </div>
        <h2 className="text-xl font-bold text-foreground">{displayName}</h2>
        <p className="text-sm text-muted-foreground mt-1">
          {user?.username ? `@${user.username} - ` : ""}
          {user?.phone} -{" "}
          {user?.role === "patient"
            ? lang === "en"
              ? "Patient"
              : "Pasyente"
            : user?.role === "doctor"
                ? lang === "en"
                  ? "Doctor"
                  : "Doktor"
                : user?.role === "admin"
                  ? lang === "en"
                    ? "Administrator"
                    : "Admin"
                  : user?.role}
          {user?.account_status ? ` - ${user.account_status}` : ""}
        </p>
      </div>

      <form onSubmit={onSaveProfile} className="bg-card rounded-2xl border border-border p-5 space-y-3">
        <h3 className="font-semibold text-foreground">{t(lang, "profile.editTitle")}</h3>
        <div className="space-y-2">
          <Label htmlFor="prof-name">{t(lang, "profile.fullName")}</Label>
          <Input
            id="prof-name"
            value={name}
            onChange={(e) => setName(e.target.value)}
            required
            className="h-11 rounded-xl"
          />
        </div>
        <div className="space-y-2">
          <Label htmlFor="prof-user">{t(lang, "profile.username")}</Label>
          <Input
            id="prof-user"
            value={username}
            onChange={(e) => setUsername(e.target.value)}
            required
            minLength={3}
            maxLength={32}
            className="h-11 rounded-xl font-mono"
          />
        </div>
        <div className="space-y-2">
          <Label htmlFor="prof-phone">{t(lang, "profile.phone")}</Label>
          <Input id="prof-phone" value={phone} onChange={(e) => setPhone(e.target.value)} required className="h-11 rounded-xl" />
        </div>
        <div className="space-y-2">
          <Label htmlFor="prof-age">{t(lang, "profile.age")} ({lang === "en" ? "optional" : "opsyonal"})</Label>
          <Input
            id="prof-age"
            type="number"
            min={1}
            max={120}
            value={age}
            placeholder={user?.role === "patient" ? "—" : ""}
            onChange={(e) => setAge(e.target.value)}
            className="h-11 rounded-xl"
          />
        </div>
        <div className="space-y-2">
          <Label>{t(lang, "profile.language")}</Label>
          <Select value={lang} onValueChange={(v) => void onLang(v as Lang)}>
            <SelectTrigger className="h-11 rounded-xl">
              <SelectValue />
            </SelectTrigger>
            <SelectContent>
              <SelectItem value="fil">Filipino</SelectItem>
              <SelectItem value="en">English</SelectItem>
            </SelectContent>
          </Select>
        </div>
        <Button type="submit" className="w-full h-11 rounded-xl" disabled={profileBusy}>
          {t(lang, "profile.save")}
        </Button>
      </form>

      <form onSubmit={onChangePassword} className="bg-card rounded-2xl border border-border p-5 space-y-3">
        <h3 className="font-semibold text-foreground">{t(lang, "profile.passwordTitle")}</h3>
        <div className="space-y-2">
          <Label htmlFor="cur-pw">{t(lang, "profile.currentPassword")}</Label>
          <Input
            id="cur-pw"
            type="password"
            autoComplete="current-password"
            value={curPw}
            onChange={(e) => setCurPw(e.target.value)}
            required
            className="h-11 rounded-xl"
          />
        </div>
        <div className="space-y-2">
          <Label htmlFor="new-pw">{t(lang, "profile.newPassword")}</Label>
          <Input
            id="new-pw"
            type="password"
            autoComplete="new-password"
            value={newPw}
            onChange={(e) => setNewPw(e.target.value)}
            required
            minLength={6}
            className="h-11 rounded-xl"
          />
        </div>
        <Button type="submit" variant="secondary" className="w-full h-11 rounded-xl" disabled={pwBusy}>
          {t(lang, "profile.changePassword")}
        </Button>
      </form>

      <Button variant="outline" className="w-full h-12 rounded-xl" onClick={onLogout}>
        Sign out
      </Button>

      <div className="bg-card rounded-2xl border border-border p-5 space-y-4">
        <h3 className="font-semibold text-foreground">{t(lang, "profile.aboutTitle")}</h3>
        <p className="text-sm text-muted-foreground leading-relaxed">{t(lang, "profile.aboutP1")}</p>
        <p className="text-sm text-muted-foreground leading-relaxed">{t(lang, "profile.aboutP2")}</p>
      </div>

      <div className="bg-card rounded-2xl border border-border p-5 space-y-3">
        <h3 className="font-semibold text-foreground">{t(lang, "profile.featuresTitle")}</h3>
        <div className="grid gap-2">
          {[
            { icon: <Camera className="w-5 h-5 text-primary" />, text: t(lang, "profile.featureScan") },
            { icon: <Bell className="w-5 h-5 text-primary" />, text: t(lang, "profile.featureRemind") },
            { icon: <CheckCircle2 className="w-5 h-5 text-primary" />, text: t(lang, "profile.featureConfirm") },
            { icon: <Globe2 className="w-5 h-5 text-primary" />, text: t(lang, "profile.featureLang") },
            { icon: <Hospital className="w-5 h-5 text-primary" />, text: t(lang, "profile.featureCommunity") },
          ].map((f, idx) => (
            <div key={idx} className="flex items-center gap-3 p-2.5 rounded-xl bg-secondary/50">
              <div className="shrink-0">{f.icon}</div>
              <span className="text-sm text-foreground">{f.text}</span>
            </div>
          ))}
        </div>
      </div>

      <p className="text-xs text-center text-muted-foreground">{t(lang, "profile.footer")}</p>
    </div>
  );
}
