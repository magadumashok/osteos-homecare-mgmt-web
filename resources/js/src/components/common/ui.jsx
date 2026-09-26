import React from "react";
import {
    CircularProgress,
    Dialog,
    DialogActions,
    DialogContent,
    DialogTitle,
    IconButton,
} from "@mui/material";
import CloseRounded from "@mui/icons-material/CloseRounded";
import { initials } from "../../utilities/format";

// Page building blocks in the EMR's style (eyebrow + serif title, white cards,
// section labels, stat strips, badges). Pages compose these instead of
// repeating inline styles.

export const colors = {
    ink: "#0d2b36",
    body: "#4a6275",
    muted: "#8a9baa",
    line: "#e8eef3",
    brand: "#1a8fa8",
    brandDark: "#0d5068",
    danger: "#e05555",
    success: "#1f9d6b",
    warning: "#c98a12",
};

export const cardStyle = {
    background: "#fff",
    borderRadius: 12,
    padding: "20px 24px",
    boxShadow: "0 1px 6px rgba(0,0,0,.07)",
    border: `1px solid ${colors.line}`,
};

/** Eyebrow, serif title, optional subtitle and right-aligned actions. */
export const PageHeader = ({ eyebrow, title, subtitle, actions }) => (
    <div style={{ marginBottom: 20, display: "flex", alignItems: "flex-end", justifyContent: "space-between", flexWrap: "wrap", gap: 12 }}>
        <div>
            {eyebrow && <p style={{ fontSize: 11, color: colors.muted, textTransform: "uppercase", letterSpacing: ".8px", margin: "0 0 4px" }}>{eyebrow}</p>}
            <h2 style={{ fontFamily: "'Playfair Display', serif", fontSize: 24, fontWeight: 700, color: colors.ink, margin: 0 }}>{title}</h2>
            {subtitle && <p style={{ fontSize: 13, color: colors.body, margin: "6px 0 0" }}>{subtitle}</p>}
        </div>
        {actions && <div style={{ display: "flex", gap: 10, flexWrap: "wrap" }}>{actions}</div>}
    </div>
);

/** White card with an optional uppercase section label and header actions. */
export const Card = ({ label, actions, children, style }) => (
    <div style={{ ...cardStyle, ...style }}>
        {(label || actions) && (
            <div style={{ display: "flex", alignItems: "center", justifyContent: "space-between", gap: 12, marginBottom: 16, paddingBottom: 8, borderBottom: `1px solid ${colors.line}` }}>
                {label && <div style={{ fontSize: 11, letterSpacing: ".8px", textTransform: "uppercase", color: colors.brand, fontWeight: 600 }}>{label}</div>}
                {actions && <div style={{ display: "flex", gap: 8, flexWrap: "wrap" }}>{actions}</div>}
            </div>
        )}
        {children}
    </div>
);

export const StatStrip = ({ items }) => (
    <div style={{ ...cardStyle, display: "flex", gap: 32, padding: "16px 24px", marginBottom: 16, flexWrap: "wrap" }}>
        {items.map(({ label, value }) => (
            <div key={label} style={{ display: "flex", flexDirection: "column", alignItems: "center", gap: 2 }}>
                <span style={{ fontFamily: "'Playfair Display', serif", fontSize: 20, fontWeight: 700, color: colors.ink }}>{value}</span>
                <span style={{ fontSize: 11, color: colors.muted, textTransform: "uppercase", letterSpacing: ".6px" }}>{label}</span>
            </div>
        ))}
    </div>
);

/** Primary / secondary / danger buttons from the design system. */
export const Button = ({ variant = "primary", small, loading, disabled, children, style, type = "button", ...rest }) => {
    const className = variant === "primary" ? "auth-btn-primary" : "auth-btn-secondary";
    const danger = variant === "danger" ? { color: colors.danger, borderColor: "rgba(224,85,85,.4)" } : null;
    return (
        <button
            {...rest}
            type={type}
            className={className}
            disabled={loading || disabled}
            style={{ ...(small ? { padding: "7px 16px", fontSize: 12 } : { padding: "9px 20px", fontSize: 13 }), ...danger, ...style }}
        >
            {loading ? <><CircularProgress size={13} color="inherit" sx={{ mr: 1, verticalAlign: "middle" }} />Saving…</> : children}
        </button>
    );
};

const tones = {
    neutral: { bg: "rgba(26,143,168,.1)", color: colors.brandDark, border: "rgba(26,143,168,.2)" },
    success: { bg: "rgba(31,157,107,.1)", color: colors.success, border: "rgba(31,157,107,.25)" },
    danger: { bg: "rgba(224,85,85,.1)", color: colors.danger, border: "rgba(224,85,85,.25)" },
    warning: { bg: "rgba(201,138,18,.1)", color: colors.warning, border: "rgba(201,138,18,.25)" },
    muted: { bg: "#f3f6f8", color: colors.muted, border: colors.line },
};

