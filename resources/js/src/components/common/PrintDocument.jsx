import React from "react";
import { useNavigate } from "react-router-dom";
import { useSelector } from "react-redux";
import { Button, colors } from "./ui";

// While printing, only the document shows: no sidebar, topbar or buttons.
const printCss = `
@media print {
  .shell-sidebar, .shell-topbar, .no-print, .Toastify { display: none !important; }
  .shell-root, .shell-body, .shell-scroll { display: block !important; height: auto !important; overflow: visible !important; margin: 0 !important; padding: 0 !important; background: #fff !important; }
  .print-sheet { box-shadow: none !important; border: none !important; margin: 0 !important; max-width: none !important; }
  @page { margin: 14mm; }
}`;

/** The center's letterhead, from the signed-in session. */
export const Letterhead = ({ title, meta }) => {
    const homecare = useSelector((s) => s.auth.homecare) ?? {};
    const place = [homecare.city, homecare.country].filter(Boolean).join(", ");

    return (
        <div style={{ display: "flex", justifyContent: "space-between", gap: 24, paddingBottom: 16, marginBottom: 20, borderBottom: `2px solid ${colors.brandDark}` }}>
            <div>
                <div style={{ fontFamily: "'Playfair Display', serif", fontSize: 22, fontWeight: 700, color: colors.ink }}>{homecare.name ?? "Homecare"}</div>
                <div style={{ fontSize: 12, color: colors.body, lineHeight: 1.6, marginTop: 4 }}>
                    {homecare.address && <div>{homecare.address}</div>}
                    {place && <div>{place}</div>}
                    {homecare.mobile && <div>Tel: {homecare.mobile}</div>}
                    {homecare.email && <div>{homecare.email}</div>}
                    {homecare.center_id && <div>License: {homecare.center_id}</div>}
                </div>
            </div>
            <div style={{ textAlign: "right" }}>
                <div style={{ fontSize: 20, fontWeight: 700, letterSpacing: "1px", textTransform: "uppercase", color: colors.brandDark }}>{title}</div>
                <div style={{ fontSize: 12, color: colors.body, lineHeight: 1.6, marginTop: 4 }}>
                    {(meta ?? []).filter(([, v]) => v).map(([label, value]) => <div key={label}>{label}: <strong>{value}</strong></div>)}
                </div>
            </div>
        </div>
    );
};

/** A printable A4-style sheet with Back and Print buttons above it. */
const PrintDocument = ({ children }) => {
    const navigate = useNavigate();

    return (
        <>
            <style>{printCss}</style>
            <div className="no-print" style={{ display: "flex", justifyContent: "flex-end", gap: 10, maxWidth: 860, margin: "0 auto 16px" }}>
                <Button variant="secondary" small onClick={() => navigate(-1)}>Back</Button>
                <Button small onClick={() => window.print()}>Print</Button>
            </div>
            <div className="print-sheet" style={{ background: "#fff", maxWidth: 860, margin: "0 auto", padding: "36px 40px", borderRadius: 12, border: `1px solid ${colors.line}`, boxShadow: "0 1px 6px rgba(0,0,0,.07)", color: colors.ink }}>
                {children}
            </div>
        </>
    );
};

/** Simple bordered table for documents. */
export const DocTable = ({ head, rows, foot }) => {
    const cell = { padding: "8px 10px", borderBottom: `1px solid ${colors.line}`, fontSize: 13, textAlign: "left" };
    return (
        <table style={{ width: "100%", borderCollapse: "collapse", margin: "8px 0 16px" }}>
            <thead>
                <tr>{head.map((h, i) => <th key={i} style={{ ...cell, background: "#f3f7f9", fontSize: 11, textTransform: "uppercase", letterSpacing: ".5px", color: colors.body, textAlign: i === head.length - 1 && head.length > 2 ? "right" : "left" }}>{h}</th>)}</tr>
            </thead>
            <tbody>
                {rows.map((r, ri) => (
                    <tr key={ri}>{r.map((c, ci) => <td key={ci} style={{ ...cell, textAlign: ci === r.length - 1 && r.length > 2 ? "right" : "left" }}>{c}</td>)}</tr>
                ))}
            </tbody>
            {foot && (
                <tfoot>
                    {foot.map(([label, value, strong], i) => (
                        <tr key={i}>
                            <td colSpan={head.length - 1} style={{ ...cell, textAlign: "right", borderBottom: "none", fontWeight: strong ? 700 : 500 }}>{label}</td>
                            <td style={{ ...cell, textAlign: "right", borderBottom: "none", fontWeight: strong ? 700 : 500, fontSize: strong ? 15 : 13 }}>{value}</td>
                        </tr>
                    ))}
                </tfoot>
            )}
        </table>
    );
};

export default PrintDocument;
