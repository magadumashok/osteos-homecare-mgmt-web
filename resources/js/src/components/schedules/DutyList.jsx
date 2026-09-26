import React, { useEffect, useMemo, useState } from "react";
import { useNavigate } from "react-router-dom";
import { useForm } from "react-hook-form";
import { Grid, MenuItem, TextField, ToggleButton, ToggleButtonGroup } from "@mui/material";
import { toast } from "react-toastify";
import DataTable, { ActionCell, TableBtn } from "../table/DataTable";
import RHFTextField from "../forms/RHFTextField";
import RHFSelect from "../forms/RHFSelect";
import { Button, Card, Modal, PageHeader, StatStrip, colors } from "../common/ui";
import { dutyStatus } from "../patients/tabs/SchedulesTab";
import useAsync from "../../hooks/useAsync";
import usePermission from "../../hooks/usePermission";
import { driverTrips, duties, staff as staffApi } from "../../api/homecare";
import * as reference from "../../api/reference";
import { errorMessage } from "../../api/client";
import { compact, fmtDate, fmtTime, humanize, isYes, today } from "../../utilities/format";
import { onInvalid } from "../../utilities/formErrors";

/** Book a driver to take the staff member to this duty. */
const DriverDialog = ({ duty, onClose }) => {
    const [drivers, setDrivers] = useState([]);
    const [driverId, setDriverId] = useState("");
    const [address, setAddress] = useState(duty.address ?? "");
    const [saving, setSaving] = useState(false);

    useEffect(() => {
        staffApi.list({ role: "transport" }).then(setDrivers).catch(() => setDrivers([]));
    }, []);

    const save = async () => {
        setSaving(true);
        try {
            await driverTrips.create({
                driver_id: driverId, staff_id: duty.staff_id, address,
                date: duty.date, end_date: duty.end_date ?? duty.date, start_time: duty.start_time, end_time: duty.end_time,
            });
            toast.success("Driver booked.");
            onClose();
        } catch (e) {
            toast.error(errorMessage(e));
        } finally {
            setSaving(false);
        }
    };

    return (
        <Modal open title="Book driver" onClose={onClose} maxWidth="xs"
            actions={<><Button variant="secondary" small onClick={onClose}>Cancel</Button><Button small loading={saving} disabled={!driverId} onClick={save}>Book</Button></>}>
            <p style={{ fontSize: 13, color: colors.body, marginTop: 0 }}>{duty.staff_name} · {fmtDate(duty.date)} {fmtTime(duty.start_time)}</p>
            <TextField select fullWidth margin="dense" label="Driver" value={driverId} onChange={(e) => setDriverId(e.target.value)}>
                {drivers.map((d) => <MenuItem key={d.username} value={d.username}>{d.name}</MenuItem>)}
            </TextField>
            <TextField fullWidth margin="dense" label="Pickup / drop address" value={address} onChange={(e) => setAddress(e.target.value)} />
        </Modal>
    );
};

/** A one-off visit to someone who is not an admitted patient. */
const ShortVisitDialog = ({ onClose }) => {
    const [staff, setStaff] = useState([]);
    const [countries, setCountries] = useState([]);
    const [saving, setSaving] = useState(false);
    const { control, handleSubmit } = useForm({
        defaultValues: { staff_id: "", patient_name: "", mobile: "", email: "", country: "", patient_address: "", date: today(), time: "10:00", visit_purpose: "", visit_fees: "" },
    });

    useEffect(() => {
        staffApi.list({ group: "visiting" }).then((l) => setStaff(l.map((s) => ({ value: s.username, label: `${s.name} (${humanize(s.roles?.[0])})` })))).catch(() => {});
        reference.countries().then((l) => setCountries(l.map((c) => ({ value: c.code, label: c.name })))).catch(() => {});
    }, []);

    const save = async (values) => {
        setSaving(true);
        try {
            await duties.createShortVisit(compact(values));
            toast.success("Short visit scheduled.");
            onClose();
        } catch (e) {
            toast.error(errorMessage(e));
        } finally {
            setSaving(false);
        }
    };

    return (
        <Modal open title="Short visit" onClose={onClose} maxWidth="md"
            actions={<><Button variant="secondary" small onClick={onClose}>Cancel</Button><Button small loading={saving} onClick={handleSubmit(save, onInvalid)}>Schedule</Button></>}>
            <Grid container columnSpacing={2}>
                <Grid item xs={12} sm={6}><RHFSelect name="staff_id" control={control} label="Staff member *" options={staff} rules={{ required: "Required" }} /></Grid>
                <Grid item xs={12} sm={6}><RHFTextField name="patient_name" control={control} label="Person visited *" rules={{ required: "Required" }} /></Grid>
                <Grid item xs={12} sm={4}><RHFSelect name="country" control={control} label="Country" options={countries} /></Grid>
                <Grid item xs={12} sm={4}><RHFTextField name="mobile" control={control} label="Mobile" /></Grid>
                <Grid item xs={12} sm={4}><RHFTextField name="email" control={control} label="Email" /></Grid>
                <Grid item xs={12}><RHFTextField name="patient_address" control={control} label="Address" /></Grid>
                <Grid item xs={6} sm={3}><RHFTextField name="date" control={control} label="Date *" type="date" rules={{ required: "Required" }} /></Grid>
                <Grid item xs={6} sm={3}><RHFTextField name="time" control={control} label="Time *" type="time" rules={{ required: "Required" }} /></Grid>
                <Grid item xs={12} sm={4}><RHFTextField name="visit_purpose" control={control} label="Purpose" /></Grid>
                <Grid item xs={12} sm={2}><RHFTextField name="visit_fees" control={control} label="Fees" type="number" /></Grid>
            </Grid>
        </Modal>
    );
};

