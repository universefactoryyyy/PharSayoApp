/** PharSayo PHP API — same origin, base `./api` */
const _b = import.meta.env.BASE_URL || "/";

function normalizeApiBase(rawBase: string): string {
  let base = (rawBase || "").trim();
  if (!base) return "";

  while (base.endsWith("/") || base.endsWith(".") || base.endsWith(" ")) {
    base = base.slice(0, -1);
  }

  return base;
}

// DIAGNOSTIC: Export the base URL so we can see it on the login screen
export const GET_API_BASE = () => {
  let base = import.meta.env.VITE_API_URL || "";
  
  if (!base) {
    const host = (window.location.hostname || "").toLowerCase();
    const proto = window.location.protocol || "http:";
    const isFileOrLocal =
      (window.location.protocol === "file:" || host === "localhost" || host === "127.0.0.1") &&
      !import.meta.env.DEV;

    if (isFileOrLocal) {
      // Default remote API when app runs as file:// on mobile.
      base = "http://pharsayo.atwebpages.com/api";
    } else if (host.endsWith("atwebpages.com")) {
      // Use the same host on AwardSpace deployments.
      base = `${proto}//${host}/api`;
    } else {
      base = _b.endsWith("/") ? `${_b}api` : `${_b}/api`;
    }
  }

  return normalizeApiBase(base);
};

const API_BASE = normalizeApiBase(GET_API_BASE());

function getApiBaseCandidates(): string[] {
  const out: string[] = [];
  const push = (u: string) => {
    let v = normalizeApiBase(u);
    if (v && !out.includes(v)) out.push(v);
  };

  push(API_BASE);

  const protoSwap = (u: string) => {
    if (u.includes("atwebpages.com")) return u; // do not force HTTPS here
    if (u.startsWith("https://")) return `http://${u.slice("https://".length)}`;
    if (u.startsWith("http://")) return `https://${u.slice("http://".length)}`;
    return u;
  };

  push(protoSwap(API_BASE));

  return out;
}

export type Lang = "fil" | "en";

export interface ApiUser {
  id: number;
  name: string;
  role: string;
  bhu_id: number | null;
  phone: string;
  username?: string;
  language_preference: Lang;
  account_status?: string;
}

export interface ApiMedication {
  id: number;
  user_id: number;
  name: string;
  dosage: string;
  frequency: string;
  purpose_fil: string;
  purpose_en: string;
  precautions_fil: string;
  precautions_en: string;
  notes?: string;
  purpose?: string;
  precautions?: string;
  prescribed_by?: number | null;
  doctor_name?: string | null;
  /** Raw Filipino frequency text (API may omit before migration). */
  frequency_fil?: string | null;
}

export interface ApiSchedule {
  id: number;
  medication_id: number;
  user_id: number;
  reminder_time: string;
  days_of_week: string;
  start_date?: string | null;
  end_date?: string | null;
  medication_name?: string;
  notes?: string;
}

async function jsonFetch<T>(url: string, init?: RequestInit): Promise<T> {
  console.log(`Fetching: ${url}`);
  try {
    const res = await fetch(url, {
      ...init,
      mode: 'cors',
      cache: 'no-cache', // Ensure we don't get cached errors
      headers: {
        "Content-Type": "application/json",
        "Accept": "application/json",
        "X-Requested-With": "XMLHttpRequest",
        ...init?.headers,
      },
    });
    
    const text = await res.text();
    console.log(`Raw response from ${url}:`, text.substring(0, 500));

    // Security Check (common on free hosts like InfinityFree/AwardSpace)
    if (text.includes("aes.js") || text.includes("Checking your browser") || text.includes("Javascript is required")) {
      throw new Error("Free hosting security check detected. Please open the URL in your phone's browser once to 'unlock' access, then return to the app.");
    }

    let data: any;
    try {
      data = JSON.parse(text);
    } catch (e) {
      if (text.toLowerCase().includes("not found") || text.includes("404")) {
        throw new Error(`Server returned 404 (Not Found) for "${url}". Please check if the backend files are uploaded correctly.`);
      }
      if (text.toLowerCase().includes("forbidden") || text.includes("403")) {
        throw new Error(`Server returned 403 (Forbidden). This often means the server is blocking the app or CORS is misconfigured.`);
      }
      throw new Error(`Server returned non-JSON response (starts with: ${text.substring(0, 50)}...). This usually means a server error or a redirect happened.`);
    }

    if (!res.ok) {
      throw new Error(data.message || `HTTP ${res.status}`);
    }
    return data as T;
  } catch (e) {
    console.error("Fetch error detail:", e);
    const msg = e instanceof Error ? e.message : String(e);
    
    if (msg.toLowerCase().includes("failed to fetch")) {
      // Provide much more detailed help for "Failed to fetch"
      throw new Error(`Network Error: Failed to fetch from "${url}". Possible causes: 1. Server URL is wrong. 2. Phone has no internet. 3. Server is down. 4. CORS/SSL blocking. (HINT: Check if you need HTTPS or if AwardSpace security check is active). Current Server URL: "${GET_API_BASE()}"`);
    }
    throw e;
  }
}

