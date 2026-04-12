import { useEffect, useState } from "react";
import { Link, Navigate, useNavigate } from "react-router-dom";
import { Pill } from "lucide-react";
import { useAuth } from "@/contexts/auth-context";
import { apiBhuList, apiRegister, type Lang } from "@/lib/api";
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

export default function Register() {
  const { user, lang } = useAuth();
  const navigate = useNavigate();
  const [name, setName] = useState("");
  const [username, setUsername] = useState("");
  const [phone, setPhone] = useState("");
  const [password, setPassword] = useState("");
  const [age, setAge] = useState("");
  const [role, setRole] = useState("patient");
  const [pref, setPref] = useState<Lang>(lang);
  const [verificationFile, setVerificationFile] = useState<File | null>(null);
  const [err, setErr] = useState("");
  const [loading, setLoading] = useState(false);

  if (user) {
    return <Navigate to="/" replace />;
  }

  const submit = async (e: React.FormEvent) => {
    e.preventDefault();
    if (loading) return;
    setErr("");
    setLoading(true);
    try {
      if (role === "doctor" && !verificationFile) {
        throw new Error("Doctors must upload an ID for verification.");
      }

      await apiRegister({
        username: username.trim(),
        name: name.trim(),
        phone: phone.trim(),
        password,
        role,
        language_preference: pref,
        age: age ? parseInt(age, 10) : null,
        verification_file: verificationFile,
      });
      navigate("/login", { replace: true });
    } catch (e: unknown) {
      setErr(e instanceof Error ? e.message : "Registration failed");
    } finally {
      setLoading(false);
    }
  };

  return (
    <div className="min-h-screen bg-background flex flex-col items-center justify-center px-5 py-10">
      <div className="w-full max-w-sm space-y-6">
        <div className="text-center space-y-2">
          <div className="inline-flex items-center justify-center w-16 h-16 rounded-2xl bg-primary/15 text-primary mx-auto">
            <Pill className="w-9 h-9" strokeWidth={2.2} />
          </div>
          <h1 className="text-2xl font-extrabold text-foreground">Create account</h1>
        </div>

        <form onSubmit={submit} className="space-y-3 bg-card rounded-2xl border border-border p-6 shadow-sm max-h-[80vh] overflow-y-auto">
          {err && <p className="text-sm text-destructive font-medium">{err}</p>}
          <div className="space-y-2">
            <Label>Language</Label>
            <Select value={pref} onValueChange={(v) => setPref(v as Lang)}>
              <SelectTrigger className="h-11 rounded-xl">
                <SelectValue />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="fil">Filipino</SelectItem>
                <SelectItem value="en">English</SelectItem>
              </SelectContent>
            </Select>
          </div>
          <div className="space-y-2">
            <Label htmlFor="name">Full name</Label>
            <Input id="name" value={name} onChange={(e) => setName(e.target.value)} required className="h-11 rounded-xl" />
          </div>
          <div className="space-y-2">
            <Label htmlFor="username">Username</Label>
            <Input
              id="username"
              value={username}
              onChange={(e) => setUsername(e.target.value)}
              required
              minLength={3}
              maxLength={32}
              autoComplete="username"
              placeholder="letters, numbers, . _ -"
              className="h-11 rounded-xl font-mono"
            />
          </div>
          <div className="space-y-2">
            <Label htmlFor="age">Age (optional)</Label>
            <Input id="age" type="number" min={1} max={120} value={age} onChange={(e) => setAge(e.target.value)} className="h-11 rounded-xl" />
          </div>
          <div className="space-y-2">
            <Label htmlFor="phone">Mobile number</Label>
            <Input id="phone" type="tel" value={phone} onChange={(e) => setPhone(e.target.value)} required className="h-11 rounded-xl" />
          </div>
          <div className="space-y-2">
            <Label htmlFor="password">Password</Label>
            <Input id="password" type="password" value={password} onChange={(e) => setPassword(e.target.value)} required className="h-11 rounded-xl" />
          </div>
          <div className="space-y-2">
            <Label>Account type</Label>
            <Select value={role} onValueChange={setRole}>
              <SelectTrigger className="h-11 rounded-xl">
                <SelectValue />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="patient">Patient</SelectItem>
                <SelectItem value="doctor">Doctor</SelectItem>
              </SelectContent>
            </Select>

            {role === "doctor" && (
              <div className="mt-4 space-y-2 p-3 bg-muted/50 rounded-xl border border-dashed border-primary/30">
                <Label htmlFor="id-file" className="text-primary font-semibold">Doctor ID / Evidence</Label>
                <Input
                  id="id-file"
                  type="file"
                  accept="image/*,.pdf"
                  onChange={(e) => setVerificationFile(e.target.files?.[0] || null)}
                  required={role === "doctor"}
                  className="h-auto py-1 px-1 text-xs cursor-pointer"
                />
                <p className="text-[10px] text-muted-foreground">Upload a photo of your license or medical ID.</p>
              </div>
            )}
            
            <p className="text-[11px] text-muted-foreground leading-relaxed">
              {role === "patient" ? t(pref, "register.activeNote") : t(pref, "register.pendingNote")}
            </p>
          </div>
          <Button type="submit" className="w-full h-12 rounded-xl font-bold" disabled={loading}>
            {loading ? "Creating…" : "Register"}
          </Button>
        </form>

        <p className="text-center text-sm text-muted-foreground">
          <Link to="/login" className="text-primary font-semibold hover:underline">
            Back to sign in
          </Link>
        </p>
      </div>
    </div>
  );
}
