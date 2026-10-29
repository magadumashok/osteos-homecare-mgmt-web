import React, { useEffect, useState } from "react";
import { useNavigate } from "react-router-dom";
import { MenuItem, TextField } from "@mui/material";
import { toast } from "react-toastify";
import { Badge, Button, Card, EmptyState, Modal, PageHeader, StatStrip, colors } from "../common/ui";
import useAsync from "../../hooks/useAsync";
import usePermission from "../../hooks/usePermission";
import { careLogs, staff as staffApi } from "../../api/homecare";
import { errorMessage } from "../../api/client";
import { fmtDate, fmtTime, today } from "../../utilities/format";

/** Where a caretaker's day stands: updated, skipped (covered or not) or waiting. */
export const careDayStatus = (log, date) => {
    if (log?.status === "skipped") {
        return log.cover_duty_id ? <Badge tone="neutral">Skipped · covered</Badge> : <Badge tone="danger">Skipped · needs cover</Badge>;
    }
    if (log?.status === "logged") return <Badge tone="success">Updated {fmtTime(log.updated_time)}</Badge>;
    return <Badge tone="muted">{date > today() ? "Upcoming" : "No update yet"}</Badge>;
};

/** Tasks ticked off, symptoms and notes of one day's update (or the skip reason). */
export const CareLogDetail = ({ log }) => {
    if (!log) return null;
    if (log.status === "skipped") {
        return <div style={{ fontSize: 13, color: colors.body }}>Reason: {log.skip_reason || "—"}</div>;
    }
    const tasks = Array.isArray(log.tasks) ? log.tasks : [];
    return (
        <div style={{ display: "grid", gap: 8, fontSize: 13 }}>
            {tasks.length > 0 && (
                <div style={{ display: "flex", flexWrap: "wrap", gap: 6 }}>
                    {tasks.map((t) => (
                        <span key={t.name} title={t.note || undefined} style={{ padding: "2px 9px", borderRadius: 20, border: `1px solid ${t.done ? "rgba(31,157,107,.3)" : colors.line}`, color: t.done ? colors.success : colors.muted, background: t.done ? "rgba(31,157,107,.08)" : "#f7f9fa", textDecoration: t.done ? "none" : "line-through" }}>
                            {t.done ? "✓ " : ""}{t.name}{t.time ? ` · ${fmtTime(t.time)}` : ""}{t.note ? ` — ${t.note}` : ""}
                        </span>
                    ))}
                </div>
            )}
            {log.symptoms && <div style={{ color: colors.danger }}><strong>Symptoms:</strong> {log.symptoms}</div>}
            {log.notes && <div style={{ color: colors.body }}><strong>Notes:</strong> {log.notes}</div>}
        </div>
    );
};

/** Give a skipped day to another caretaker. */
export const CoverDialog = ({ duty, date, onClose, onSaved }) => {
    const [caretakers, setCaretakers] = useState([]);
    const [staffId, setStaffId] = useState("");
    const [saving, setSaving] = useState(false);

    useEffect(() => {
        staffApi.list({ role: duty.role || "caretaker" })
            .then((list) => setCaretakers((list ?? []).filter((s) => s.username !== duty.staff_id)))
            .catch(() => setCaretakers([]));
    }, [duty]);

    const save = async () => {
        setSaving(true);
        try {
            await careLogs.coverDay(duty._id, date, staffId);
            toast.success("Cover caretaker assigned and notified.");
            onSaved();
        } catch (e) {
            toast.error(errorMessage(e));
        } finally {
            setSaving(false);
        }
    };

    return (
        <Modal open title="Assign cover caretaker" onClose={onClose} maxWidth="xs"
            actions={<><Button variant="secondary" small onClick={onClose}>Cancel</Button><Button small loading={saving} disabled={!staffId} onClick={save}>Assign</Button></>}>
            <p style={{ fontSize: 13, color: colors.body, marginTop: 0 }}>
                {duty.patient_name ?? duty.patient_id} · {fmtDate(date)} (24 hours)
            </p>
            <TextField select fullWidth margin="dense" label="Caretaker" value={staffId} onChange={(e) => setStaffId(e.target.value)}>
                {caretakers.map((s) => <MenuItem key={s.username} value={s.username}>{s.name}</MenuItem>)}
            </TextField>
        </Modal>
    );
};

/** Admin board of caretakers' 24-hour duties for one day, with each day's update. */
const CareUpdates = () => {
    const navigate = useNavigate();
    const { can } = usePermission();
    const [date, setDate] = useState(today());
    const [covering, setCovering] = useState(null);
    const { data: rows, loading, reload } = useAsync(() => careLogs.day({ date }), [date], []);

    const skipped = rows.filter((d) => d.log?.status === "skipped");

    return (
        <>
            <PageHeader
                eyebrow="Scheduling"
                title="Caretaker updates"
                subtitle="Daily care logs from caretakers on 24-hour duty. Skipped days need a cover caretaker."
                actions={can("schedules.manage") && <Button onClick={() => navigate("/schedules/new")}>+ Schedule duty</Button>}
            />
            <StatStrip items={[
                { label: "Caretakers on duty", value: rows.length - skipped.length },
                { label: "Updated", value: rows.filter((d) => d.log?.status === "logged").length },
                { label: "No update yet", value: rows.filter((d) => !d.log).length },
                { label: "Need cover", value: skipped.filter((d) => !d.log.cover_duty_id).length },
            ]} />
            <Card
                label={fmtDate(date)}
                actions={<TextField size="small" type="date" label="Day" value={date} onChange={(e) => setDate(e.target.value || today())} InputLabelProps={{ shrink: true }} />}
            >
                {loading ? <EmptyState title="Loading…" /> : rows.length === 0 ? <EmptyState title="No caretakers on duty this day" /> : rows.map((d) => (
                    <div key={d._id} style={{ padding: "14px 0", borderBottom: `1px solid ${colors.line}`, display: "grid", gap: 8 }}>
                        <div style={{ display: "flex", alignItems: "center", gap: 12, flexWrap: "wrap" }}>
                            <div style={{ flex: 1, minWidth: 220 }}>
                                <strong style={{ color: colors.ink }}>{d.patient_name ?? d.patient_id}</strong>
                                <span style={{ color: colors.muted, fontSize: 13 }}> · {d.staff_name ?? d.staff_id}{d.covers_duty_id ? " (cover)" : ""}</span>
                                <div style={{ fontSize: 12, color: colors.muted }}>
                                    {d.live_in ? `24-hour care · ${fmtDate(d.date)} → ${fmtDate(d.end_date)}` : `Shift ${fmtTime(d.start_time)} – ${fmtTime(d.end_time)}`}
                                </div>
                            </div>
                            {careDayStatus(d.log, date)}
                            {can("schedules.manage") && d.log?.status === "skipped" && !d.log.cover_duty_id && (
                                <Button small onClick={() => setCovering(d)}>Assign cover</Button>
                            )}
                            <Button variant="secondary" small onClick={() => navigate(`/schedules/${d._id}/care`)}>Care log</Button>
                        </div>
                        <CareLogDetail log={d.log} />
                    </div>
                ))}
            </Card>
            {covering && <CoverDialog duty={covering} date={date} onClose={() => setCovering(null)} onSaved={() => { setCovering(null); reload(); }} />}
        </>
    );
};

export default CareUpdates;
