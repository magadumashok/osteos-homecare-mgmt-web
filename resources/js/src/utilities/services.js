// The service a patient is registered for, and a duty is for. "Other" lets
// staff type their own; it is stored as that text.
export const SERVICES = ["Nursing", "Care taking"];

export const SERVICE_OPTIONS = [
    { value: "", label: "Not set" },
    ...SERVICES.map((s) => ({ value: s, label: s })),
    { value: "other", label: "Other" },
];

/** A stored service as the form's { service, other } pair. */
export const serviceChoice = (stored) => {
    if (!stored) return { service: "", other: "" };
    return SERVICES.includes(stored) ? { service: stored, other: "" } : { service: "other", other: stored };
};

/** The form's pair back to the stored text (null when not set). */
export const serviceValue = (service, other) => (service === "other" ? (other ?? "").trim() || null : service || null);
