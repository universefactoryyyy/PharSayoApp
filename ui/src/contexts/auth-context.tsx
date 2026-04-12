import {
  createContext,
  useCallback,
  useContext,
  useEffect,
  useMemo,
  useState,
  type ReactNode,
} from "react";
import type { ApiUser, Lang } from "@/lib/api";
import { apiLogin, apiLogout, apiUpdateLanguage } from "@/lib/api";

const USER_KEY = "pharsayo_user";
const LANG_KEY = "lang";

type AuthContextValue = {
  user: ApiUser | null;
  lang: Lang;
  setLang: (l: Lang) => Promise<void>;
  applyUser: (u: ApiUser) => void;
  login: (username: string, password: string) => Promise<void>;
  logout: () => void;
  loading: boolean;
};

const AuthContext = createContext<AuthContextValue | null>(null);

function readStoredUser(): ApiUser | null {
  try {
    const raw = localStorage.getItem(USER_KEY);
    if (!raw) return null;
    return JSON.parse(raw) as ApiUser;
  } catch {
    return null;
  }
}

export function AuthProvider({ children }: { children: ReactNode }) {
  const [user, setUser] = useState<ApiUser | null>(() => readStoredUser());
  const [lang, setLangState] = useState<Lang>(() => {
    const u = readStoredUser();
    const s = localStorage.getItem(LANG_KEY) as Lang | null;
    if (u && u.language_preference) return u.language_preference;
    return s === "en" ? "en" : "fil";
  });
  const [loading, setLoading] = useState(false);

  useEffect(() => {
    localStorage.setItem(LANG_KEY, lang);
  }, [lang]);

  const applyUser = useCallback((u: ApiUser) => {
    if (!u) return;
    setUser(u);
    localStorage.setItem(USER_KEY, JSON.stringify(u));
    const l = u.language_preference === "en" ? "en" : "fil";
    setLangState(l);
    localStorage.setItem(LANG_KEY, l);
  }, []);

  const login = useCallback(
    async (username: string, password: string) => {
      setLoading(true);
      try {
        const res = await apiLogin(username, password);
        console.log("Full login response:", res);
        if (res && res.user) {
          applyUser(res.user);
        } else {
          console.error("Login response missing user:", res);
          const detail = res && typeof res === 'object' ? JSON.stringify(res) : 'Not an object';
          throw new Error(`Login failed: Invalid server response. Data: ${detail.substring(0, 150)}`);
        }
      } finally {
        setLoading(false);
      }
    },
    [applyUser]
  );

  const logout = useCallback(() => {
    setUser(null);
    localStorage.removeItem(USER_KEY);
    void apiLogout();
  }, []);

  const setLang = useCallback(
    async (l: Lang) => {
      setLangState(l);
      localStorage.setItem(LANG_KEY, l);
      if (user) {
        const next = { ...user, language_preference: l };
        setUser(next);
        localStorage.setItem(USER_KEY, JSON.stringify(next));
        try {
          await apiUpdateLanguage(user.id, l);
        } catch {
          /* offline */
        }
      }
    },
    [user]
  );

  const value = useMemo(
    () => ({ user, lang, setLang, applyUser, login, logout, loading }),
    [user, lang, setLang, applyUser, login, logout, loading]
  );

  return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>;
}

export function useAuth() {
  const ctx = useContext(AuthContext);
  if (!ctx) throw new Error("useAuth outside AuthProvider");
  return ctx;
}
