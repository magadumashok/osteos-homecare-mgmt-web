import React, { useEffect, useMemo, useState } from "react";
import { useNavigate, useParams, useSearchParams } from "react-router-dom";
import { useForm, useWatch } from "react-hook-form";
import { Checkbox, Chip, FormControlLabel, Grid, TextField, ToggleButton, ToggleButtonGroup } from "@mui/material";
import { toast } from "react-toastify";
import RHFTextField from "../forms/RHFTextField";
import RHFSelect from "../forms/RHFSelect";
import { Button, Card, Loader, PageHeader, colors } from "../common/ui";
import { careLogs, duties, patients as patientsApi, staff as staffApi } from "../../api/homecare";
import { errorMessage, fieldErrors } from "../../api/client";
import { fmtDate, humanize, today } from "../../utilities/format";
import { onInvalid } from "../../utilities/formErrors";
import { SERVICE_OPTIONS, serviceChoice, serviceValue } from "../../utilities/services";

const ROLES = ["nurse", "physiotherapist", "doctor", "caretaker"].map((r) => ({ value: r, label: humanize(r) }));
const BUFFERS = ["00:00", "00:15", "00:30", "00:45", "01:00", "01:30", "02:00"].map((v) => ({
    value: v,
    label: v === "00:00" ? "None" : v.replace(/^0/, "").replace(":", "h ") + "m",
}));
const STAYS = [1, 3, 6];
const SHIFT_HOURS = [5, 8, 10, 12];
const MAX_SHIFTS = 200; // about six months of daily shifts (the server's limit)

const minutes = (t) => {
    const m = /^(\d{1,2}):(\d{2})/.exec(t ?? "");
    return m ? Number(m[1]) * 60 + Number(m[2]) : null;
};
const hhmm = (total) => `${String(Math.floor(total / 60) % 24).padStart(2, "0")}:${String(total % 60).padStart(2, "0")}`;

// Local-date arithmetic ("YYYY-MM-DD"); toISOString() would shift to UTC.
const iso = (d) => `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, "0")}-${String(d.getDate()).padStart(2, "0")}`;
const addDays = (date, n) => {
    const [y, m, d] = date.split("-").map(Number);
    return iso(new Date(y, m - 1, d + n));
};

/** The last day of an n-month period starting on `date`. */
const monthsLater = (date, n) => {
    const [y, m, d] = date.split("-").map(Number);
    // Same day n months on (clamped to that month's length), less one day.
    const monthLength = new Date(y, m - 1 + n + 1, 0).getDate();
    return iso(new Date(y, m - 1 + n, Math.min(d, monthLength) - 1));
};

/** "HH:MM" length of a shift, allowing it to run past midnight. */
const duration = (start, end) => {
    const s = minutes(start);
    const e = minutes(end);
    if (s === null || e === null) return "";
    const total = (e - s + 1440) % 1440 || 1440;
    return `${String(Math.floor(total / 60)).padStart(2, "0")}:${String(total % 60).padStart(2, "0")}`;
};

/**
 * Schedule a duty (/schedules/new?patient=) or reschedule one (/schedules/:id/edit),
 * for any role:
 *  - Shift: set start and end times (5, 10, 12 hours…), on one day or every
 *    (or every other) day up to an end date — one duty per day, each signed
 *    in and out. A shift ending before it starts runs overnight.
 *  - 24 hours: the nurse or caretaker stays with the patient from a start to
 *    an end date, with daily tasks they log each day; no times, driver or sign in.
 * Service and fees default to the patient's registration and can be changed.
 */
