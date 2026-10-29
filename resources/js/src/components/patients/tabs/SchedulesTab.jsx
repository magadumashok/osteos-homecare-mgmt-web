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
import { fmtDate, fmtTime, humanize, isYes, today } from "../../../utilities/format";

/** 24-hour duties, and shifts with care tasks (e.g. a caretaker's 12-hour shift), have a care log. */
export const hasCareLog = (d) => isYes(d.live_in) || (Array.isArray(d.care_tasks) && d.care_tasks.length > 0);

/** "28 Oct 2026", or "28 Oct 2026 → 27 Jan 2027" when the duty runs over several days. */
export const dutyDates = (d) => (d.end_date && d.end_date !== d.date ? `${fmtDate(d.date)} → ${fmtDate(d.end_date)}` : fmtDate(d.date));

/** Visit times, or "24-hour care" for a caretaker's live-in duty. */
export const dutyTime = (d) => (isYes(d.live_in) ? "24-hour care" : `${fmtTime(d.start_time)} – ${fmtTime(d.end_time)}`);

export const dutyStatus = (d) => {
    if (isYes(d.live_in)) {
        if (isYes(d.not_attended)) return <Badge tone="danger">Cancelled</Badge>;
        if (d.date > today()) return <Badge tone="muted">Upcoming</Badge>;
        if ((d.end_date || d.date) < today()) return <Badge tone="success">Ended</Badge>;
        return <Badge tone="warning">Ongoing</Badge>;
    }
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

const hasPoint = (lat, lng) => lat !== null && lat !== undefined && lat !== "" && lng !== null && lng !== undefined && lng !== "";
export const hasAttendanceLocation = (d) => hasPoint(d.signin_latitude, d.signin_longitude) || hasPoint(d.signout_latitude, d.signout_longitude);

const MapPoint = ({ title, time, lat, lng }) => (
    <div style={{ flex: "1 1 320px", minWidth: 0 }}>
        <div style={{ fontSize: 13, fontWeight: 600, color: colors.ink, marginBottom: 6 }}>
            {title}{time ? ` · ${fmtTime(time)}` : ""}
        </div>
        {hasPoint(lat, lng) ? (
            <>
                <iframe
                    title={title}
                    src={`https://maps.google.com/maps?q=${lat},${lng}&z=16&output=embed`}
                    style={{ width: "100%", height: 260, border: `1px solid ${colors.line}`, borderRadius: 8 }}
                    loading="lazy"
                />
                <a href={`https://www.google.com/maps?q=${lat},${lng}`} target="_blank" rel="noreferrer" style={{ fontSize: 12 }}>
                    Open in Google Maps ({Number(lat).toFixed(5)}, {Number(lng).toFixed(5)})
                </a>
            </>
        ) : (
            <p style={{ fontSize: 13, color: colors.muted }}>{time ? "Location not shared (GPS off or refused)." : "Not yet."}</p>
        )}
    </div>
);

/** Where the staff member was when they signed in and out of this day's session. */
export const AttendanceMapDialog = ({ duty, onClose }) => (
    <Modal open title={`Sign-in location · ${fmtDate(duty.date)}`} onClose={onClose} maxWidth="md">
        <p style={{ fontSize: 13, color: colors.body, marginTop: 0 }}>
            {duty.staff_name ?? duty.name ?? duty.staff_id} with {duty.patient_name ?? duty.patient_id} · {fmtTime(duty.start_time)} – {fmtTime(duty.end_time)}
            {duty.address ? <><br />Patient's address: {duty.address}</> : null}
        </p>
        <div style={{ display: "flex", gap: 16, flexWrap: "wrap" }}>
            <MapPoint title="Signed in" time={duty.signin_time} lat={duty.signin_latitude} lng={duty.signin_longitude} />
            <MapPoint title="Signed out" time={duty.signout_time} lat={duty.signout_latitude} lng={duty.signout_longitude} />
        </div>
    </Modal>
);

/** Photos staff took with the patient at their home, with time and a map link to where. */
export const PresencePhotosDialog = ({ duty, onClose }) => {
    const photos = Array.isArray(duty.presence_photos) ? duty.presence_photos : [];
    return (
        <Modal open title={`Visit photos · ${duty.patient_name ?? duty.patient_id ?? ""}`} onClose={onClose} maxWidth="md">
            {photos.length ? (
                <div style={{ display: "grid", gridTemplateColumns: "repeat(auto-fill, minmax(200px, 1fr))", gap: 12 }}>
                    {photos.map((p) => (
                        <div key={p.url} style={{ fontSize: 12, color: colors.body }}>
                            <a href={p.url} target="_blank" rel="noreferrer">
                                <img src={p.url} alt="Staff with patient" style={{ width: "100%", height: 180, objectFit: "cover", borderRadius: 8, border: `1px solid ${colors.line}` }} />
                            </a>
                            <div style={{ marginTop: 4 }}>
                                {fmtDate(p.date)} · {fmtTime(p.time)}
                                {p.latitude != null && p.longitude != null && (
                                    <> · <a href={`https://www.google.com/maps?q=${p.latitude},${p.longitude}`} target="_blank" rel="noreferrer">Location</a></>
                                )}
                            </div>
                        </div>
                    ))}
                </div>
            ) : <p style={{ color: colors.muted, fontSize: 13 }}>No photos for this duty yet.</p>}
        </Modal>
    );
};

const NotAttendedDialog =({ duty, onClose, onSaved }) => {
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
    const [photos, setPhotos] = useState(null);
    const [mapFor, setMapFor] = useState(null);

    const columns = useMemo(() => [
        { header: "Date", accessorKey: "date", cell: ({ row }) => dutyDates(row.original) },
        { header: "Time", id: "time", cell: ({ row }) => dutyTime(row.original) },
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
                        {hasCareLog(d) && <TableBtn onClick={() => navigate(`/schedules/${d._id}/care`)}>Care log</TableBtn>}
                        {hasAttendanceLocation(d) && <TableBtn variant="secondary" onClick={() => setMapFor({ ...d, patient_name: patient.patient_name, address: patient.address })}>Location</TableBtn>}
                        {Array.isArray(d.presence_photos) && d.presence_photos.length > 0 && <TableBtn variant="secondary" onClick={() => setPhotos({ ...d, patient_name: patient.patient_name })}>Photos ({d.presence_photos.length})</TableBtn>}
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
            // actions={can("schedules.manage") && !isYes(patient.discharged) && 
            // <Button small onClick={() => navigate(`/schedules/new?patient=${patient.username}`)}>+ Schedule duty</Button>}
        >
            <DataTable data={rows} columns={columns} globalSearch={rows.length > 10} emptyMessage={loading ? "Loading…" : "No duties scheduled yet"} />
            {specimens && <SpecimensDialog duty={specimens} onClose={() => setSpecimens(null)} />}
            {photos && <PresencePhotosDialog duty={photos} onClose={() => setPhotos(null)} />}
            {mapFor && <AttendanceMapDialog duty={mapFor} onClose={() => setMapFor(null)} />}
            {notAttended && <NotAttendedDialog duty={notAttended} onClose={() => setNotAttended(null)} onSaved={() => { setNotAttended(null); reload(); }} />}
        </Card>
    );
};

export default SchedulesTab;
