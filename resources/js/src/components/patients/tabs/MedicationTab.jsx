import React, { useMemo, useState } from "react";
import { useForm } from "react-hook-form";
import { Grid, TextField } from "@mui/material";
import { toast } from "react-toastify";
import DataTable, { ActionCell, TableBtn } from "../../table/DataTable";
import RHFTextField from "../../forms/RHFTextField";
import RHFSelect from "../../forms/RHFSelect";
import { Button, Card, ConfirmDialog, Modal, colors } from "../../common/ui";
import useAsync from "../../../hooks/useAsync";
import usePermission from "../../../hooks/usePermission";
import { patients as patientsApi } from "../../../api/homecare";
import { errorMessage } from "../../../api/client";
import { compact, fmtDate, fmtTime, today } from "../../../utilities/format";
import { onInvalid } from "../../../utilities/formErrors";

const SESSIONS = ["morning", "afternoon", "evening", "night"];
const FOOD = ["Before food", "After food", "With food", "Any time"].map((v) => ({ value: v, label: v }));
const FREQUENCY = ["Once daily", "Twice daily", "Thrice daily", "Four times daily", "As needed"].map((v) => ({ value: v, label: v }));

const MedicationDialog = ({ patient, onClose, onSaved }) => {
    const [saving, setSaving] = useState(false);
    const { control, handleSubmit } = useForm({
        defaultValues: { medicine_name: "", dosage: "", dose_unit: "", frequency: "", before_or_after_food: "", date: today(), time: "" },
    });

    const save = async (values) => {
        setSaving(true);
        try {
            await patientsApi.addMedication(patient.username, compact(values));
            toast.success("Medication added to the schedule.");
            onSaved();
        } catch (e) {
            toast.error(errorMessage(e));
        } finally {
            setSaving(false);
        }
    };

    return (
        <Modal open title="Add medication" onClose={onClose} maxWidth="md"
            actions={<><Button variant="secondary" small onClick={onClose}>Cancel</Button><Button small loading={saving} onClick={handleSubmit(save, onInvalid)}>Add</Button></>}>
            <Grid container columnSpacing={2}>
                <Grid item xs={12} sm={6}><RHFTextField name="medicine_name" control={control} label="Medicine *" rules={{ required: "Required" }} /></Grid>
                <Grid item xs={6} sm={3}><RHFTextField name="dosage" control={control} label="Dose" /></Grid>
                <Grid item xs={6} sm={3}><RHFTextField name="dose_unit" control={control} label="Unit (mg, ml…)" /></Grid>
                <Grid item xs={12} sm={4}><RHFSelect name="frequency" control={control} label="Frequency" options={FREQUENCY} /></Grid>
                <Grid item xs={12} sm={4}><RHFSelect name="before_or_after_food" control={control} label="Food" options={FOOD} /></Grid>
                <Grid item xs={6} sm={2}><RHFTextField name="date" control={control} label="Date *" type="date" rules={{ required: "Required" }} /></Grid>
                <Grid item xs={6} sm={2}><RHFTextField name="time" control={control} label="Time" type="time" /></Grid>
            </Grid>
        </Modal>
    );
};

/**
 * What deleting a medicine does, as on mobile: a duplicate's doses stay with
 * the remaining copy; deleting the only entry removes that day's doses too.
 */
const deleteEffect = (m) => {
    if (m.duplicate) return "This is a duplicate entry. The other entry stays, with any doses already given.";
    if (m.doses_given) return `Doses already marked given for this medicine on ${fmtDate(m.date)} will also be removed.`;
    return "This removes it from the patient's medication list.";
};

/** Session cell: who gave it and when, plus the witnessing guardian. */
const Given = ({ entry }) => {
    if (!entry) return <span style={{ color: colors.muted }}>—</span>;
    return (
        <div style={{ fontSize: 12 }}>
            <div style={{ fontWeight: 600, color: colors.success }}>✓ {fmtTime(entry.given_time)}</div>
            <div style={{ color: colors.body }}>{entry.nurse_name}</div>
            {entry.guardian_name && <div style={{ color: colors.muted }}>Witness: {entry.guardian_name}</div>}
            {entry.guardian_signature && <a href={entry.guardian_signature} target="_blank" rel="noreferrer" style={{ fontSize: 11 }}>signature</a>}
        </div>
    );
};

