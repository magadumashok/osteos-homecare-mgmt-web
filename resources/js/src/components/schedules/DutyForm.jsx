import React, { useEffect, useMemo, useState } from "react";
import { useNavigate, useParams, useSearchParams } from "react-router-dom";
import { useForm, useWatch } from "react-hook-form";
import { Checkbox, FormControlLabel, Grid } from "@mui/material";
import { toast } from "react-toastify";
import RHFTextField from "../forms/RHFTextField";
import RHFSelect from "../forms/RHFSelect";
import { Button, Card, Loader, PageHeader, colors } from "../common/ui";
import { duties, patients as patientsApi, staff as staffApi } from "../../api/homecare";
import { errorMessage, fieldErrors } from "../../api/client";
import { fmtDate, humanize, today } from "../../utilities/format";
import { onInvalid } from "../../utilities/formErrors";

const ROLES = ["nurse", "physiotherapist", "doctor"].map((r) => ({ value: r, label: humanize(r) }));
const BUFFERS = ["00:00", "00:15", "00:30", "00:45", "01:00", "01:30", "02:00"].map((v) => ({ value: v, label: v === "00:00" ? "None" : v.replace(/^0/, "").replace(":", "h ") + "m" }));

const minutes = (t) => {
    const m = /^(\d{1,2}):(\d{2})/.exec(t ?? "");
    return m ? Number(m[1]) * 60 + Number(m[2]) : null;
};

// Local-date arithmetic ("YYYY-MM-DD"); toISOString() would shift to UTC.
const addDays = (date, n) => {
    const [y, m, d] = date.split("-").map(Number);
    const next = new Date(y, m - 1, d + n);
    return `${next.getFullYear()}-${String(next.getMonth() + 1).padStart(2, "0")}-${String(next.getDate()).padStart(2, "0")}`;
};

/** "HH:MM" length of the duty, allowing it to run past midnight. */
const duration = (start, end) => {
    const s = minutes(start);
    const e = minutes(end);
    if (s === null || e === null) return "";
    const total = (e - s + 1440) % 1440 || 1440;
    return `${String(Math.floor(total / 60)).padStart(2, "0")}:${String(total % 60).padStart(2, "0")}`;
};

/**
 * Schedule a duty (/schedules/new?patient=) or reschedule one (/schedules/:id/edit).
 * A duty ending before it starts runs overnight into the next day. "Repeat
 * daily" creates one duty per day; every day is checked for clashes first.
 */