function isNativeMobileRuntime(): boolean {
  try {
    const cap = (window as any)?.Capacitor;
    const p = cap?.getPlatform?.();
    return p === "android" || p === "ios";
  } catch {
    return false;
  }
}

async function nativeHttpPostJson<T>(url: string, data: Record<string, unknown>): Promise<T> {
  const cap = (window as any)?.Capacitor;
  const http = cap?.Plugins?.CapacitorHttp;
  if (!http?.request) {
    throw new Error("CapacitorHttp plugin is not available.");
  }

  const res = await http.request({
    url,
    method: "POST",
    headers: {
      "Content-Type": "application/json",
      "Accept": "application/json",
      "X-Requested-With": "XMLHttpRequest",
    },
    data,
    connectTimeout: 15000,
    readTimeout: 20000,
  });

  const raw = typeof res?.data === "string" ? res.data : JSON.stringify(res?.data ?? {});
  let parsed: any;
  try {
    parsed = typeof res?.data === "object" && res?.data !== null ? res.data : JSON.parse(raw);
  } catch {
    throw new Error(`Server returned non-JSON response (starts with: ${raw.substring(0, 50)}...)`);
  }

  const status = Number(res?.status ?? 0);
  if (status < 200 || status >= 300) {
    throw new Error(parsed?.message || `HTTP ${status}`);
  }
  return parsed as T;
}

export async function apiLogin(username: string, password: string): Promise<{ user: ApiUser }> {
  const loginPayload = { username: username.trim(), password };
  const body = JSON.stringify(loginPayload);
  const nonce = Date.now();
  const candidates = Array.from(
    new Set([
      "http://pharsayo.atwebpages.com/api",
      ...getApiBaseCandidates(),
    ]),
  );
  let lastErr: unknown = null;

  for (const base of candidates) {
    const mobileGetUrl = `${base}/login_mobile.php?u=${encodeURIComponent(loginPayload.username)}&p=${encodeURIComponent(password)}&ts=${nonce}`;
    const loginUrls = [
      // Prefer top-level alias first to avoid /auth path redirects on some networks.
      `${base}/login.php`,
      `${base}/auth/login.php`,
      // Cache-busting variants: some free-hosting edges intermittently cache redirect responses.
      `${base}/login.php?ts=${nonce}`,
      `${base}/auth/login.php?ts=${nonce}`,
      `${base}/login.php?mobile=1&ts=${nonce}`,
      `${base}/auth/login.php?mobile=1&ts=${nonce}`,
    ];
    try {
      try {
        // First attempt: GET-based mobile endpoint (avoids POST preflight/redirect edge cases).
        const m = await jsonFetch<{ user: ApiUser }>(mobileGetUrl, {
          method: "GET",
          headers: {
            "Accept": "application/json",
            "X-Requested-With": "XMLHttpRequest",
          },
        });
        if (m?.user) return m;
      } catch (mobileErr) {
        lastErr = mobileErr;
      }

      for (const loginUrl of loginUrls) {
        try {
          if (isNativeMobileRuntime()) {
            return await nativeHttpPostJson<{ user: ApiUser }>(loginUrl, loginPayload);
          }
          return await jsonFetch(loginUrl, {
            method: "POST",
            redirect: "follow",
            body,
          });
        } catch (inner) {
          lastErr = inner;
          const imsg = (inner instanceof Error ? inner.message : String(inner)).toLowerCase();
          const innerRetryable =
            imsg.includes("http 302") ||
            imsg.includes("http 301") ||
            imsg.includes("http 307") ||
            imsg.includes("http 308") ||
            imsg.includes("failed to fetch") ||
            imsg.includes("security check") ||
            imsg.includes("non-json");
          if (!innerRetryable) throw inner;
        }
      }
    } catch (e) {
      lastErr = e;
      const msg = (e instanceof Error ? e.message : String(e)).toLowerCase();
      const retryable =
        msg.includes("http 302") ||
        msg.includes("http 301") ||
        msg.includes("http 307") ||
        msg.includes("http 308") ||
        msg.includes("failed to fetch") ||
        msg.includes("security check") ||
        msg.includes("non-json");
      if (!retryable) break;
    }
  }

  const errMsg = lastErr instanceof Error ? lastErr.message : String(lastErr ?? "Login request failed.");
  throw new Error(`Login failed after trying API routes. Last error: ${errMsg}`);
}

