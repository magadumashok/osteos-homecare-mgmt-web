import React, { useEffect, useMemo, useState } from "react";
import { useNavigate, useParams, useSearchParams } from "react-router-dom";
import { useForm, useWatch } from "react-hook-form";
import { Checkbox, Chip, FormControlLabel, Grid, TextField } from "@mui/material";
import { toast } from "react-toastify";
import RHFTextField from "../forms/RHFTextField";
import RHFSelect from "../forms/RHFSelect";
import { Button, Card, Loader, PageHeader, colors } from "../common/ui";
import {
    careLogs,
    duties,
    patients as patientsApi,
    staff as staffApi,
} from "../../api/homecare";
import { errorMessage, fieldErrors } from "../../api/client";
import { fmtDate, humanize, today } from "../../utilities/format";
import { onInvalid } from "../../utilities/formErrors";

const ROLES = ["nurse", "physiotherapist", "doctor", "caretaker"].map((r) => ({
    value: r,
    label: humanize(r),
}));
const BUFFERS = [
    "00:00",
    "00:15",
    "00:30",
    "00:45",
    "01:00",
    "01:30",
    "02:00",
].map((v) => ({
    value: v,
    label:
        v === "00:00" ? "None" : v.replace(/^0/, "").replace(":", "h ") + "m",
}));

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

/** The last day of an n-month stay starting on `date`. */
const monthsLater = (date, n) => {
    const [y, m, d] = date.split("-").map(Number);
    // Same day n months on (clamped to that month's length), less one day.
    const monthLength = new Date(y, m - 1 + n + 1, 0).getDate();
    const next = new Date(y, m - 1 + n, Math.min(d, monthLength) - 1);
    return `${next.getFullYear()}-${String(next.getMonth() + 1).padStart(2, "0")}-${String(next.getDate()).padStart(2, "0")}`;
};

