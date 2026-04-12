import { useCallback, useEffect, useMemo, useState } from "react";
import { ChevronsUpDown, FileText, ShieldCheck, RefreshCw } from "lucide-react";
import { useAuth } from "@/contexts/auth-context";
import {
  apiAdminLinkDoctorPatient,
  apiAdminListSchedules,
  apiAdminListUsers,
  apiAdminSetUserStatus,
  apiAdminUnlinkDoctorPatient,
  apiAdminUpdateUser,
  apiAdminDeleteUser,
  apiAdminVerificationFileUrl,
  apiScheduleDelete,
  apiScheduleUpdate,
  type ApiAdminScheduleRow,
  type ApiAdminUserRow,
  type Lang,
} from "@/lib/api";
import { t } from "@/lib/i18n";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs";
import {
  Dialog,
  DialogContent,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from "@/components/ui/dialog";
import { Textarea } from "@/components/ui/textarea";
import {
  Command,
  CommandEmpty,
  CommandGroup,
  CommandInput,
  CommandItem,
  CommandList,
} from "@/components/ui/command";
import { Popover, PopoverContent, PopoverTrigger } from "@/components/ui/popover";
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select";
import {
  AlertDialog,
  AlertDialogCancel,
  AlertDialogContent,
  AlertDialogDescription,
  AlertDialogFooter,
  AlertDialogHeader,
  AlertDialogTitle,
} from "@/components/ui/alert-dialog";
import { toast } from "sonner";

/** HH:MM for API / edit (24-hour, zero-padded). */
function formatScheduleTime(raw: string): string {
  const s = (raw || "").trim().slice(0, 8);
  const m = s.match(/^(\d{1,2}):(\d{2})/);
  if (!m) return (raw || "").slice(0, 5);
  return `${m[1].padStart(2, "0")}:${m[2].padStart(2, "0")}`;
}

/** 12-hour clock for list display only. */
function formatScheduleTime12h(raw: string): string {
  const s = (raw || "").trim().slice(0, 8);
  const m = s.match(/^(\d{1,2}):(\d{2})/);
  if (!m) return (raw || "").slice(0, 5);
  let h = parseInt(m[1], 10);
  const min = m[2].padStart(2, "0");
  const ap = h >= 12 ? "PM" : "AM";
  h = h % 12;
  if (h === 0) h = 12;
  return `${h}:${min} ${ap}`;
}

function AdminUserCombobox({
  users,
  valueUsername,
  onChangeUsername,
  label,
  searchPlaceholder,
  emptyLabel,
  idleLabel,
}: {
  users: ApiAdminUserRow[];
  valueUsername: string;
  onChangeUsername: (username: string) => void;
  label: string;
  searchPlaceholder: string;
  emptyLabel: string;
  idleLabel: string;
}) {
  const [open, setOpen] = useState(false);
  const selected = users.find((u) => (u.username || "").trim() === valueUsername.trim());

  return (
    <div className="space-y-2">
      <Label>{label}</Label>
      <Popover open={open} onOpenChange={setOpen}>
        <PopoverTrigger asChild>
          <Button
            type="button"
            variant="outline"
            role="combobox"
            aria-expanded={open}
            className="w-full justify-between rounded-xl h-11 font-normal"
          >
            <span className="truncate text-left">
              {selected ? (
                <>
                  <span className="font-mono">@{selected.username}</span>
                  <span className="text-muted-foreground"> — {selected.name}</span>
                </>
              ) : (
                <span className="text-muted-foreground">{idleLabel}</span>
              )}
            </span>
            <ChevronsUpDown className="ml-2 h-4 w-4 shrink-0 opacity-50" />
          </Button>
        </PopoverTrigger>
        <PopoverContent className="w-[var(--radix-popover-trigger-width)] min-w-[min(100%,20rem)] p-0" align="start">
          <Command>
            <CommandInput placeholder={searchPlaceholder} />
            <CommandList>
              <CommandEmpty>{emptyLabel}</CommandEmpty>
              <CommandGroup>
                {users.map((u) => (
                  <CommandItem
                    key={u.id}
                    value={`${u.username ?? ""} ${u.name} ${u.phone} ${u.id}`}
                    onSelect={() => {
                      onChangeUsername((u.username || "").trim());
                      setOpen(false);
                    }}
                  >
                    <span className="font-mono">@{u.username || "—"}</span>
                    <span className="text-muted-foreground truncate"> — {u.name}</span>
                    <span className="ml-auto shrink-0 pl-2 text-xs text-muted-foreground">{u.phone}</span>
                  </CommandItem>
                ))}
              </CommandGroup>
            </CommandList>
          </Command>
        </PopoverContent>
      </Popover>
    </div>
  );
}

export default function AdminDashboard() {
  const { user, logout, lang, applyUser } = useAuth();
  const [rows, setRows] = useState<ApiAdminUserRow[]>([]);
  const [schedules, setSchedules] = useState<ApiAdminScheduleRow[]>([]);
  const [loading, setLoading] = useState(true);
  const [schedLoading, setSchedLoading] = useState(false);
  const [busyId, setBusyId] = useState<number | null>(null);

  const [docU, setDocU] = useState("");
  const [patU, setPatU] = useState("");
  const [linkBusy, setLinkBusy] = useState(false);

  const [schedEditRow, setSchedEditRow] = useState<ApiAdminScheduleRow | null>(null);
  const [schedEditTime, setSchedEditTime] = useState("");
  const [schedEditDays, setSchedEditDays] = useState("");
  const [schedEditNotes, setSchedEditNotes] = useState("");
  const [schedEditBusy, setSchedEditBusy] = useState(false);

  const [accountEdit, setAccountEdit] = useState<ApiAdminUserRow | null>(null);
  const [acctName, setAcctName] = useState("");
  const [acctUsername, setAcctUsername] = useState("");
  const [acctPhone, setAcctPhone] = useState("");
  const [acctAge, setAcctAge] = useState("");
  const [acctLang, setAcctLang] = useState<Lang>("fil");
  const [acctPassword, setAcctPassword] = useState("");
  const [acctBusy, setAcctBusy] = useState(false);

  const [deleteTarget, setDeleteTarget] = useState<ApiAdminUserRow | null>(null);
  const [deleteBusy, setDeleteBusy] = useState(false);

  const [verificationPreview, setVerificationPreview] = useState<{
    row: ApiAdminUserRow;
    url: string;
  } | null>(null);

  const doctorsPickList = useMemo(
    () => rows.filter((r) => r.role === "doctor" && (r.username || "").trim() !== ""),
    [rows],
  );
  const patientsPickList = useMemo(
    () => rows.filter((r) => r.role === "patient" && (r.username || "").trim() !== ""),
    [rows],
  );

  const loadUsers = useCallback(async () => {
    if (!user?.id) return;
    setLoading(true);
    try {
      const list = await apiAdminListUsers(user.id);
      setRows(list);
    } catch (e) {
      toast.error(e instanceof Error ? e.message : "Failed to load accounts");
      setRows([]);
    } finally {
      setLoading(false);
    }
  }, [user?.id]);

  const loadSchedules = useCallback(async () => {
    if (!user?.id) return;
    setSchedLoading(true);
    try {
      const list = await apiAdminListSchedules(user.id);
      setSchedules(list);
    } catch (e) {
      toast.error(e instanceof Error ? e.message : "Failed to load schedules");
      setSchedules([]);
    } finally {
      setSchedLoading(false);
    }
  }, [user?.id]);

  useEffect(() => {
    void loadUsers();
  }, [loadUsers]);

  useEffect(() => {
    void loadSchedules();
  }, [loadSchedules]);

  const setStatus = async (targetId: number, status: "active" | "rejected" | "inactive") => {
    if (!user?.id) return;
    setBusyId(targetId);
    try {
      await apiAdminSetUserStatus(user.id, targetId, status);
      toast.success("Updated.");
      await loadUsers();
    } catch (e) {
      toast.error(e instanceof Error ? e.message : "Update failed");
    } finally {
      setBusyId(null);
    }
  };

  const confirmDeleteUser = async () => {
    if (!user?.id || !deleteTarget) return;
    setDeleteBusy(true);
    try {
      await apiAdminDeleteUser(user.id, deleteTarget.id);
      toast.success(lang === "en" ? "Account deleted." : "Nabura ang account.");
      setDeleteTarget(null);
      await loadUsers();
      await loadSchedules();
    } catch (e) {
      toast.error(e instanceof Error ? e.message : "Delete failed");
    } finally {
      setDeleteBusy(false);
    }
  };

  const openAccountEdit = (r: ApiAdminUserRow) => {
    setAccountEdit(r);
    setAcctName(r.name || "");
    setAcctUsername((r.username || "").trim());
    setAcctPhone(r.phone || "");
    setAcctAge(r.age != null ? String(r.age) : "");
    setAcctLang(r.language_preference === "en" ? "en" : "fil");
    setAcctPassword("");
  };

  const saveAccountEdit = async () => {
    if (!user?.id || !accountEdit) return;
    const ageTrim = acctAge.trim();
    let agePayload: number | null;
    if (ageTrim === "") {
      agePayload = null;
    } else {
      const n = Number.parseInt(ageTrim, 10);
      if (Number.isNaN(n) || n < 0 || n > 150) {
        toast.error(t(lang, "admin.invalidAge"));
        return;
      }
      agePayload = n;
    }
    setAcctBusy(true);
    try {
      const { user: updated } = await apiAdminUpdateUser({
        admin_id: user.id,
        user_id: accountEdit.id,
        name: acctName.trim(),
        phone: acctPhone.trim(),
        username: acctUsername.trim(),
        language_preference: acctLang,
        age: agePayload,
        new_password: acctPassword.trim() || undefined,
      });
      toast.success("Updated.");
      if (updated.id === user.id) {
        applyUser(updated);
      }
      setAccountEdit(null);
      await loadUsers();
    } catch (e) {
      toast.error(e instanceof Error ? e.message : "Update failed");
    } finally {
      setAcctBusy(false);
    }
  };

  const onLink = async (unlink: boolean) => {
    if (!user?.id || !docU.trim() || !patU.trim()) {
      toast.message(t(lang, "admin.chooseBothLink"));
      return;
    }
    setLinkBusy(true);
    try {
      if (unlink) {
        await apiAdminUnlinkDoctorPatient(user.id, docU.trim(), patU.trim());
        toast.success("Unlinked.");
      } else {
        await apiAdminLinkDoctorPatient(user.id, docU.trim(), patU.trim());
        toast.success("Linked.");
      }
      setDocU("");
      setPatU("");
      await loadSchedules();
    } catch (e) {
      toast.error(e instanceof Error ? e.message : "Request failed");
    } finally {
      setLinkBusy(false);
    }
  };

  const openSchedEdit = (r: ApiAdminScheduleRow) => {
    setSchedEditRow(r);
    setSchedEditTime(formatScheduleTime(r.reminder_time));
    setSchedEditDays(r.days_of_week || "");
    setSchedEditNotes(r.schedule_notes ?? "");
  };

  const saveSchedEdit = async () => {
    if (!user?.id || !schedEditRow) return;
    setSchedEditBusy(true);
    try {
      await apiScheduleUpdate({
        actor_id: user.id,
        actor_role: "admin",
        schedule_id: schedEditRow.schedule_id,
        reminder_time: schedEditTime.trim(),
        days_of_week: schedEditDays.trim(),
        notes: schedEditNotes,
      });
      toast.success("Schedule saved.");
      setSchedEditRow(null);
      await loadSchedules();
    } catch (e) {
      toast.error(e instanceof Error ? e.message : "Save failed");
    } finally {
      setSchedEditBusy(false);
    }
  };

  const deleteSchedule = async (id: number) => {
    if (!user?.id) return;
    setBusyId(id);
    try {
      await apiScheduleDelete({ actor_id: user.id, actor_role: "admin", schedule_id: id });
      toast.success("Deleted.");
      await loadSchedules();
    } catch (e) {
      toast.error(e instanceof Error ? e.message : "Delete failed");
    } finally {
      setBusyId(null);
    }
  };

  return (
    <div className="min-h-screen bg-background px-4 py-8 pb-24">
      <div className="max-w-3xl mx-auto space-y-6">
        <div className="flex flex-wrap items-center justify-between gap-3">
          <div className="flex items-center gap-3">
            <div className="w-12 h-12 rounded-2xl bg-primary/15 flex items-center justify-center text-primary">
              <ShieldCheck className="w-7 h-7" />
            </div>
            <div>
              <h1 className="text-xl font-bold text-foreground">{t(lang, "admin.title")}</h1>
              <p className="text-sm text-muted-foreground">{t(lang, "admin.verifyAccounts")} · System Admin</p>
            </div>
          </div>
          <Button variant="outline" size="sm" className="rounded-xl" onClick={() => logout()}>
            Sign out
          </Button>
        </div>

        <Tabs defaultValue="accounts" className="w-full">
          <TabsList className="grid w-full grid-cols-3 h-auto rounded-xl">
            <TabsTrigger value="accounts" className="rounded-lg text-xs sm:text-sm">
              {t(lang, "admin.tabAccounts")}
            </TabsTrigger>
            <TabsTrigger value="links" className="rounded-lg text-xs sm:text-sm">
              {t(lang, "admin.tabLinks")}
            </TabsTrigger>
            <TabsTrigger value="schedules" className="rounded-lg text-xs sm:text-sm">
              {t(lang, "admin.tabSchedules")}
            </TabsTrigger>
          </TabsList>

          <TabsContent value="accounts" className="mt-4">
            <div className="rounded-2xl border border-border bg-card overflow-hidden">
              <div className="px-4 py-3 border-b border-border bg-muted/40 flex items-center justify-between">
                <h2 className="font-semibold text-foreground text-sm">{t(lang, "admin.allAccounts")}</h2>
                <Button
                  variant="ghost"
                  size="sm"
                  className="rounded-lg h-8 gap-2 text-xs"
                  onClick={() => void loadUsers()}
                  disabled={loading}
                >
                  <RefreshCw className={`w-3.5 h-3.5 ${loading ? "animate-spin" : ""}`} />
                  Refresh
                </Button>
              </div>
              {loading ? (
                <p className="p-6 text-sm text-muted-foreground">{t(lang, "admin.loading")}</p>
              ) : rows.length === 0 ? (
                <p className="p-6 text-sm text-muted-foreground">{t(lang, "admin.emptyUsers")}</p>
              ) : (
                <div className="divide-y divide-border max-h-[65vh] overflow-y-auto">
                  {rows.map((r) => (
                    <div key={r.id} className="px-4 py-4 flex flex-col gap-3">
                      <div className="min-w-0 w-full space-y-0.5">
                        <p className="font-medium text-foreground break-words">{r.name}</p>
                        <p className="text-xs text-muted-foreground font-mono break-all">
                          {r.username ? `@${r.username} · ` : ""}
                          {r.phone}
                        </p>
                        <p className="text-xs text-muted-foreground">
                          {t(lang, "admin.roleStatus")}: <span className="text-foreground">{r.role}</span> ·{" "}
                          <span className="text-foreground">{r.account_status}</span>
                        </p>
                      </div>
                      <div className="grid grid-cols-2 sm:grid-cols-3 gap-2 w-full">
                        <Button
                          size="sm"
                          variant="outline"
                          className="rounded-lg h-9 w-full"
                          onClick={() => openAccountEdit(r)}
                        >
                          {t(lang, "admin.edit")}
                        </Button>
                        {r.id !== user?.id && (
                          <Button
                            size="sm"
                            variant="outline"
                            className="rounded-lg h-9 w-full border-destructive/60 text-destructive hover:bg-destructive/10"
                            disabled={busyId === r.id}
                            onClick={() => setDeleteTarget(r)}
                          >
                            {t(lang, "admin.deleteAccount")}
                          </Button>
                        )}
                        {r.role === "doctor" && (r.verification_file || "").trim() !== "" && user?.id && (
                          <Button
                            size="sm"
                            variant="secondary"
                            className="rounded-lg h-9 gap-1 w-full col-span-2 sm:col-span-1"
                            type="button"
                            onClick={() =>
                              setVerificationPreview({
                                row: r,
                                url: apiAdminVerificationFileUrl(user.id, r.id),
                              })
                            }
                          >
                            <FileText className="w-3.5 h-3.5 shrink-0" />
                            <span className="truncate">{t(lang, "admin.viewVerification")}</span>
                          </Button>
                        )}
                        {r.account_status !== "active" && (
                          <Button
                            size="sm"
                            className="rounded-lg h-9 w-full"
                            disabled={busyId === r.id}
                            onClick={() => void setStatus(r.id, "active")}
                          >
                            {t(lang, "admin.approve")}
                          </Button>
                        )}
                        {r.account_status !== "rejected" && r.role === "doctor" && (
                          <Button
                            size="sm"
                            variant="destructive"
                            className="rounded-lg h-9 w-full"
                            disabled={busyId === r.id}
                            onClick={() => void setStatus(r.id, "rejected")}
                          >
                            {t(lang, "admin.reject")}
                          </Button>
                        )}
                        {r.account_status === "active" && r.role !== "admin" && (
                          <Button
                            size="sm"
                            variant="outline"
                            className="rounded-lg h-9 w-full"
                            disabled={busyId === r.id}
                            onClick={() => void setStatus(r.id, "inactive")}
                          >
                            {t(lang, "admin.deactivate")}
                          </Button>
                        )}
                      </div>
                    </div>
                  ))}
                </div>
              )}
            </div>
          </TabsContent>

          <TabsContent value="links" className="mt-4 space-y-3">
            <div className="flex items-center justify-between">
              <p className="text-sm text-muted-foreground">{t(lang, "admin.linkSection")}</p>
              <Button
                variant="ghost"
                size="sm"
                className="rounded-lg h-8 gap-2 text-xs"
                onClick={() => void loadUsers()}
                disabled={loading}
              >
                <RefreshCw className={`w-3.5 h-3.5 ${loading ? "animate-spin" : ""}`} />
                Refresh Users
              </Button>
            </div>
            <div className="rounded-2xl border border-border bg-card p-4 space-y-3">
              <AdminUserCombobox
                users={doctorsPickList}
                valueUsername={docU}
                onChangeUsername={setDocU}
                label={t(lang, "admin.doctorUsername")}
                searchPlaceholder={t(lang, "admin.searchUsersPlaceholder")}
                emptyLabel={t(lang, "admin.noUserMatch")}
                idleLabel={t(lang, "admin.doctorUsername")}
              />
              <AdminUserCombobox
                users={patientsPickList}
                valueUsername={patU}
                onChangeUsername={setPatU}
                label={t(lang, "admin.patientUsername")}
                searchPlaceholder={t(lang, "admin.searchUsersPlaceholder")}
                emptyLabel={t(lang, "admin.noUserMatch")}
                idleLabel={t(lang, "admin.patientUsername")}
              />
              <div className="flex gap-2">
                <Button className="rounded-xl" disabled={linkBusy} onClick={() => void onLink(false)}>
                  {t(lang, "admin.linkPair")}
                </Button>
                <Button variant="outline" className="rounded-xl" disabled={linkBusy} onClick={() => void onLink(true)}>
                  {t(lang, "admin.unlinkPair")}
                </Button>
              </div>
            </div>
          </TabsContent>

          <TabsContent value="schedules" className="mt-4 space-y-3">
            <div className="flex items-center justify-between">
              <h2 className="font-semibold text-foreground text-sm">{t(lang, "admin.schedulesAll")}</h2>
              <Button
                variant="ghost"
                size="sm"
                className="rounded-lg h-8 gap-2 text-xs"
                onClick={() => void loadSchedules()}
                disabled={schedLoading}
              >
                <RefreshCw className={`w-3.5 h-3.5 ${schedLoading ? "animate-spin" : ""}`} />
                Refresh
              </Button>
            </div>
            {schedLoading ? (
              <p className="text-sm text-muted-foreground">{t(lang, "admin.loading")}</p>
            ) : schedules.length === 0 ? (
              <p className="text-sm text-muted-foreground">No rows.</p>
            ) : (
              <div className="rounded-2xl border border-border divide-y max-h-[60vh] overflow-y-auto">
                {schedules.map((r) => (
                  <div key={r.schedule_id} className="p-4 space-y-2 text-sm">
                    <div className="flex flex-wrap justify-between gap-2">
                      <div>
                        <p className="font-medium text-foreground">{r.patient_name}</p>
                        <p className="text-xs text-muted-foreground">
                          {r.medicine_name} · {formatScheduleTime12h(r.reminder_time)}
                        </p>
                        {r.schedule_notes ? (
                          <p className="text-xs text-muted-foreground mt-1">{r.schedule_notes}</p>
                        ) : null}
                      </div>
                      <div className="flex gap-2">
                        <Button size="sm" variant="outline" className="rounded-lg h-8" onClick={() => openSchedEdit(r)}>
                          {t(lang, "admin.edit")}
                        </Button>
                        <Button
                          size="sm"
                          variant="destructive"
                          className="rounded-lg h-8"
                          disabled={busyId === r.schedule_id}
                          onClick={() => void deleteSchedule(r.schedule_id)}
                        >
                          {t(lang, "admin.delete")}
                        </Button>
                      </div>
                    </div>
                  </div>
                ))}
              </div>
            )}
          </TabsContent>
        </Tabs>
      </div>

      <Dialog open={!!accountEdit} onOpenChange={(o) => !o && setAccountEdit(null)}>
        <DialogContent className="sm:max-w-md rounded-2xl max-h-[90vh] overflow-y-auto">
          <DialogHeader>
            <DialogTitle>{t(lang, "admin.editUserTitle")}</DialogTitle>
          </DialogHeader>
          <div className="space-y-3 py-2">
            <div className="space-y-2">
              <Label>{t(lang, "profile.fullName")}</Label>
              <Input className="rounded-xl" value={acctName} onChange={(e) => setAcctName(e.target.value)} />
            </div>
            <div className="space-y-2">
              <Label>{t(lang, "profile.username")}</Label>
              <Input className="rounded-xl font-mono" value={acctUsername} onChange={(e) => setAcctUsername(e.target.value)} />
            </div>
            <div className="space-y-2">
              <Label>{t(lang, "profile.phone")}</Label>
              <Input className="rounded-xl" value={acctPhone} onChange={(e) => setAcctPhone(e.target.value)} />
            </div>
            <div className="space-y-2">
              <Label>{t(lang, "profile.age")}</Label>
              <Input
                className="rounded-xl"
                inputMode="numeric"
                value={acctAge}
                onChange={(e) => setAcctAge(e.target.value)}
                placeholder="—"
              />
            </div>
            <div className="space-y-2">
              <Label>{t(lang, "profile.language")}</Label>
              <Select value={acctLang} onValueChange={(v) => setAcctLang(v as Lang)}>
                <SelectTrigger className="rounded-xl">
                  <SelectValue />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="en">English</SelectItem>
                  <SelectItem value="fil">Filipino</SelectItem>
                </SelectContent>
              </Select>
            </div>
            <div className="space-y-2">
              <Label>{t(lang, "admin.newPasswordOptional")}</Label>
              <Input
                type="password"
                autoComplete="new-password"
                className="rounded-xl"
                value={acctPassword}
                onChange={(e) => setAcctPassword(e.target.value)}
              />
            </div>
          </div>
          <DialogFooter className="gap-2">
            <Button variant="outline" className="rounded-xl" onClick={() => setAccountEdit(null)}>
              {t(lang, "admin.cancel")}
            </Button>
            <Button className="rounded-xl" disabled={acctBusy} onClick={() => void saveAccountEdit()}>
              {t(lang, "admin.save")}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>

      <Dialog open={!!schedEditRow} onOpenChange={(o) => !o && setSchedEditRow(null)}>
        <DialogContent className="sm:max-w-md rounded-2xl">
          <DialogHeader>
            <DialogTitle>{t(lang, "admin.editSchedule")}</DialogTitle>
          </DialogHeader>
          <div className="space-y-3 py-2">
            <div className="space-y-2">
              <Label>{t(lang, "admin.time")}</Label>
              <Input
                className="rounded-xl font-mono"
                value={schedEditTime}
                onChange={(e) => setSchedEditTime(e.target.value)}
              />
            </div>
            <div className="space-y-2">
              <Label>{t(lang, "admin.days")}</Label>
              <Input
                className="rounded-xl font-mono text-sm"
                value={schedEditDays}
                onChange={(e) => setSchedEditDays(e.target.value)}
              />
            </div>
            <div className="space-y-2">
              <Label>{t(lang, "admin.notes")}</Label>
              <Textarea
                className="rounded-xl min-h-[80px]"
                value={schedEditNotes}
                onChange={(e) => setSchedEditNotes(e.target.value)}
              />
            </div>
          </div>
          <DialogFooter className="gap-2">
            <Button variant="outline" className="rounded-xl" onClick={() => setSchedEditRow(null)}>
              {t(lang, "admin.cancel")}
            </Button>
            <Button className="rounded-xl" disabled={schedEditBusy} onClick={() => void saveSchedEdit()}>
              {t(lang, "admin.save")}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>

      <Dialog
        open={!!verificationPreview}
        onOpenChange={(o) => {
          if (!o) setVerificationPreview(null);
        }}
      >
        <DialogContent className="sm:max-w-2xl rounded-2xl max-h-[90vh] flex flex-col gap-3">
          <DialogHeader>
            <DialogTitle className="text-left break-words">
              {t(lang, "admin.viewVerification")}
              {verificationPreview ? (
                <span className="block text-sm font-normal text-muted-foreground mt-1">
                  {verificationPreview.row.name}
                  {verificationPreview.row.username
                    ? ` · @${verificationPreview.row.username}`
                    : ""}
                </span>
              ) : null}
            </DialogTitle>
          </DialogHeader>
          {verificationPreview ? (
            <>
              <div className="rounded-xl border border-border bg-muted/20 overflow-hidden min-h-[50vh] max-h-[65vh] flex-1">
                <iframe
                  title={t(lang, "admin.viewVerification")}
                  src={verificationPreview.url}
                  className="w-full h-full min-h-[50vh] border-0 bg-background"
                />
              </div>
              <DialogFooter className="flex-col sm:flex-row gap-2 sm:justify-between">
                <Button variant="outline" className="rounded-xl w-full sm:w-auto" onClick={() => setVerificationPreview(null)}>
                  {t(lang, "admin.cancel")}
                </Button>
                <Button
                  className="rounded-xl w-full sm:w-auto"
                  onClick={() =>
                    window.open(verificationPreview.url, "_blank", "noopener,noreferrer")
                  }
                >
                  {t(lang, "admin.openVerificationNewTab")}
                </Button>
              </DialogFooter>
            </>
          ) : null}
        </DialogContent>
      </Dialog>

      <AlertDialog open={!!deleteTarget} onOpenChange={(open) => !open && setDeleteTarget(null)}>
        <AlertDialogContent className="rounded-2xl">
          <AlertDialogHeader>
            <AlertDialogTitle>{t(lang, "admin.deleteAccountConfirm")}</AlertDialogTitle>
            <AlertDialogDescription>{t(lang, "admin.deleteAccountWarn")}</AlertDialogDescription>
          </AlertDialogHeader>
          <AlertDialogFooter>
            <AlertDialogCancel className="rounded-xl">{t(lang, "admin.cancel")}</AlertDialogCancel>
            <Button
              variant="destructive"
              className="rounded-xl"
              disabled={deleteBusy}
              onClick={() => void confirmDeleteUser()}
            >
              {t(lang, "admin.delete")}
            </Button>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>
    </div>
  );
}