export async function apiRegister(body: {
  username: string;
  name: string;
  phone: string;
  password: string;
  role: string;
  language_preference: Lang;
  age?: number | null;
  bhu_id?: number | null;
  verification_file?: File | null;
}): Promise<void> {
  if (body.verification_file) {
    const fd = new FormData();
    Object.entries(body).forEach(([k, v]) => {
      if (v !== null && v !== undefined) {
        if (k === 'verification_file' && v instanceof File) {
          fd.append(k, v);
        } else {
          fd.append(k, String(v));
        }
      }
    });
    
    const res = await fetch(`${API_BASE}/auth/register.php`, {
      method: "POST",
      mode: 'cors',
      headers: {
        "Accept": "application/json",
      },
      body: fd,
    });
    
    const text = await res.text();
    if (text.includes("aes.js") || text.includes("Checking your browser")) {
      throw new Error("Security check failed. Open the URL in your phone's browser first.");
    }

    if (!res.ok) {
      let data: any = {};
      try { data = JSON.parse(text); } catch(e) {}
      throw new Error(data.message || `Registration failed (HTTP ${res.status})`);
    }
    return;
  }

  await jsonFetch(`${API_BASE}/auth/register.php`, {
    method: "POST",
    body: JSON.stringify(body),
  });
}

export async function apiLogout(): Promise<void> {
  try {
    await fetch(`${API_BASE}/auth/logout.php`);
  } catch {
    /* ignore */
  }
}

export async function apiGetMedications(userId: number, lang: Lang): Promise<ApiMedication[]> {
  const data = await jsonFetch<ApiMedication[]>(`${API_BASE}/medications/get.php?user_id=${userId}&lang=${lang}`);
  return Array.isArray(data) ? data : [];
}

export async function apiPatientAddMedicationFromScan(payload: {
  user_id: number;
  name: string;
  times: string[];
  dosage?: string;
  frequency?: string;
  frequency_fil?: string;
  purpose_en?: string;
  purpose_fil?: string;
  precautions_en?: string;
  precautions_fil?: string;
  notes?: string;
  days_of_week?: string;
  schedule_notes?: string[];
}): Promise<{ medication_id: number }> {
  const r = await jsonFetch<{ message?: string; medication_id?: string | number }>(
    `${API_BASE}/medications/add_patient_scan.php`,
    {
      method: "POST",
      body: JSON.stringify(payload),
    },
  );
  return { medication_id: Number(r.medication_id ?? 0) };
}

export async function apiAddMedication(payload: {
  user_id: number;
  prescribed_by: number;
  name: string;
  dosage: string;
  frequency: string;
  purpose_fil: string;
  purpose_en: string;
  precautions_fil: string;
  precautions_en: string;
  notes?: string;
}): Promise<{ id: number }> {
  const r = await jsonFetch<{ message: string; id: string | number }>(`${API_BASE}/medications/add.php`, {
    method: "POST",
    body: JSON.stringify(payload),
  });
  return { id: Number(r.id) };
}

export async function apiDeleteMedication(userId: number, id: number): Promise<void> {
  await jsonFetch(`${API_BASE}/medications/delete.php`, {
    method: "POST",
    body: JSON.stringify({ user_id: userId, id }),
  });
}

export async function apiGetSchedules(userId: number): Promise<ApiSchedule[]> {
  const data = await jsonFetch<ApiSchedule[]>(`${API_BASE}/schedules/get.php?user_id=${userId}`);
  return Array.isArray(data) ? data : [];
}

