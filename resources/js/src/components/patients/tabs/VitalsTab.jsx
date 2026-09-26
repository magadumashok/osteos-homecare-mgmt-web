import React, { useMemo, useState } from "react";
import { useForm } from "react-hook-form";
import { Grid, MenuItem, TextField } from "@mui/material";
import { toast } from "react-toastify";
import DataTable from "../../table/DataTable";
import RHFTextField from "../../forms/RHFTextField";
import { Button, Card, Modal } from "../../common/ui";
import useAsync from "../../../hooks/useAsync";
import usePermission from "../../../hooks/usePermission";
import { patients as patientsApi } from "../../../api/homecare";
import { errorMessage } from "../../../api/client";
import { compact, fmtDate, fmtTime, nowTime, today } from "../../../utilities/format";
import { onInvalid } from "../../../utilities/formErrors";

const RANGES = [
    ["today", "Today"],
    ["this_week", "Last 7 days"],
    ["this_month", "This month"],
    ["all_time", "All time"],
];

const MEASURES = [
    ["patient_bp_systolic_value", "BP systolic"],
    ["patient_bp_diastolic_value", "BP diastolic"],
    ["pulse", "Pulse (bpm)"],
    ["temperature", "Temperature"],
    ["resp", "Respiration (/min)"],
    ["sat_O2", "SpO₂ (%)"],
    ["intake", "Intake"],
    ["output", "Output"],
];

const VitalsDialog = ({ patient, reading, onClose, onSaved }) => {
    const [saving, setSaving] = useState(false);
    const { control, handleSubmit } = useForm({
        defaultValues: {
            date: reading?.date ?? today(),
            time: reading?.time ?? nowTime(),
            ...Object.fromEntries(MEASURES.map(([k]) => [k, reading?.[k] ?? ""])),
        },
    });

    const save = async (values) => {
        setSaving(true);
        try {
            const body = Object.fromEntries(Object.entries(compact(values)).map(([k, v]) => [k, String(v)]));
            if (reading) await patientsApi.updateVitals(reading._id, body);
            else await patientsApi.addVitals(patient.username, body);
            toast.success("Vital signs saved.");
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
            title={reading ? "Edit vital signs" : "Record vital signs"}
            onClose={onClose}
            maxWidth="md"
            actions={<><Button variant="secondary" small onClick={onClose}>Cancel</Button><Button small loading={saving} onClick={handleSubmit(save, onInvalid)}>Save</Button></>}
        >
            <Grid container columnSpacing={2}>
                <Grid item xs={6} sm={3}><RHFTextField name="date" control={control} label="Date" type="date" rules={{ required: "Required" }} /></Grid>
                <Grid item xs={6} sm={3}><RHFTextField name="time" control={control} label="Time" type="time" rules={{ required: "Required" }} /></Grid>
                <Grid item sm={6} />
                {MEASURES.map(([k, label]) => (
                    <Grid item xs={6} sm={3} key={k}><RHFTextField name={k} control={control} label={label} /></Grid>
                ))}
            </Grid>
        </Modal>
    );
};

const VitalsTab = ({ patient }) => {
    const { can } = usePermission();
    const [range, setRange] = useState("this_week");
    const [date, setDate] = useState("");
    const [editing, setEditing] = useState(null);

    const { data: rows, loading, reload } = useAsync(
        () => patientsApi.vitals(patient.username, date ? { date } : { range }),
        [patient.username, range, date],
        []
    );

    const columns = useMemo(() => [
        { header: "Date", accessorKey: "date", cell: ({ row }) => fmtDate(row.original.date) },
        { header: "Time", accessorKey: "time", cell: ({ row }) => fmtTime(row.original.time) },
        { header: "BP", id: "bp", cell: ({ row }) => (row.original.patient_bp_systolic_value ? `${row.original.patient_bp_systolic_value}/${row.original.patient_bp_diastolic_value ?? "—"}` : "—") },
        { header: "Pulse", accessorKey: "pulse", cell: ({ row }) => row.original.pulse ?? "—" },
        { header: "Temp", accessorKey: "temperature", cell: ({ row }) => row.original.temperature ?? "—" },
        { header: "Resp", accessorKey: "resp", cell: ({ row }) => row.original.resp ?? "—" },
        { header: "SpO₂", accessorKey: "sat_O2", cell: ({ row }) => row.original.sat_O2 ?? "—" },
        { header: "In / Out", id: "io", cell: ({ row }) => `${row.original.intake ?? "—"} / ${row.original.output ?? "—"}` },
        { header: "Recorded by", accessorKey: "nurse_name", cell: ({ row }) => row.original.nurse_name ?? "—" },
        ...(can("vitals.record") ? [{
            header: "",
            id: "edit",
            cell: ({ row }) => <Button variant="secondary" small onClick={() => setEditing(row.original)}>Edit</Button>,
        }] : []),
    ], [can]);

    return (
        <Card
            label="Vital signs"
            actions={
                <>
                    <TextField select size="small" label="Period" value={date ? "" : range} onChange={(e) => { setDate(""); setRange(e.target.value); }} sx={{ minWidth: 150 }} disabled={!!date}>
                        {RANGES.map(([v, l]) => <MenuItem key={v} value={v}>{l}</MenuItem>)}
                    </TextField>
                    <TextField size="small" type="date" label="Or a day" value={date} onChange={(e) => setDate(e.target.value)} InputLabelProps={{ shrink: true }} />
                    {can("vitals.record") && <Button small onClick={() => setEditing({})}>+ Record</Button>}
                </>
            }
        >
            <DataTable data={rows} columns={columns} globalSearch={false} emptyMessage={loading ? "Loading…" : "No readings in this period"} />
            {editing && (
                <VitalsDialog
                    patient={patient}
                    reading={editing._id ? editing : null}
                    onClose={() => setEditing(null)}
                    onSaved={() => { setEditing(null); reload(); }}
                />
            )}
        </Card>
    );
};

export default VitalsTab;