// Caretakers stay with the patient around the clock between two dates.
const LIVE_IN_ROLES = ["caretaker"];
const STAYS = [1, 3, 6];

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
 * A caretaker duty is one 24-hour stay from a start to an end date, with
 * the care tasks the caretaker logs each day; it has no times or driver.
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
    const [taskOptions, setTaskOptions] = useState([]);
    const [careTasks, setCareTasks] = useState([]);
    const [customTask, setCustomTask] = useState("");

    const { control, handleSubmit, reset, setValue, setError } = useForm({
        defaultValues: {
            patient_id: search.get("patient") ?? "",
            role: "nurse",
            staff_id: "",
            driver: "",
            date: today(),
            end_date: "",
            start_time: "09:00",
            end_time: "17:00",
            buffer_time: "00:30",
        },
    });
    const [role, date, start, end, patientId, endDate] = useWatch({
        control,
        name: ["role", "date", "start_time", "end_time", "patient_id", "end_date"],
    });
    const liveIn = LIVE_IN_ROLES.includes(role);

    useEffect(() => {
        careLogs
            .tasks()
            .then((list) => {
                setTaskOptions(list ?? []);
                // A new caretaker duty starts with every standard task ticked.
                if (!isEdit) setCareTasks(list ?? []);
            })
            .catch(() => {});
    }, [isEdit]);

    // Default a new stay to one month.
    useEffect(() => {
        if (liveIn && !endDate && date) setValue("end_date", monthsLater(date, 1));
    }, [liveIn, endDate, date, setValue]);

    const toggleTask = (task) =>
        setCareTasks((list) =>
            list.includes(task) ? list.filter((t) => t !== task) : [...list, task],
        );
    const addCustomTask = () => {
        const task = customTask.trim();
        if (!task) return;
        if (!taskOptions.includes(task)) setTaskOptions((list) => [...list, task]);
        if (!careTasks.includes(task)) setCareTasks((list) => [...list, task]);
        setCustomTask("");
    };

    useEffect(() => {
        patientsApi
            .list()
            .then((list) =>
                setPatients(
                    list.map((p) => ({
                        value: p.username,
                        label: `${p.patient_name} (${p.username})`,
                    })),
                ),
            )
            .catch(() => {});
        staffApi
            .list({ role: "transport" })
            .then((list) =>
                setDrivers([
                    { value: "", label: "No driver" },
                    ...list.map((d) => ({ value: d.name, label: d.name })),
                ]),
            )
            .catch(() => {});
    }, []);

    useEffect(() => {
        staffApi
            .list({ role })
            .then((list) =>
                setStaffList(
                    list.map((s) => ({ value: s.username, label: s.name })),
                ),
            )
            .catch(() => setStaffList([]));
    }, [role]);

    useEffect(() => {
        if (!isEdit) return undefined;
        let active = true; // ignore a stale load (see AssessmentForm)
        duties
            .show(id)
            .then((d) => {
                if (!active) return;
                reset({
                    patient_id: d.patient_id,
                    role: d.role ?? "nurse",
                    staff_id: d.staff_id,
                    driver: d.driver ?? "",
                    date: d.date,
                    end_date: d.end_date ?? d.date,
                    start_time: d.start_time,
                    end_time: d.end_time,
                    buffer_time: d.buffer_time ?? "00:00",
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

    const overnight =
        minutes(end) !== null &&
        minutes(start) !== null &&
        minutes(end) <= minutes(start);
    const endDateFor = (d) => (overnight ? addDays(d, 1) : d);
    const days = useMemo(() => {
        if (!repeat || !repeatUntil || repeatUntil < date) return [];
        const list = [];
        for (
            let d = date;
            d <= repeatUntil && list.length < 62;
            d = addDays(d, 1)
        )
            list.push(d);
        return list;
    }, [repeat, repeatUntil, date]);

    const onSubmit = async (v) => {
        const base = {
            staff_id: v.staff_id,
            role: v.role,
            start_time: v.start_time,
            end_time: v.end_time,
            buffer_time: v.buffer_time,
            schedule_hour: duration(v.start_time, v.end_time),
        };

        if (liveIn) {
            if (!v.end_date || v.end_date < v.date) {
                setError("end_date", { message: "Must be on or after the start date" });
                return;
            }
            if (!careTasks.length) {
                toast.error("Choose at least one care task.");
                return;
            }
        }

        setSaving(true);
        try {
            if (liveIn) {
                const stay = {
                    staff_id: v.staff_id,
                    role: v.role,
                    date: v.date,
                    end_date: v.end_date,
                    care_tasks: careTasks,
                };
                if (isEdit) {
                    await duties.reschedule(id, stay);
                    toast.success("Caretaker duty updated.");
                } else {
                    await duties.create({ ...stay, patient_id: v.patient_id });
                    toast.success(
                        "Caretaker scheduled. Caretaker and patient are notified on WhatsApp.",
                    );
                }
            } else if (isEdit) {
                await duties.reschedule(id, {
                    ...base,
                    driver: v.driver || null,
                    date: v.date,
                    end_date: endDateFor(v.date),
                });
                toast.success(
                    "Duty rescheduled. Staff and patient are notified on WhatsApp.",
                );
            } else if (days.length > 1) {
                await duties.createSeries({
                    ...base,
                    patient_id: v.patient_id,
                    ranges: days.map((d) => ({
                        start_date: d,
                        end_date: endDateFor(d),
                    })),
                });
                toast.success(`${days.length} duties scheduled.`);
            } else {
                await duties.create({
                    ...base,
                    patient_id: v.patient_id,
                    driver: v.driver || null,
                    date: v.date,
                    end_date: endDateFor(v.date),
                });
                toast.success(
                    "Duty scheduled. Staff, patient and driver are notified on WhatsApp.",
                );
            }
            navigate(
                v.patient_id
                    ? `/patients/${v.patient_id}?tab=schedules`
                    : "/schedules",
            );
        } catch (e) {
            Object.entries(fieldErrors(e)).forEach(([field, messages]) =>
                setError(field, { message: messages[0] }),
            );
            toast.error(errorMessage(e));
        } finally {
            setSaving(false);
        }
    };

    if (loading) return <Loader />;

    return (
        <div style={{ maxWidth: 860 }}>
            <PageHeader
                eyebrow="Scheduling"
                title={isEdit ? "Reschedule Duty" : "Schedule Duty"}
                subtitle="Clashes with the patient's or the staff member's other duties (including travel buffer) are refused."
            />
            <form onSubmit={handleSubmit(onSubmit, onInvalid)} noValidate>
                <Card label="Who">
                    <Grid container columnSpacing={2}>
                        <Grid item xs={12}>
                            <RHFSelect
                                name="patient_id"
                                control={control}
                                label="Patient *"
                                options={patients}
                                rules={{ required: "Choose a patient" }}
                                disabled={isEdit}
                            />
                        </Grid>
                        <Grid item xs={12} sm={4}>
                            <RHFSelect
                                name="role"
                                control={control}
                                label="Role *"
                                options={ROLES}
                                onChange={() => setValue("staff_id", "")}
                            />
                        </Grid>
                        <Grid item xs={12} sm={4}>
                            <RHFSelect
                                name="staff_id"
                                control={control}
                                label={`${humanize(role)} *`}
                                options={staffList}
                                rules={{ required: "Choose a staff member" }}
                            />
                        </Grid>
                        {!liveIn && (
                            <Grid item xs={12} sm={4}>
                                <RHFSelect
                                    name="driver"
                                    control={control}
                                    label="Driver"
                                    options={drivers}
                                    disabled={days.length > 1}
                                />
                            </Grid>
                        )}
                    </Grid>
                </Card>

                {liveIn && (
                    <>
                        <Card label="Stay (24-hour care)" style={{ marginTop: 16 }}>
                            <Grid container columnSpacing={2}>
                                <Grid item xs={6} sm={4}>
                                    <RHFTextField
                                        name="date"
                                        control={control}
                                        label="Start date *"
                                        type="date"
                                        rules={{ required: "Required" }}
                                    />
                                </Grid>
                                <Grid item xs={6} sm={4}>
                                    <RHFTextField
                                        name="end_date"
                                        control={control}
                                        label="End date *"
                                        type="date"
                                        rules={{ required: "Required" }}
                                    />
                                </Grid>
                            </Grid>
                            <div style={{ display: "flex", gap: 8, alignItems: "center", flexWrap: "wrap", marginTop: 4 }}>
                                {STAYS.map((n) => (
                                    <Chip
                                        key={n}
                                        size="small"
                                        label={`${n} month${n > 1 ? "s" : ""}`}
                                        variant={endDate === monthsLater(date, n) ? "filled" : "outlined"}
                                        color={endDate === monthsLater(date, n) ? "primary" : "default"}
                                        onClick={() => setValue("end_date", monthsLater(date, n))}
                                    />
                                ))}
                                <span style={{ fontSize: 13, color: colors.body }}>
                                    {endDate >= date
                                        ? `${fmtDate(date)} → ${fmtDate(endDate)} · the caretaker sees this duty every day until the end date`
                                        : "End date is before the start date"}
                                </span>
                            </div>
                        </Card>

                        <Card label="Care tasks" style={{ marginTop: 16 }}>
                            <p style={{ fontSize: 13, color: colors.body, marginTop: 0 }}>
                                The caretaker ticks these off in their daily update.
                            </p>
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
                    </>
                )}

                {!liveIn && (
                <Card label="When" style={{ marginTop: 16 }}>
                    <Grid container columnSpacing={2}>
                        <Grid item xs={6} sm={3}>
                            <RHFTextField
                                name="date"
                                control={control}
                                label="Date *"
                                type="date"
                                rules={{ required: "Required" }}
                            />
                        </Grid>
                        <Grid item xs={6} sm={3}>
                            <RHFTextField
                                name="start_time"
                                control={control}
                                label="Start *"
                                type="time"
                                rules={{ required: "Required" }}
                            />
                        </Grid>
                        <Grid item xs={6} sm={3}>
                            <RHFTextField
                                name="end_time"
                                control={control}
                                label="End *"
                                type="time"
                                rules={{ required: "Required" }}
                            />
                        </Grid>
                        <Grid item xs={6} sm={3}>
                            <RHFSelect
                                name="buffer_time"
                                control={control}
                                label="Travel buffer"
                                options={BUFFERS}
                            />
                        </Grid>
                    </Grid>
                    <div
                        style={{
                            fontSize: 13,
                            color: colors.body,
                            marginTop: 4,
                        }}
                    >
                        Duration {duration(start, end) || "—"}
                        {overnight &&
                            ` · ends next day (${fmtDate(endDateFor(date))})`}
                    </div>
                    {!isEdit && (
                        <div
                            style={{
                                display: "flex",
                                alignItems: "center",
                                gap: 16,
                                marginTop: 8,
                                flexWrap: "wrap",
                            }}
                        >
                            <FormControlLabel
                                control={
                                    <Checkbox
                                        size="small"
                                        checked={repeat}
                                        onChange={(e) =>
                                            setRepeat(e.target.checked)
                                        }
                                    />
                                }
                                label={
                                    <span style={{ fontSize: 13 }}>
                                        Repeat daily
                                    </span>
                                }
                            />
                            {repeat && (
                                <>
                                    <input
                                        type="date"
                                        value={repeatUntil}
                                        min={date}
                                        onChange={(e) =>
                                            setRepeatUntil(e.target.value)
                                        }
                                        style={{
                                            padding: 8,
                                            borderRadius: 8,
                                            border: `1px solid ${colors.line}`,
                                        }}
                                    />
                                    <span
                                        style={{
                                            fontSize: 12,
                                            color: colors.muted,
                                        }}
                                    >
                                        {days.length
                                            ? `${days.length} duties (drivers are booked separately)`
                                            : "Choose the last day"}
                                    </span>
                                </>
                            )}
                        </div>
                    )}
                </Card>
                )}

                <div
                    style={{
                        display: "flex",
                        justifyContent: "flex-end",
                        gap: 12,
                        marginTop: 24,
                    }}
                >
                    <Button
                        variant="secondary"
                        onClick={() => navigate(-1)}
                        disabled={saving}
                    >
                        Cancel
                    </Button>
                    <Button
                        type="submit"
                        loading={saving}
                        disabled={!patientId && !isEdit}
                    >
                        {isEdit
                            ? "Reschedule"
                            : liveIn
                              ? "Schedule caretaker"
                              : days.length > 1
                              ? `Schedule ${days.length} duties`
                              : "Schedule duty"}
                    </Button>
                </div>
            </form>
        </div>
    );
};

export default DutyForm;
