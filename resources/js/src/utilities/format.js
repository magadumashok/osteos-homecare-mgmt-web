// Display helpers. Dates and times come from the API as "YYYY-MM-DD" and "HH:MM" strings.

const pad = (n) => String(n).padStart(2, "0");

/** Today as "YYYY-MM-DD" in the browser's time zone. */
export const today = () => {
    const d = new Date();
    return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`;
};

/** Current time as "HH:MM". */
export const nowTime = () => {
    const d = new Date();
    return `${pad(d.getHours())}:${pad(d.getMinutes())}`;
};

/** "2025-03-01" → "1 Mar 2025". Anything unparseable is returned as is. */
export const fmtDate = (value) => {
    if (!value) return "—";
    const m = /^(\d{4})-(\d{2})-(\d{2})/.exec(String(value));
    if (!m) return String(value);
    const d = new Date(Number(m[1]), Number(m[2]) - 1, Number(m[3]));
    return d.toLocaleDateString(undefined, { day: "numeric", month: "short", year: "numeric" });
};

/** "14:05" → "2:05 pm". */
export const fmtTime = (value) => {
    const m = /^(\d{1,2}):(\d{2})/.exec(String(value ?? ""));
    if (!m) return value ? String(value) : "—";
    const h = Number(m[1]);
    return `${h % 12 || 12}:${m[2]} ${h >= 12 ? "pm" : "am"}`;
};

export const fmtMoney = (value, currency = "AED") => {
    if (value === null || value === undefined || value === "") return "—";
    const n = Number(value);
    return Number.isFinite(n) ? `${n.toLocaleString(undefined, { maximumFractionDigits: 2 })} ${currency}` : String(value);
};

/** "physiotherapist" → "Physiotherapist", "text_notes" → "Text notes". */
export const humanize = (value) => {
    if (!value) return "";
    const s = String(value).replace(/_/g, " ");
    return s.charAt(0).toUpperCase() + s.slice(1);
};

export const initials = (name) =>
    String(name ?? "?")
        .split(/\s+/)
        .filter(Boolean)
        .slice(0, 2)
        .map((w) => w[0].toUpperCase())
        .join("") || "?";

/** Legacy booleans may arrive as true/false or "true"/"false". */
export const isYes = (value) => value === true || value === "true" || value === 1 || value === "1";

/** Values sent to the API: drop empty strings so optional fields stay unset. */
export const compact = (obj) =>
    Object.fromEntries(Object.entries(obj).filter(([, v]) => v !== "" && v !== undefined));
