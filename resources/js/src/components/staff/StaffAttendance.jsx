import React, { useMemo, useState } from "react";
import { useParams } from "react-router-dom";
import { MenuItem, TextField } from "@mui/material";
import DataTable from "../table/DataTable";
import { Badge, Card, Loader, PageHeader, StatStrip } from "../common/ui";
import useAsync from "../../hooks/useAsync";
import { duties, staff } from "../../api/homecare";
import { fmtDate, fmtTime, humanize, isYes } from "../../utilities/format";

const dutyStatus = (d) => {
    if (isYes(d.not_attended)) return <Badge tone="danger">Not attended</Badge>;
    if (isYes(d.sign_out)) return <Badge tone="success">Completed</Badge>;
    if (isYes(d.sign_in)) return <Badge tone="warning">In progress</Badge>;
    return <Badge tone="muted">Scheduled</Badge>;
};

const tripStatus = (t) => {
    if (isYes(t.drop)) return <Badge tone="success">Dropped</Badge>;
    if (isYes(t.pickup)) return <Badge tone="warning">Picked up</Badge>;
    return <Badge tone="muted">Scheduled</Badge>;
};

/** Attendance monitor: duty sign-ins for clinical staff, pickups and drops for drivers. */
const StaffAttendance = () => {
    const { id } = useParams();
    const [patientId, setPatientId] = useState("");

    const { data: member } = useAsync(() => staff.show(id), [id]);
    const isDriver = (member?.roles ?? []).includes("transport");

    const { data: patients } = useAsync(() => (member && !isDriver ? staff.patients(id) : []), [member, isDriver], []);

    const { data: rows, loading } = useAsync(async () => {
        if (!member) return [];
        if (isDriver) return duties.driverAttendance(member.username);
        const result = await duties.staffAttendance(member.username, patientId || undefined);
        return patientId ? result.attendance_data : result;
    }, [member, isDriver, patientId], []);

    const summary = useMemo(() => {
        if (isDriver) {
            return [
                { label: "Trips", value: rows.length },
                { label: "Picked up", value: rows.filter((t) => isYes(t.pickup)).length },
                { label: "Dropped", value: rows.filter((t) => isYes(t.drop)).length },
            ];
        }
        return [
            { label: "Duties", value: rows.length },
            { label: "Completed", value: rows.filter((d) => isYes(d.sign_out)).length },
            { label: "Not attended", value: rows.filter((d) => isYes(d.not_attended)).length },
        ];
    }, [rows, isDriver]);

    const columns = useMemo(() => (isDriver ? [
        { header: "Date", accessorKey: "date", cell: ({ row }) => fmtDate(row.original.date) },
        { header: "Staff", accessorKey: "staff_name", cell: ({ row }) => <>{row.original.staff_name ?? row.original.staff_id} <span style={{ color: "#8a9baa" }}>· {humanize(row.original.staff_role)}</span></> },
        { header: "Duty", id: "slot", cell: ({ row }) => `${fmtTime(row.original.start_time)} – ${fmtTime(row.original.end_time)}` },
        { header: "Pickup", accessorKey: "pickup_time", cell: ({ row }) => fmtTime(row.original.pickup_time) },
        { header: "Drop", accessorKey: "drop_time", cell: ({ row }) => fmtTime(row.original.drop_time) },
        { header: "Address", accessorKey: "address", cell: ({ row }) => row.original.address || "—" },
        { header: "Status", id: "status", cell: ({ row }) => tripStatus(row.original) },
    ] : [
        { header: "Date", accessorKey: "date", cell: ({ row }) => fmtDate(row.original.date) },
        { header: "Patient", accessorKey: "patient_name", cell: ({ row }) => row.original.patient_name ?? row.original.patient_id },
        { header: "Duty", id: "slot", cell: ({ row }) => `${fmtTime(row.original.start_time)} – ${fmtTime(row.original.end_time)}` },
        { header: "Signed in", accessorKey: "signin_time", cell: ({ row }) => fmtTime(row.original.signin_time) },
        { header: "Signed out", accessorKey: "signout_time", cell: ({ row }) => fmtTime(row.original.signout_time) },
        { header: "Status", id: "status", cell: ({ row }) => dutyStatus(row.original) },
        { header: "Reason", accessorKey: "not_attended_reason", cell: ({ row }) => row.original.not_attended_reason || "—" },
    ]), [isDriver]);

    if (!member) return <Loader />;

    return (
        <>
            <PageHeader eyebrow="Attendance monitor" title={member.name} subtitle={`${humanize(member.roles?.[0])} · ${member.username}`} />
            <StatStrip items={summary} />
            <Card
                label={isDriver ? "Trips" : "Duties"}
                actions={!isDriver && (
                    <TextField select size="small" label="Patient" value={patientId} onChange={(e) => setPatientId(e.target.value)} sx={{ minWidth: 220 }}>
                        <MenuItem value="">All patients</MenuItem>
                        {patients.map((p) => <MenuItem key={p.username} value={p.username}>{p.patient_name}</MenuItem>)}
                    </TextField>
                )}
            >
                <DataTable data={rows} columns={columns} emptyMessage={loading ? "Loading…" : "No records yet"} />
            </Card>
        </>
    );
};

export default StaffAttendance;