export async function apiSetSchedule(payload: {
  user_id: number;
  medication_id: number;
  reminder_time: string;
  days_of_week: string;
}): Promise<void> {
  await jsonFetch(`${API_BASE}/schedules/set.php`, {
    method: "POST",
    body: JSON.stringify(payload),
  });
}

export async function apiConfirmAdherence(payload: {
  user_id: number;
  medication_id: number;
  scheduled_time: string;
  taken: boolean;
}): Promise<void> {
  await jsonFetch(`${API_BASE}/adherence/confirm.php`, {
    method: "POST",
    body: JSON.stringify(payload),
  });
}

export interface ApiAdherenceLogRow {
  id?: number;
  user_id?: number;
  medication_id: number;
  scheduled_time: string;
  taken: boolean | number | string;
  medication_name?: string;
  responded_at?: string | null;
}

/** Logs for a calendar day (scheduled_time or responded_at on that date). */
export async function apiGetAdherenceHistory(userId: number, dateYmd: string): Promise<ApiAdherenceLogRow[]> {
  const data = await jsonFetch<ApiAdherenceLogRow[]>(
    `${API_BASE}/adherence/history.php?user_id=${userId}&date=${encodeURIComponent(dateYmd)}`,
  );
  return Array.isArray(data) ? (data as ApiAdherenceLogRow[]) : [];
}

export async function apiUpdateLanguage(userId: number, lang: Lang): Promise<void> {
  await jsonFetch(`${API_BASE}/user/language.php`, {
    method: "PUT",
    body: JSON.stringify({ user_id: userId, language_preference: lang }),
  });
}

export async function apiOcrScan(body: { extracted_text: string; candidate_names: string[] }): Promise<{
  success: boolean;
  source?: string;
  data?: Record<string, string>;
  message?: string;
}> {
  try {
    const res = await fetch(`${API_BASE}/ocr/scan.php`, {
      method: "POST",
      mode: 'cors',
      headers: { "Content-Type": "application/json", Accept: "application/json" },
      body: JSON.stringify(body),
    });
    const text = await res.text();
    if (text.includes("aes.js") || text.includes("Checking your browser")) {
      return { success: false, message: "Security check failed. Open the URL in your phone's browser first." };
    }
    
    let data: any;
    try {
      data = JSON.parse(text);
    } catch (e) {
      console.error("Scanner JSON parse error:", text);
      return { success: false, message: `Server returned non-JSON response: ${text.substring(0, 100)}` };
    }

    return {
      success: Boolean(data.success),
      source: data.source,
      data: data.data,
      message: data.message,
    };
  } catch (e) {
    console.error("Scanner error:", e);
    return { success: false, message: `Failed to connect to scanner service: ${e instanceof Error ? e.message : String(e)}` };
  }
}

export async function apiMedicineAiChat(message: string, lang?: "en" | "fil"): Promise<{
  success: boolean;
  source?: string;
  reply?: string;
  sources?: { title: string; url: string }[];
  message?: string;
}> {
  return apiMedicineAiChatWithHistory(
    { message, messages: [] },
    lang,
  );
}

export async function apiMedicineAiChatWithHistory(
  payload: {
    message: string;
    messages: { role: "user" | "bot"; content: string }[];
  },
  lang?: "en" | "fil"
): Promise<{
  success: boolean;
  source?: string;
  reply?: string;
  sources?: { title: string; url: string }[];
  message?: string;
}> {
  const body = {
    message: payload.message.trim(),
    messages: payload.messages,
    lang: lang === "en" ? "en" : "fil",
  };
  const endpoints: string[] = [
    `${API_BASE}/medications/ai_chat.php`,
    // Compatibility fallback in case the file was uploaded one level higher.
    `${API_BASE}/ai_chat.php`,
  ];
  if (API_BASE.includes("pharsayo.atwebpages.com")) {
    endpoints.push(`${API_BASE}/medications/ai_chat.php`, `${API_BASE}/ai_chat.php`);
  }

  let lastError = "";
  for (const endpoint of Array.from(new Set(endpoints))) {
    try {
      const data = await jsonFetch<{
        success: boolean;
        source?: string;
        reply?: string;
        sources?: { title: string; url: string }[];
        message?: string;
      }>(endpoint, {
        method: "POST",
        body: JSON.stringify(body),
      });
      return {
        success: Boolean(data.success),
        source: data.source,
        reply: data.reply,
        sources: Array.isArray(data.sources) ? data.sources : undefined,
        message: data.message,
      };
    } catch (e) {
      lastError = e instanceof Error ? e.message : String(e);
      const lower = lastError.toLowerCase();
      const canTryNext =
        lower.includes("404") ||
        lower.includes("not found") ||
        lower.includes("failed to fetch");
      if (!canTryNext) break;
    }
  }

  try {
    throw new Error(lastError || "Chat endpoint is unreachable.");
  } catch (e) {
    const msg = e instanceof Error ? e.message : String(e);
    return {
      success: false,
      message:
        msg.toLowerCase().includes("failed to fetch") || msg.toLowerCase().includes("404")
          ? `Network Error: Failed to fetch chatbot endpoint. Tried "${API_BASE}/medications/ai_chat.php" and "${API_BASE}/ai_chat.php". Current Server URL: "${GET_API_BASE()}"`
          : msg,
    };
  }
}

