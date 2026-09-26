import { toast } from "react-toastify";

// Field names → readable labels for the toast ("temporary_address.country" → "Country").
const label = (path) => {
    const last = path.split(".").pop();
    return last.charAt(0).toUpperCase() + last.slice(1).replace(/_/g, " ");
};

const flatten = (errors, prefix = "") =>
    Object.entries(errors ?? {}).flatMap(([key, value]) => {
        const path = prefix ? `${prefix}.${key}` : key;
        return value?.message !== undefined || value?.type ? [path] : flatten(value, path);
    });

/**
 * handleSubmit's second argument: when validation fails, say which fields
 * need attention and scroll to the first one (the messages under the fields
 * are easy to miss on long forms).
 */
export const onInvalid = (errors) => {
    const fields = flatten(errors);
    toast.error(`Please check: ${fields.map(label).join(", ")}.`);
    requestAnimationFrame(() => {
        const first = document.querySelector(".Mui-error");
        first?.scrollIntoView({ behavior: "smooth", block: "center" });
        first?.querySelector("input, textarea, [role=combobox]")?.focus({ preventScroll: true });
    });
};