/** Duty board: one day's duties, or everything from a date on. */
const DutyList = () => {
    const navigate = useNavigate();
    const { can } = usePermission();
    const [mode, setMode] = useState("day");
    const [date, setDate] = useState(today());
    const [booking, setBooking] = useState(null);
    const [shortVisit, setShortVisit] = useState(false);

    const { data: rows, loading } = useAsync(
        () => duties.list(mode === "day" ? { date } : { since: date }),
        [mode, date],
        []
    );

    const columns = useMemo(() => [
        { header: "Date", accessorKey: "date", cell: ({ row }) => fmtDate(row.original.date) },
        { header: "Time", id: "time", cell: ({ row }) => `${fmtTime(row.original.start_time)} – ${fmtTime(row.original.end_time)}` },
        {
            header: "Patient",
            accessorKey: "patient_name",
            cell: ({ row }) => (
                <button type="button" onClick={() => navigate(`/patients/${row.original.patient_id}`)} style={{ border: "none", background: "none", padding: 0, color: colors.brandDark, fontWeight: 600, cursor: "pointer" }}>
                    {row.original.patient_name ?? row.original.patient_id}
                </button>
            ),
        },
        {
            header: "Staff",
            id: "staff",
            accessorFn: (d) => d.staff_name ?? d.name ?? d.staff_id,
            cell: ({ row }) => <>{row.original.staff_name ?? row.original.name ?? row.original.staff_id}<div style={{ fontSize: 11, color: colors.muted }}>{humanize(row.original.role)}</div></>,
        },
        { header: "Driver", accessorKey: "driver", cell: ({ row }) => row.original.driver || "—" },
        { header: "Status", id: "status", cell: ({ row }) => dutyStatus(row.original) },
        {
            header: "Actions",
            id: "actions",
            cell: ({ row }) => {
                const d = row.original;
                const done = isYes(d.sign_out) || isYes(d.not_attended);
                return (
                    <ActionCell>
                        {can("schedules.manage") && !done && <TableBtn onClick={() => navigate(`/schedules/${d._id}/edit`)}>Reschedule</TableBtn>}
                        {can("schedules.manage") && !done && <TableBtn variant="secondary" onClick={() => setBooking({ ...d, staff_name: d.staff_name ?? d.name })}>Book driver</TableBtn>}
                    </ActionCell>
                );
            },
        },
    ], [can, navigate]);

    return (
        <>
            <PageHeader
                eyebrow="Scheduling"
                title="Duty Schedules"
                actions={can("schedules.manage") && (
                    <>
                        <Button variant="secondary" onClick={() => setShortVisit(true)}>+ Short visit</Button>
                        <Button onClick={() => navigate("/schedules/new")}>+ Schedule duty</Button>
                    </>
                )}
            />
            <StatStrip items={[
                { label: mode === "day" ? "Duties that day" : "Upcoming duties", value: rows.length },
                { label: "Completed", value: rows.filter((d) => isYes(d.sign_out)).length },
                { label: "In progress", value: rows.filter((d) => isYes(d.sign_in) && !isYes(d.sign_out)).length },
                { label: "Not attended", value: rows.filter((d) => isYes(d.not_attended)).length },
            ]} />
            <Card
                actions={
                    <>
                        <ToggleButtonGroup size="small" exclusive value={mode} onChange={(_, v) => v && setMode(v)}
                            sx={{ "& .MuiToggleButton-root": { textTransform: "none", fontSize: 12, py: 0.5, px: 1.5 }, "& .Mui-selected": { color: `${colors.brandDark} !important` } }}>
                            <ToggleButton value="day">One day</ToggleButton>
                            <ToggleButton value="since">From date on</ToggleButton>
                        </ToggleButtonGroup>
                        <TextField size="small" type="date" label={mode === "day" ? "Day" : "From"} value={date} onChange={(e) => setDate(e.target.value || today())} InputLabelProps={{ shrink: true }} />
                    </>
                }
                label="Duties"
            >
                <DataTable data={rows} columns={columns} searchPlaceholder="Search by patient or staff…" emptyMessage={loading ? "Loading…" : "No duties"} />
            </Card>
            {booking && <DriverDialog duty={booking} onClose={() => setBooking(null)} />}
            {shortVisit && <ShortVisitDialog onClose={() => setShortVisit(false)} />}
        </>
    );
};

export default DutyList;
