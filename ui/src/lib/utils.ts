import { clsx, type ClassValue } from "clsx";
import { twMerge } from "tailwind-merge";

export function cn(...inputs: ClassValue[]) {
  return twMerge(clsx(inputs));
}

/** 12-hour clock for list display only. (HH:MM -> H:MM AM/PM) */
export function formatScheduleTime12h(raw: string): string {
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
