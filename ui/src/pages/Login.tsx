import { useState } from "react";
import { Link, Navigate, useNavigate } from "react-router-dom";
import { Pill } from "lucide-react";
import { useAuth } from "@/contexts/auth-context";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";

export default function Login() {
  const { user, login, loading } = useAuth();
  const navigate = useNavigate();
  const [username, setUsername] = useState("");
  const [password, setPassword] = useState("");
  const [err, setErr] = useState("");

  if (user) {
    return <Navigate to="/" replace />;
  }

  const submit = async (e: React.FormEvent) => {
    e.preventDefault();
    setErr("");
    try {
      await login(username.trim(), password);
      navigate("/", { replace: true });
    } catch (e) {
      setErr(e instanceof Error ? e.message : "Invalid username or password.");
    }
  };

  return (
    <div className="min-h-screen bg-background flex flex-col items-center justify-center px-5 pb-12">
      <div className="w-full max-w-sm space-y-8">
        <div className="text-center space-y-2">
          <div className="inline-flex items-center justify-center w-16 h-16 rounded-2xl bg-primary/15 text-primary mx-auto">
            <Pill className="w-9 h-9" strokeWidth={2.2} />
          </div>
          <h1 className="text-2xl font-extrabold text-foreground tracking-tight">PharSayo</h1>
          <p className="text-sm text-muted-foreground">Sign in with username and password</p>
        </div>

        <form onSubmit={submit} className="space-y-4 bg-card rounded-2xl border border-border p-6 shadow-sm">
          {err && <p className="text-sm text-destructive font-medium">{err}</p>}
          <div className="space-y-2">
            <Label htmlFor="username">Username</Label>
            <Input
              id="username"
              type="text"
              autoComplete="username"
              placeholder="your.username"
              value={username}
              onChange={(e) => setUsername(e.target.value)}
              className="h-12 rounded-xl"
            />
          </div>
          <div className="space-y-2">
            <Label htmlFor="password">Password</Label>
            <Input
              id="password"
              type="password"
              autoComplete="current-password"
              value={password}
              onChange={(e) => setPassword(e.target.value)}
              className="h-12 rounded-xl"
            />
          </div>
          <Button type="submit" className="w-full h-12 rounded-xl text-base font-bold" disabled={loading}>
            {loading ? "Signing in…" : "Sign in"}
          </Button>
        </form>

        <p className="text-center text-sm text-muted-foreground">
          No account?{" "}
          <Link to="/register" className="text-primary font-semibold hover:underline">
            Create account
          </Link>
        </p>
      </div>
    </div>
  );
}