export async function apiBarcodeScan(
  barcode: string,
  lang?: "en" | "fil",
): Promise<{
  success: boolean;
  source?: string;
  data?: Record<string, string>;
  message?: string;
}> {
  try {
    const res = await fetch(`${API_BASE}/ocr/scan.php`, {
      method: "POST",
      mode: 'cors',
      headers: { "Content-Type": "application/json", Accept: "application/json" },
      body: JSON.stringify({
        barcode: barcode.trim(),
        extracted_text: "",
        candidate_names: [],
        lang: lang === "en" ? "en" : "fil",
      }),
    });
    const text = await res.text();
    if (text.includes("aes.js") || text.includes("Checking your browser")) {
      return { success: false, message: "Security check failed. Open the URL in your phone's browser first." };
    }

    let data: any;
    try {
      data = JSON.parse(text);
    } catch (e) {
      console.error("Scanner JSON parse error:", text);
      return { success: false, message: `Server returned non-JSON response: ${text.substring(0, 100)}` };
    }

    return {
      success: Boolean(data.success),
      source: data.source,
      data: data.data,
      message: data.message,
    };
  } catch (e) {
    console.error("Scanner error:", e);
    return { success: false, message: `Failed to connect to scanner service: ${e instanceof Error ? e.message : String(e)}` };
  }
}

export async function apiBhuList(): Promise<{ id: number; name: string; municipality?: string }[]> {
  const data = await jsonFetch<{ id: number; name: string; municipality?: string }[]>(`${API_BASE}/bhu/list.php`);
  return Array.isArray(data) ? data : [];
}

export interface ApiAdminUserRow {
  id: number;
  name: string;
  username?: string;
  phone: string;
  role: string;
  account_status: string;
  age?: number | null;
  language_preference?: Lang;
  created_at?: string;
  /** Relative path e.g. uploads/verification/doc_xxx.jpg — for admin review only. */
  verification_file?: string | null;
}

/** Open in a new tab while logged in as admin (session-less; passes admin_id like other admin APIs). */
export function apiAdminVerificationFileUrl(adminId: number, userId: number): string {
  return `${API_BASE}/admin/verification_file.php?admin_id=${adminId}&user_id=${userId}`;
}

export async function apiAdminListUsers(adminId: number): Promise<ApiAdminUserRow[]> {
  const data = await jsonFetch<ApiAdminUserRow[]>(`${API_BASE}/admin/users_list.php?admin_id=${adminId}`);
  return Array.isArray(data) ? data : [];
}

export async function apiAdminUpdateUser(payload: {
  admin_id: number;
  user_id: number;
  name?: string;
  phone?: string;
  username?: string;
  age?: number | null;
  language_preference?: Lang;
  /** If set, replaces password (admin reset; min 6 characters). */
  new_password?: string;
}): Promise<{ user: ApiUser }> {
  return jsonFetch(`${API_BASE}/admin/update_user.php`, {
    method: "POST",
    body: JSON.stringify(payload),
  });
}

export async function apiAdminDeleteUser(adminId: number, userId: number): Promise<void> {
  await jsonFetch(`${API_BASE}/admin/delete_user.php`, {
    method: "POST",
    body: JSON.stringify({ admin_id: adminId, user_id: userId }),
  });
}

export async function apiAdminSetUserStatus(
  adminId: number,
  userId: number,
  status: "active" | "rejected" | "pending" | "inactive"
): Promise<void> {
  await jsonFetch(`${API_BASE}/admin/set_user_status.php`, {
    method: "POST",
    body: JSON.stringify({ admin_id: adminId, user_id: userId, status }),
  });
}