export const Badge = ({ tone = "neutral", children }) => {
    const t = tones[tone] ?? tones.neutral;
    return (
        <span style={{ display: "inline-flex", alignItems: "center", padding: "2px 9px", borderRadius: 20, fontSize: 11, fontWeight: 600, background: t.bg, color: t.color, border: `1px solid ${t.border}`, textTransform: "capitalize", whiteSpace: "nowrap" }}>
            {children}
        </span>
    );
};

export const Avatar = ({ name, size = 34 }) => (
    <div style={{ width: size, height: size, borderRadius: "50%", background: "linear-gradient(135deg, #093343, #15698a)", color: "#fff", display: "flex", alignItems: "center", justifyContent: "center", fontSize: size * 0.38, fontWeight: 700, flexShrink: 0 }}>
        {initials(name)}
    </div>
);

/** Name with avatar and a muted second line, for table cells and headers. */
export const PersonCell = ({ name, sub }) => (
    <div style={{ display: "flex", alignItems: "center", gap: 10 }}>
        <Avatar name={name} />
        <div>
            <div style={{ fontWeight: 600, fontSize: 13, color: colors.ink }}>{name || "—"}</div>
            {sub && <div style={{ fontSize: 11, color: colors.muted }}>{sub}</div>}
        </div>
    </div>
);

/** Label/value pairs in a responsive grid. */
export const InfoGrid = ({ items, columns = 3 }) => (
    <div style={{ display: "grid", gridTemplateColumns: `repeat(auto-fill, minmax(${columns === 2 ? 260 : 190}px, 1fr))`, gap: "14px 24px" }}>
        {items.filter(Boolean).map(({ label, value }) => (
            <div key={label}>
                <div style={{ fontSize: 11, color: colors.muted, textTransform: "uppercase", letterSpacing: ".6px", marginBottom: 3 }}>{label}</div>
                <div style={{ fontSize: 13, color: colors.ink, wordBreak: "break-word" }}>{value === null || value === undefined || value === "" ? "—" : value}</div>
            </div>
        ))}
    </div>
);

export const Loader = ({ label = "Loading…" }) => (
    <div style={{ display: "flex", alignItems: "center", justifyContent: "center", gap: 10, padding: 40, color: colors.muted, fontSize: 13 }}>
        <CircularProgress size={18} /> {label}
    </div>
);

export const EmptyState = ({ title, children }) => (
    <div style={{ textAlign: "center", padding: "36px 16px", color: colors.muted }}>
        <div style={{ fontSize: 14, fontWeight: 600, color: colors.body, marginBottom: 4 }}>{title}</div>
        {children && <div style={{ fontSize: 13 }}>{children}</div>}
    </div>
);

/** Dialog with the design system's header and footer buttons. */
export const Modal = ({ open, title, onClose, children, actions, maxWidth = "sm" }) => (
    <Dialog open={open} onClose={onClose} fullWidth maxWidth={maxWidth} PaperProps={{ sx: { borderRadius: 3 } }}>
        <DialogTitle sx={{ fontFamily: "'Playfair Display', serif", fontWeight: 700, color: colors.ink, pr: 6 }}>
            {title}
            <IconButton aria-label="Close" onClick={onClose} sx={{ position: "absolute", right: 12, top: 12 }}>
                <CloseRounded fontSize="small" />
            </IconButton>
        </DialogTitle>
        <DialogContent dividers>{children}</DialogContent>
        {actions && <DialogActions sx={{ px: 3, py: 2, gap: 1 }}>{actions}</DialogActions>}
    </Dialog>
);

export const ConfirmDialog = ({ open, title, message, confirmLabel = "Confirm", danger, loading, onConfirm, onClose }) => (
    <Modal
        open={open}
        title={title}
        onClose={onClose}
        maxWidth="xs"
        actions={
            <>
                <Button variant="secondary" small onClick={onClose} disabled={loading}>Cancel</Button>
                <Button variant={danger ? "danger" : "primary"} small loading={loading} onClick={onConfirm}>{confirmLabel}</Button>
            </>
        }
    >
        <p style={{ fontSize: 14, color: colors.body, margin: 0 }}>{message}</p>
    </Modal>
);

/** Yes/no marker used for consent and attendance flags. */
export const YesNo = ({ value, yes = "Yes", no = "No" }) => <Badge tone={value ? "success" : "muted"}>{value ? yes : no}</Badge>;
