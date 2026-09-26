import React, { useMemo, useState } from "react";
import { useNavigate } from "react-router-dom";
import { TextField } from "@mui/material";
import { toast } from "react-toastify";
import DataTable, { ActionCell, TableBtn } from "../../table/DataTable";
import { Badge, Button, Card, Modal, colors } from "../../common/ui";
import useAsync from "../../../hooks/useAsync";
import usePermission from "../../../hooks/usePermission";
import { duties, patients as patientsApi } from "../../../api/homecare";
import { errorMessage } from "../../../api/client";
import { fmtDate, fmtTime, humanize, isYes } from "../../../utilities/format";

export const dutyStatus = (d) => {
    if (isYes(d.not_attended)) return <Badge tone="danger">Not attended</Badge>;
    if (isYes(d.sign_out)) return <Badge tone="success">Completed</Badge>;
    if (isYes(d.sign_in)) return <Badge tone="warning">In progress</Badge>;
    return <Badge tone="muted">Scheduled</Badge>;
};

/** Specimen photos a staff member took during a duty. */
const SpecimensDialog = ({ duty, onClose }) => {
    const images = Array.isArray(duty.specimen_images) ? duty.specimen_images : [];
    return (
        <Modal open title={`Specimens · ${fmtDate(duty.date)}`} onClose={onClose} maxWidth="md">
            {images.length ? (
                <div style={{ display: "grid", gridTemplateColumns: "repeat(auto-fill, minmax(180px, 1fr))", gap: 12 }}>
                    {images.map((img, i) => {
                        const url = typeof img === "string" ? img : img.image_path;
                        return (
                            <a key={i} href={url} target="_blank" rel="noreferrer" style={{ textDecoration: "none", color: colors.body }}>
                                <img src={url} alt="Specimen" style={{ width: "100%", height: 140, objectFit: "cover", borderRadius: 8, border: `1px solid ${colors.line}` }} />
                                {img.lab_details && <div style={{ fontSize: 12, marginTop: 4 }}>{img.lab_details}</div>}
                            </a>
                        );
                    })}
                </div>
            ) : <p style={{ color: colors.muted, fontSize: 13 }}>No specimen images for this duty.</p>}
        </Modal>
    );
};

const NotAttendedDialog = ({ duty, onClose, onSaved }) => {
    const [reason, setReason] = useState("");
    const [saving, setSaving] = useState(false);
    const save = async () => {
        setSaving(true);
        try {
            await duties.notAttended(duty._id, reason);
            toast.success("Duty marked as not attended.");
            onSaved();
        } catch (e) {
            toast.error(errorMessage(e));
        } finally {
            setSaving(false);
        }
    };
    return (
        <Modal
            open
            title="Mark as not attended"
            onClose={onClose}
            maxWidth="xs"
            actions={<><Button variant="secondary" small onClick={onClose}>Cancel</Button><Button small loading={saving} disabled={!reason.trim()} onClick={save}>Save</Button></>}
        >
            <TextField fullWidth multiline minRows={2} label="Reason" value={reason} onChange={(e) => setReason(e.target.value)} />
        </Modal>
    );
};

/** Every duty with this patient: attendance, invoice per visit and specimens. */
const SchedulesTab = ({ patient }) => {
    const navigate = useNavigate();
    const { can, canAny } = usePermission();
    const { data: rows, loading, reload } = useAsync(() => patientsApi.schedules(patient.username), [patient.username], []);
    const [specimens, setSpecimens] = useState(null);
    const [notAttended, setNotAttended] = useState(null);

    const columns = useMemo(() => [
        { header: "Date", accessorKey: "date", cell: ({ row }) => `${fmtDate(row.original.date)}${row.original.end_date && row.original.end_date !== row.original.date ? ` → ${fmtDate(row.original.end_date)}` : ""}` },
        { header: "Time", id: "time", cell: ({ row }) => `${fmtTime(row.original.start_time)} – ${fmtTime(row.original.end_time)}` },
        { header: "Staff", accessorKey: "staff_name", cell: ({ row }) => <>{row.original.staff_name ?? row.original.staff_id}<div style={{ fontSize: 11, color: colors.muted }}>{humanize(row.original.role)}</div></> },
        { header: "Signed in / out", id: "attendance", cell: ({ row }) => `${fmtTime(row.original.signin_time)} / ${fmtTime(row.original.signout_time)}` },
        { header: "Status", id: "status", cell: ({ row }) => <>{dutyStatus(row.original)}{row.original.not_attended_reason && <div style={{ fontSize: 11, color: colors.muted, marginTop: 2 }}>{row.original.not_attended_reason}</div>}</> },
        {
            header: "Actions",
            id: "actions",
            cell: ({ row }) => {
                const d = row.original;
                const done = isYes(d.sign_out) || isYes(d.not_attended);
                return (
                    <ActionCell>
                        {canAny(["invoices.view", "patients.view"]) && <TableBtn variant="secondary" onClick={() => navigate(`/patients/${patient.username}/duties/${d._id}/invoice`)}>Invoice</TableBtn>}
                        {can("documents.view") && Array.isArray(d.specimen_images) && d.specimen_images.length > 0 && <TableBtn variant="secondary" onClick={() => setSpecimens(d)}>Specimens</TableBtn>}
                        {can("schedules.manage") && !done && <TableBtn onClick={() => navigate(`/schedules/${d._id}/edit`)}>Reschedule</TableBtn>}
                        {can("schedules.manage") && !done && <TableBtn variant="danger" onClick={() => setNotAttended(d)}>Not attended</TableBtn>}
                    </ActionCell>
                );
            },
        },
    ], [can, canAny, navigate, patient.username]);

    return (
        <Card
            label="Duty schedules"
            actions={can("schedules.manage") && !isYes(patient.discharged) && <Button small onClick={() => navigate(`/schedules/new?patient=${patient.username}`)}>+ Schedule duty</Button>}
        >
            <DataTable data={rows} columns={columns} globalSearch={rows.length > 10} emptyMessage={loading ? "Loading…" : "No duties scheduled yet"} />
            {specimens && <SpecimensDialog duty={specimens} onClose={() => setSpecimens(null)} />}
            {notAttended && <NotAttendedDialog duty={notAttended} onClose={() => setNotAttended(null)} onSaved={() => { setNotAttended(null); reload(); }} />}
        </Card>
    );
};

export default SchedulesTab;