const DutyForm = () => {
    const { id } = useParams();
    const [search] = useSearchParams();
    const isEdit = Boolean(id);
    const navigate = useNavigate();

    const [loading, setLoading] = useState(isEdit);
    const [saving, setSaving] = useState(false);
    const [patients, setPatients] = useState([]);
    const [staffList, setStaffList] = useState([]);
    const [drivers, setDrivers] = useState([]);
    const [repeat, setRepeat] = useState(false);
    const [repeatUntil, setRepeatUntil] = useState("");

    const { control, handleSubmit, reset, setValue, setError } = useForm({
        defaultValues: {
            patient_id: search.get("patient") ?? "", role: "nurse", staff_id: "", driver: "",
            date: today(), start_time: "09:00", end_time: "17:00", buffer_time: "00:30",
        },
    });
    const [role, date, start, end, patientId] = useWatch({ control, name: ["role", "date", "start_time", "end_time", "patient_id"] });

    useEffect(() => {
        patientsApi.list().then((list) => setPatients(list.map((p) => ({ value: p.username, label: `${p.patient_name} (${p.username})` })))).catch(() => {});
        staffApi.list({ role: "transport" }).then((list) => setDrivers([{ value: "", label: "No driver" }, ...list.map((d) => ({ value: d.name, label: d.name }))])).catch(() => {});
    }, []);

    useEffect(() => {
        staffApi.list({ role }).then((list) => setStaffList(list.map((s) => ({ value: s.username, label: s.name })))).catch(() => setStaffList([]));
    }, [role]);

    useEffect(() => {
        if (!isEdit) return undefined;
        let active = true; // ignore a stale load (see AssessmentForm)
        duties.show(id)
            .then((d) => active && reset({
                patient_id: d.patient_id, role: d.role ?? "nurse", staff_id: d.staff_id, driver: d.driver ?? "",
                date: d.date, start_time: d.start_time, end_time: d.end_time, buffer_time: d.buffer_time ?? "00:00",
            }))
            .catch((e) => active && toast.error(errorMessage(e)))
            .finally(() => active && setLoading(false));
        return () => { active = false; };
    }, [id, isEdit, reset]);

    const overnight = minutes(end) !== null && minutes(start) !== null && minutes(end) <= minutes(start);
    const endDateFor = (d) => (overnight ? addDays(d, 1) : d);
    const days = useMemo(() => {
        if (!repeat || !repeatUntil || repeatUntil < date) return [];
        const list = [];
        for (let d = date; d <= repeatUntil && list.length < 62; d = addDays(d, 1)) list.push(d);
        return list;
    }, [repeat, repeatUntil, date]);

    const onSubmit = async (v) => {
        const base = {
            staff_id: v.staff_id, role: v.role, start_time: v.start_time, end_time: v.end_time,
            buffer_time: v.buffer_time, schedule_hour: duration(v.start_time, v.end_time),
        };

        setSaving(true);
        try {
            if (isEdit) {
                await duties.reschedule(id, { ...base, driver: v.driver || null, date: v.date, end_date: endDateFor(v.date) });
                toast.success("Duty rescheduled. Staff and patient are notified on WhatsApp.");
            } else if (days.length > 1) {
                await duties.createSeries({ ...base, patient_id: v.patient_id, ranges: days.map((d) => ({ start_date: d, end_date: endDateFor(d) })) });
                toast.success(`${days.length} duties scheduled.`);
            } else {
                await duties.create({ ...base, patient_id: v.patient_id, driver: v.driver || null, date: v.date, end_date: endDateFor(v.date) });
                toast.success("Duty scheduled. Staff, patient and driver are notified on WhatsApp.");
            }
            navigate(v.patient_id ? `/patients/${v.patient_id}?tab=schedules` : "/schedules");
        } catch (e) {
            Object.entries(fieldErrors(e)).forEach(([field, messages]) => setError(field, { message: messages[0] }));
            toast.error(errorMessage(e));
        } finally {
            setSaving(false);
        }
    };

    if (loading) return <Loader />;

    return (
        <div style={{ maxWidth: 860 }}>
            <PageHeader eyebrow="Scheduling" title={isEdit ? "Reschedule Duty" : "Schedule Duty"} subtitle="Clashes with the patient's or the staff member's other duties (including travel buffer) are refused." />
            <form onSubmit={handleSubmit(onSubmit, onInvalid)} noValidate>
                <Card label="Who">
                    <Grid container columnSpacing={2}>
                        <Grid item xs={12}>
                            <RHFSelect name="patient_id" control={control} label="Patient *" options={patients} rules={{ required: "Choose a patient" }} disabled={isEdit} />
                        </Grid>
                        <Grid item xs={12} sm={4}>
                            <RHFSelect name="role" control={control} label="Role *" options={ROLES} onChange={() => setValue("staff_id", "")} />
                        </Grid>
                        <Grid item xs={12} sm={4}>
                            <RHFSelect name="staff_id" control={control} label={`${humanize(role)} *`} options={staffList} rules={{ required: "Choose a staff member" }} />
                        </Grid>
                        <Grid item xs={12} sm={4}>
                            <RHFSelect name="driver" control={control} label="Driver" options={drivers} disabled={days.length > 1} />
                        </Grid>
                    </Grid>
                </Card>

                <Card label="When" style={{ marginTop: 16 }}>
                    <Grid container columnSpacing={2}>
                        <Grid item xs={6} sm={3}><RHFTextField name="date" control={control} label="Date *" type="date" rules={{ required: "Required" }} /></Grid>
                        <Grid item xs={6} sm={3}><RHFTextField name="start_time" control={control} label="Start *" type="time" rules={{ required: "Required" }} /></Grid>
                        <Grid item xs={6} sm={3}><RHFTextField name="end_time" control={control} label="End *" type="time" rules={{ required: "Required" }} /></Grid>
                        <Grid item xs={6} sm={3}><RHFSelect name="buffer_time" control={control} label="Travel buffer" options={BUFFERS} /></Grid>
                    </Grid>
                    <div style={{ fontSize: 13, color: colors.body, marginTop: 4 }}>
                        Duration {duration(start, end) || "—"}{overnight && ` · ends next day (${fmtDate(endDateFor(date))})`}
                    </div>
                    {!isEdit && (
                        <div style={{ display: "flex", alignItems: "center", gap: 16, marginTop: 8, flexWrap: "wrap" }}>
                            <FormControlLabel control={<Checkbox size="small" checked={repeat} onChange={(e) => setRepeat(e.target.checked)} />} label={<span style={{ fontSize: 13 }}>Repeat daily</span>} />
                            {repeat && (
                                <>
                                    <input type="date" value={repeatUntil} min={date} onChange={(e) => setRepeatUntil(e.target.value)} style={{ padding: 8, borderRadius: 8, border: `1px solid ${colors.line}` }} />
                                    <span style={{ fontSize: 12, color: colors.muted }}>{days.length ? `${days.length} duties (drivers are booked separately)` : "Choose the last day"}</span>
                                </>
                            )}
                        </div>
                    )}
                </Card>

                <div style={{ display: "flex", justifyContent: "flex-end", gap: 12, marginTop: 24 }}>
                    <Button variant="secondary" onClick={() => navigate(-1)} disabled={saving}>Cancel</Button>
                    <Button type="submit" loading={saving} disabled={!patientId && !isEdit}>{isEdit ? "Reschedule" : days.length > 1 ? `Schedule ${days.length} duties` : "Schedule duty"}</Button>
                </div>
            </form>
        </div>
    );
};

export default DutyForm;