const DutyForm = () => {
    const { id } = useParams();
    const [search] = useSearchParams();
    const isEdit = Boolean(id);
    const navigate = useNavigate();

    const [loading, setLoading] = useState(isEdit);
    const [saving, setSaving] = useState(false);
    const [patientRows, setPatientRows] = useState([]);
    const [staffList, setStaffList] = useState([]);
    const [drivers, setDrivers] = useState([]);
    const [alternate, setAlternate] = useState(false);
    const [taskOptions, setTaskOptions] = useState([]);
    const [careTasks, setCareTasks] = useState([]);
    const [customTask, setCustomTask] = useState("");

    const { control, handleSubmit, reset, setValue, setError } = useForm({
        defaultValues: {
            patient_id: search.get("patient") ?? "",
            role: "nurse",
            staff_id: "",
            driver: "",
            hours: "shift",
            date: today(),
            end_date: today(),
            start_time: "08:00",
            end_time: "20:00",
            buffer_time: "00:30",
            service: "",
            service_other: "",
            fees: "",
        },
    });
    const [role, hours, date, endDate, start, end, patientId, service] = useWatch({
        control,
        name: ["role", "hours", "date", "end_date", "start_time", "end_time", "patient_id", "service"],
    });
    const liveIn = hours === "24h";
    const overnight = minutes(end) !== null && minutes(start) !== null && minutes(end) <= minutes(start);

    useEffect(() => {
        patientsApi.list().then((list) => setPatientRows(list ?? [])).catch(() => {});
        staffApi
            .list({ role: "transport" })
            .then((list) => setDrivers([{ value: "", label: "No driver" }, ...list.map((d) => ({ value: d.name, label: d.name }))]))
            .catch(() => {});
        careLogs
            .tasks()
            .then((list) => setTaskOptions((current) => [...(list ?? []), ...current.filter((t) => !(list ?? []).includes(t))]))
            .catch(() => {});
    }, []);

    useEffect(() => {
        staffApi
            .list({ role })
            .then((list) => setStaffList(list.map((s) => ({ value: s.username, label: s.name }))))
            .catch(() => setStaffList([]));
    }, [role]);

    const patients = useMemo(
        () => patientRows.map((p) => ({ value: p.username, label: `${p.patient_name} (${p.username})` })),
        [patientRows],
    );

    // A new duty takes the service and fees the patient was registered with.
    useEffect(() => {
        if (isEdit) return;
        const patient = patientRows.find((p) => p.username === patientId);
        if (!patient) return;
        const choice = serviceChoice(patient.service);
        setValue("service", choice.service);
        setValue("service_other", choice.other);
        setValue("fees", patient.fees ?? "");
    }, [isEdit, patientId, patientRows, setValue]);

    useEffect(() => {
        if (!isEdit) return undefined;
        let active = true; // ignore a stale load (see AssessmentForm)
        duties
            .show(id)
            .then((d) => {
                if (!active) return;
                const choice = serviceChoice(d.service);
                reset({
                    patient_id: d.patient_id,
                    role: d.role ?? "nurse",
                    staff_id: d.staff_id,
                    driver: d.driver ?? "",
                    hours: d.live_in ? "24h" : "shift",
                    date: d.date,
                    end_date: d.end_date ?? d.date,
                    start_time: d.start_time,
                    end_time: d.end_time,
                    buffer_time: d.buffer_time ?? "00:00",
                    service: choice.service,
                    service_other: choice.other,
                    fees: d.fees ?? "",
                });
                const tasks = Array.isArray(d.care_tasks) ? d.care_tasks : [];
                setCareTasks(tasks);
                setTaskOptions((list) => [...list, ...tasks.filter((t) => !list.includes(t))]);
            })
            .catch((e) => active && toast.error(errorMessage(e)))
            .finally(() => active && setLoading(false));
        return () => {
            active = false;
        };
    }, [id, isEdit, reset]);

    const setHours = (value) => {
        if (!value) return;
        setValue("hours", value);
        if (value === "24h") {
            if (!endDate || endDate <= date) setValue("end_date", monthsLater(date, 1));
            // A new caretaker stay starts with every standard task ticked.
            if (!isEdit && !careTasks.length && role === "caretaker") setCareTasks(taskOptions);
        }
    };

    const toggleTask = (task) => setCareTasks((list) => (list.includes(task) ? list.filter((t) => t !== task) : [...list, task]));
    const addCustomTask = () => {
        const task = customTask.trim();
        if (!task) return;
        if (!taskOptions.includes(task)) setTaskOptions((list) => [...list, task]);
        if (!careTasks.includes(task)) setCareTasks((list) => [...list, task]);
        setCustomTask("");
    };

    // Shift days from the start date to the end date, every day or every other day.
    const shiftDays = useMemo(() => {
        if (liveIn || !date || !endDate || endDate < date) return [];
        const list = [];
        for (let d = date; d <= endDate && list.length <= MAX_SHIFTS; d = addDays(d, alternate ? 2 : 1)) list.push(d);
        return list;
    }, [liveIn, date, endDate, alternate]);

    const onSubmit = async (v) => {
        if (!v.end_date || v.end_date < v.date) {
            setError("end_date", { message: "Must be on or after the start date" });
            return;
        }
        if (!liveIn && shiftDays.length > MAX_SHIFTS) {
            toast.error(`At most ${MAX_SHIFTS} shifts can be scheduled at once. Choose an earlier end date.`);
            return;
        }
        if (v.service === "other" && !v.service_other.trim()) {
            setError("service_other", { message: "Enter the service" });
            return;
        }

        const common = {
            staff_id: v.staff_id,
            role: v.role,
            service: serviceValue(v.service, v.service_other),
            fees: v.fees === "" ? null : Number(v.fees),
        };

        setSaving(true);
        try {
            if (liveIn) {
                const stay = { ...common, live_in: true, date: v.date, end_date: v.end_date, care_tasks: careTasks };
                if (isEdit) await duties.reschedule(id, stay);
                else await duties.create({ ...stay, patient_id: v.patient_id });
                toast.success(isEdit ? "24-hour duty updated." : "24-hour duty scheduled. Staff and patient are notified on WhatsApp.");
            } else {
                const shift = {
                    ...common,
                    live_in: false,
                    start_time: v.start_time,
                    end_time: v.end_time,
                    buffer_time: v.buffer_time,
                    schedule_hour: duration(v.start_time, v.end_time),
                };
                const endOf = (d) => (overnight ? addDays(d, 1) : d);
                if (isEdit) {
                    await duties.reschedule(id, { ...shift, driver: v.driver || null, date: v.date, end_date: endOf(v.date) });
                    toast.success("Duty rescheduled. Staff and patient are notified on WhatsApp.");
                } else if (shiftDays.length > 1) {
                    await duties.createSeries({
                        ...shift,
                        patient_id: v.patient_id,
                        ranges: shiftDays.map((d) => ({ start_date: d, end_date: endOf(d) })),
                    });
                    toast.success(`${shiftDays.length} shifts scheduled.`);
                } else {
                    await duties.create({ ...shift, patient_id: v.patient_id, driver: v.driver || null, date: v.date, end_date: endOf(v.date) });
                    toast.success("Duty scheduled. Staff, patient and driver are notified on WhatsApp.");
                }
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

    const toggleSx = { "& .MuiToggleButton-root": { textTransform: "none", fontSize: 13, px: 2 }, "& .Mui-selected": { color: `${colors.brandDark} !important` } };

    return (
        <div style={{ maxWidth: 860 }}>
            <PageHeader
                eyebrow="Scheduling"
                title={isEdit ? "Reschedule Duty" : "Schedule Duty"}
                subtitle="Clashes are refused: staff already on duty elsewhere, or a patient with two staff of the same role at once. A nurse and a caretaker can be with the same patient together."
            />
            <form onSubmit={handleSubmit(onSubmit, onInvalid)} noValidate>
                <Card label="Who">
                    <Grid container columnSpacing={2}>
                        <Grid item xs={12}>
                            <RHFSelect name="patient_id" control={control} label="Patient *" options={patients} rules={{ required: "Choose a patient" }} disabled={isEdit} />
                        </Grid>
                        <Grid item xs={12} sm={4}>
                            <RHFSelect name="role" control={control} label="Role *" options={ROLES} onChange={() => setValue("staff_id", "")} />
                        </Grid>
                        <Grid item xs={12} sm={liveIn ? 8 : 4}>
                            <RHFSelect name="staff_id" control={control} label={`${humanize(role)} *`} options={staffList} rules={{ required: "Choose a staff member" }} />
                        </Grid>
                        {!liveIn && (
                            <Grid item xs={12} sm={4}>
                                <RHFSelect name="driver" control={control} label="Driver" options={drivers} disabled={shiftDays.length > 1} />
                            </Grid>
                        )}
                        <Grid item xs={12} sm={service === "other" ? 4 : 8}>
                            <RHFSelect name="service" control={control} label="Service" options={SERVICE_OPTIONS} />
                        </Grid>
                        {service === "other" && (
                            <Grid item xs={12} sm={4}>
                                <RHFTextField name="service_other" control={control} label="Service name *" />
                            </Grid>
                        )}
                        <Grid item xs={12} sm={4}>
                            <RHFTextField name="fees" control={control} label="Fees" type="number" />
                        </Grid>
                    </Grid>
                </Card>

                <Card label="Duty hours" style={{ marginTop: 16 }}>
                    <ToggleButtonGroup size="small" exclusive value={hours} onChange={(_, value) => setHours(value)} sx={toggleSx}>
                        <ToggleButton value="shift">Shift (set hours)</ToggleButton>
                        <ToggleButton value="24h">24 hours</ToggleButton>
                    </ToggleButtonGroup>
                    <p style={{ fontSize: 13, color: colors.body, margin: "8px 0 12px" }}>
                        {liveIn
                            ? "Stays with the patient round the clock from the start to the end date. No sign in or out; they send a daily update instead."
                            : "Works set hours each day and signs in and out every day (with location)."}
                    </p>

                    <Grid container columnSpacing={2}>
                        <Grid item xs={6} sm={3}>
                            <RHFTextField name="date" control={control} label="Start date *" type="date" rules={{ required: "Required" }} />
                        </Grid>
                        <Grid item xs={6} sm={3}>
                            <RHFTextField name="end_date" control={control} label={liveIn ? "End date *" : "Last day *"} type="date" rules={{ required: "Required" }} disabled={isEdit && !liveIn} />
                        </Grid>
                        {!liveIn && (
                            <>
                                <Grid item xs={6} sm={3}>
                                    <RHFTextField name="start_time" control={control} label="Shift starts *" type="time" rules={{ required: "Required" }} />
                                </Grid>
                                <Grid item xs={6} sm={3}>
                                    <RHFTextField name="end_time" control={control} label="Shift ends *" type="time" rules={{ required: "Required" }} />
                                </Grid>
                            </>
                        )}
                    </Grid>

                    <div style={{ display: "flex", gap: 8, alignItems: "center", flexWrap: "wrap", marginTop: 4 }}>
                        {(!isEdit || liveIn) &&
                            STAYS.map((n) => (
                                <Chip
                                    key={`m${n}`}
                                    size="small"
                                    label={`${n} month${n > 1 ? "s" : ""}`}
                                    variant={endDate === monthsLater(date, n) ? "filled" : "outlined"}
                                    color={endDate === monthsLater(date, n) ? "primary" : "default"}
                                    onClick={() => setValue("end_date", monthsLater(date, n))}
                                />
                            ))}
                        {!liveIn &&
                            SHIFT_HOURS.map((h) => {
                                const target = minutes(start) !== null ? hhmm(minutes(start) + h * 60) : null;
                                return (
                                    <Chip
                                        key={`h${h}`}
                                        size="small"
                                        label={`${h} h shift`}
                                        variant={target === end ? "filled" : "outlined"}
                                        color={target === end ? "primary" : "default"}
                                        onClick={() => target && setValue("end_time", target)}
                                    />
                                );
                            })}
                    </div>

                    {liveIn ? (
                        <div style={{ fontSize: 13, color: colors.body, marginTop: 8 }}>
                            {endDate >= date ? `${fmtDate(date)} → ${fmtDate(endDate)} · shown to them every day until the end date` : "End date is before the start date"}
                        </div>
                    ) : (
                        <>
                            <Grid container columnSpacing={2} sx={{ mt: 1 }}>
                                <Grid item xs={6} sm={3}>
                                    <RHFSelect name="buffer_time" control={control} label="Travel buffer" options={BUFFERS} />
                                </Grid>
                                {!isEdit && endDate > date && (
                                    <Grid item xs={6} sm={9} sx={{ display: "flex", alignItems: "center" }}>
                                        <FormControlLabel
                                            control={<Checkbox size="small" checked={alternate} onChange={(e) => setAlternate(e.target.checked)} />}
                                            label={<span style={{ fontSize: 13 }}>Alternate days only</span>}
                                        />
                                    </Grid>
                                )}
                            </Grid>
                            <div style={{ fontSize: 13, color: colors.body, marginTop: 4 }}>
                                {duration(start, end) ? `${duration(start, end).replace(":", "h ")}m shift` : "—"}
                                {overnight && " · ends the next morning"}
                                {!isEdit &&
                                    (shiftDays.length > MAX_SHIFTS
                                        ? ` · more than ${MAX_SHIFTS} shifts, choose an earlier last day`
                                        : shiftDays.length > 1
                                          ? ` · ${shiftDays.length} shifts, ${fmtDate(shiftDays[0])} → ${fmtDate(shiftDays[shiftDays.length - 1])} (drivers are booked separately)`
                                          : "")}
                            </div>
                        </>
                    )}
                </Card>

                {liveIn && (
                    <Card label="Daily tasks" style={{ marginTop: 16 }}>
                        <p style={{ fontSize: 13, color: colors.body, marginTop: 0 }}>Ticked off in the daily update (optional).</p>
                        <div style={{ display: "flex", flexWrap: "wrap", gap: "0 16px" }}>
                            {taskOptions.map((task) => (
                                <FormControlLabel
                                    key={task}
                                    control={<Checkbox size="small" checked={careTasks.includes(task)} onChange={() => toggleTask(task)} />}
                                    label={<span style={{ fontSize: 13 }}>{task}</span>}
                                />
                            ))}
                        </div>
                        <div style={{ display: "flex", gap: 8, marginTop: 8, maxWidth: 420 }}>
                            <TextField
                                size="small"
                                fullWidth
                                label="Other task"
                                value={customTask}
                                onChange={(e) => setCustomTask(e.target.value)}
                                onKeyDown={(e) => {
                                    if (e.key === "Enter") {
                                        e.preventDefault();
                                        addCustomTask();
                                    }
                                }}
                            />
                            <Button variant="secondary" small onClick={addCustomTask} disabled={!customTask.trim()}>
                                Add
                            </Button>
                        </div>
                    </Card>
                )}

                <div style={{ display: "flex", justifyContent: "flex-end", gap: 12, marginTop: 24 }}>
                    <Button variant="secondary" onClick={() => navigate(-1)} disabled={saving}>
                        Cancel
                    </Button>
                    <Button type="submit" loading={saving} disabled={!patientId && !isEdit}>
                        {isEdit ? "Reschedule" : liveIn ? "Schedule 24-hour duty" : shiftDays.length > 1 ? `Schedule ${shiftDays.length} shifts` : "Schedule shift"}
                    </Button>
                </div>
            </form>
        </div>
    );
};

export default DutyForm;