export async function apiAdminLinkDoctorPatient(
  adminId: number,
  doctorUsername: string,
  patientUsername: string
): Promise<void> {
  await jsonFetch(`${API_BASE}/admin/doctor_patient_link.php`, {
    method: "POST",
    body: JSON.stringify({
      admin_id: adminId,
      doctor_username: doctorUsername.trim(),
      patient_username: patientUsername.trim(),
    }),
  });
}

export async function apiAdminUnlinkDoctorPatient(
  adminId: number,
  doctorUsername: string,
  patientUsername: string
): Promise<void> {
  await jsonFetch(`${API_BASE}/admin/doctor_patient_unlink.php`, {
    method: "POST",
    body: JSON.stringify({
      admin_id: adminId,
      doctor_username: doctorUsername.trim(),
      patient_username: patientUsername.trim(),
    }),
  });
}

export interface ApiAdminScheduleRow {
  schedule_id: number;
  reminder_time: string;
  days_of_week: string;
  start_date?: string | null;
  end_date?: string | null;
  schedule_notes?: string;
  medication_id: number;
  medicine_name: string;
  medication_notes?: string;
  patient_id: number;
  patient_name: string;
  patient_username?: string;
  doctor_id?: number | null;
  doctor_name?: string | null;
  doctor_username?: string | null;
}

export async function apiAdminListSchedules(adminId: number): Promise<ApiAdminScheduleRow[]> {
  const url = `${API_BASE}/schedules/list_all.php?admin_id=${adminId}`;
  const res = await fetch(url, { method: "GET", cache: "no-cache" });
  const text = await res.text();
  let data: unknown = [];
  try {
    data = JSON.parse(text);
  } catch {
    throw new Error(`Schedules endpoint returned non-JSON response.`);
  }
  if (!res.ok) {
    const msg = typeof data === "object" && data && "message" in data ? String((data as { message?: string }).message || "") : "";
    throw new Error(msg || "Failed to load schedules");
  }
  return Array.isArray(data) ? (data as ApiAdminScheduleRow[]) : [];
}

export async function apiScheduleUpdate(payload: {
  actor_id: number;
  actor_role: "admin" | "doctor";
  schedule_id: number;
  reminder_time?: string;
  days_of_week?: string;
  start_date?: string | null;
  end_date?: string | null;
  notes?: string;
}): Promise<void> {
  await jsonFetch(`${API_BASE}/schedules/update.php`, {
    method: "POST",
    body: JSON.stringify(payload),
  });
}

export async function apiScheduleDelete(payload: {
  actor_id: number;
  actor_role: "admin" | "doctor";
  schedule_id: number;
}): Promise<void> {
  await jsonFetch(`${API_BASE}/schedules/delete.php`, {
    method: "POST",
    body: JSON.stringify(payload),
  });
}

export interface ApiDoctorPatientRow {
  id: number;
  name: string;
  username?: string;
  age: number | null;
  phone: string;
  language_preference: string;
  account_status: string;
}

export async function apiDoctorPatients(doctorId: number): Promise<ApiDoctorPatientRow[]> {
  const res = await fetch(`${API_BASE}/doctor/patients.php?doctor_id=${doctorId}`);
  if (!res.ok) throw new Error("Failed to load patients");
  const data = await res.json();
  return Array.isArray(data) ? data : [];
}

export interface ApiDoctorLinkablePatientRow {
  id: number;
  name: string;
  username?: string;
  phone: string;
}

export async function apiDoctorSearchLinkablePatients(
  doctorId: number,
  query: string
): Promise<ApiDoctorLinkablePatientRow[]> {
  const q = query.trim();
  if (q.length < 2) return [];
  const res = await fetch(
    `${API_BASE}/doctor/search_patients.php?doctor_id=${doctorId}&q=${encodeURIComponent(q)}`
  );
  if (!res.ok) throw new Error("Patient search failed");
  const data = await res.json();
  return Array.isArray(data) ? (data as ApiDoctorLinkablePatientRow[]) : [];
}