const MedicationTab = ({ patient }) => {
    const { can, canAny } = usePermission();
    const [date, setDate] = useState(today());
    const [adding, setAdding] = useState(false);
    const [deleting, setDeleting] = useState(null);
    const [removing, setRemoving] = useState(false);
    const canDelete = canAny(["medications.manage", "medications.administer"]);

    const { data: schedule, loading, reload } = useAsync(() => patientsApi.medications(patient.username), [patient.username], []);
    const { data: given, loading: loadingGiven, reload: reloadGiven } = useAsync(() => patientsApi.administered(patient.username, date), [patient.username, date], []);

    const remove = async () => {
        setRemoving(true);
        try {
            await patientsApi.deleteMedication(patient.username, deleting._id);
            toast.success("Medicine removed.");
            setDeleting(null);
            reload();
            reloadGiven();
        } catch (e) {
            toast.error(errorMessage(e));
        } finally {
            setRemoving(false);
        }
    };

    const scheduleColumns = useMemo(() => [
        { header: "Date", accessorKey: "date", cell: ({ row }) => fmtDate(row.original.date) },
        { header: "Medicine", accessorKey: "medicine_name" },
        { header: "Dose", id: "dose", cell: ({ row }) => [row.original.dosage, row.original.dose_unit].filter(Boolean).join(" ") || "—" },
        { header: "Frequency", accessorKey: "frequency", cell: ({ row }) => row.original.frequency || "—" },
        { header: "Food", accessorKey: "before_or_after_food", cell: ({ row }) => row.original.before_or_after_food || "—" },
        { header: "Entered by", id: "by", cell: ({ row }) => row.original.nurse_name ?? row.original.entered_by ?? "—" },
        ...(canDelete
            ? [{
                header: "Actions",
                id: "actions",
                cell: ({ row }) => (
                    <ActionCell>
                        <TableBtn variant="danger" onClick={() => setDeleting(row.original)}>Delete</TableBtn>
                    </ActionCell>
                ),
            }]
            : []),
    ], [canDelete]);

    const givenColumns = useMemo(() => [
        { header: "Medicine", accessorKey: "medicine_name" },
        ...SESSIONS.map((s) => ({ header: s.charAt(0).toUpperCase() + s.slice(1), id: s, cell: ({ row }) => <Given entry={row.original[s]} /> })),
    ], []);

    return (
        <>
            <Card label="Medication schedule" actions={can("medications.manage") && <Button small onClick={() => setAdding(true)}>+ Add medication</Button>}>
                <DataTable data={schedule} columns={scheduleColumns} emptyMessage={loading ? "Loading…" : "No medication scheduled"} />
            </Card>

            <Card
                label="Administered"
                style={{ marginTop: 16 }}
                actions={<TextField size="small" type="date" label="Day" value={date} onChange={(e) => setDate(e.target.value || today())} InputLabelProps={{ shrink: true }} />}
            >
                <DataTable data={given} columns={givenColumns} globalSearch={false} emptyMessage={loadingGiven ? "Loading…" : `Nothing recorded for ${fmtDate(date)}. Nurses record doses in the mobile app.`} />
            </Card>

            {adding && <MedicationDialog patient={patient} onClose={() => setAdding(false)} onSaved={() => { setAdding(false); reload(); }} />}
            <ConfirmDialog
                open={!!deleting}
                danger
                loading={removing}
                title={deleting?.duplicate ? "Delete duplicate?" : "Delete medicine?"}
                confirmLabel="Delete"
                message={deleting && (
                    <>
                        <strong>{deleting.medicine_name}</strong>
                        {" · "}
                        {[[deleting.dosage, deleting.dose_unit].filter(Boolean).join(" "), deleting.frequency, fmtDate(deleting.date)].filter(Boolean).join(" · ")}
                        <br /><br />
                        {deleteEffect(deleting)}
                    </>
                )}
                onConfirm={remove}
                onClose={() => setDeleting(null)}
            />
        </>
    );
};

export default MedicationTab;