export async function apiDoctorLinkPatient(
  doctorId: number,
  opts: { patient_username?: string; patient_phone?: string }
): Promise<void> {
  await jsonFetch(`${API_BASE}/doctor/link_patient.php`, {
    method: "POST",
    body: JSON.stringify({
      doctor_id: doctorId,
      patient_username: (opts.patient_username ?? "").trim(),
      patient_phone: (opts.patient_phone ?? "").trim(),
    }),
  });
}

/** Today's intake status for a schedule row (server date + days_of_week). */
export type DoctorAdherenceToday =
  | "not_scheduled_today"
  | "pending"
  | "marked_not_taken"
  | "taken_on_time"
  | "taken_late"
  | "taken_time_unknown";

export interface ApiDoctorScheduleRow {
  schedule_id: number;
  reminder_time: string;
  days_of_week: string;
  start_date?: string | null;
  end_date?: string | null;
  schedule_notes?: string;
  medication_id: number;
  medicine_name: string;
  patient_name: string;
  patient_username?: string;
  adherence_today: DoctorAdherenceToday;
  /** When the patient confirmed "taken", e.g. "2:35 AM PHT" (Philippine Time, 12-hour). */
  adherence_confirmed_time?: string | null;
}

export async function apiDoctorSchedulesFlat(doctorId: number): Promise<ApiDoctorScheduleRow[]> {
  const res = await fetch(`${API_BASE}/doctor/schedules_flat.php?doctor_id=${doctorId}`);
  if (!res.ok) throw new Error("Failed to load schedules");
  const data = await res.json();
  if (!Array.isArray(data)) return [];
  return data.map((row: Record<string, unknown>) => ({
    ...(row as unknown as ApiDoctorScheduleRow),
    adherence_today: (row.adherence_today as DoctorAdherenceToday) || "pending",
    adherence_confirmed_time:
      typeof row.adherence_confirmed_time === "string" && row.adherence_confirmed_time.trim() !== ""
        ? row.adherence_confirmed_time.trim()
        : null,
  }));
}

export async function apiDoctorPrescribe(payload: {
  doctor_id: number;
  patient_user_id: number;
  name: string;
  times: string[];
  dosage?: string;
  /** e.g. Mon,Tue,Wed,Thu,Fri,Sat,Sun */
  days_of_week?: string;
  start_date?: string | null;
  end_date?: string | null;
  /** Patient-visible frequency text; defaults to `days_of_week` when omitted. */
  frequency?: string;
  frequency_fil?: string;
  purpose_en?: string;
  purpose_fil?: string;
  precautions_en?: string;
  precautions_fil?: string;
  medication_notes?: string;
  schedule_notes?: string[];
}): Promise<{ medication_id: number }> {
  const days = (payload.days_of_week ?? "").trim() || "Mon,Tue,Wed,Thu,Fri,Sat,Sun";
  const freq = (payload.frequency ?? "").trim() || days;
  const freqFil = (payload.frequency_fil ?? "").trim() || freq;
  const body = {
    doctor_id: payload.doctor_id,
    patient_user_id: payload.patient_user_id,
    name: payload.name,
    times: payload.times,
    dosage: payload.dosage ?? "",
    days_of_week: days,
    start_date: payload.start_date ?? null,
    end_date: payload.end_date ?? null,
    frequency: freq,
    frequency_fil: freqFil,
    purpose_en: payload.purpose_en ?? "",
    purpose_fil: payload.purpose_fil ?? "",
    precautions_en: payload.precautions_en ?? "",
    precautions_fil: payload.precautions_fil ?? "",
    medication_notes: payload.medication_notes ?? "",
    schedule_notes: payload.schedule_notes ?? [],
  };
  const r = await jsonFetch<{ message?: string; medication_id?: string | number }>(`${API_BASE}/doctor/prescribe.php`, {
    method: "POST",
    body: JSON.stringify(body),
  });
  return { medication_id: Number(r.medication_id ?? 0) };
}

export async function apiUpdateProfile(payload: {
  user_id: number;
  name?: string;
  phone?: string;
  age?: number | null;
  language_preference?: Lang;
  username?: string;
}): Promise<{ user: ApiUser }> {
  return jsonFetch(`${API_BASE}/user/profile.php`, {
    method: "PUT",
    body: JSON.stringify(payload),
  });
}

export async function apiChangePassword(payload: {
  user_id: number;
  current_password: string;
  new_password: string;
}): Promise<void> {
  await jsonFetch(`${API_BASE}/user/password.php`, {
    method: "POST",
    body: JSON.stringify(payload),
  });
}
