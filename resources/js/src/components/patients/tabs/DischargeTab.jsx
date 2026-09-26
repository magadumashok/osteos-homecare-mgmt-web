import React, { useState } from "react";
import { Grid, TextField } from "@mui/material";
import { toast } from "react-toastify";
import { Button, Card, ConfirmDialog, InfoGrid, colors } from "../../common/ui";
import usePermission from "../../../hooks/usePermission";
import { patients as patientsApi } from "../../../api/homecare";
import { errorMessage } from "../../../api/client";
import { fmtDate, fmtTime, isYes, nowTime, today } from "../../../utilities/format";

/**
 * Discharge in two steps, as in the legacy app: an admin sets the planned
 * date, then the nurse discharges with a summary on the day.
 */
const DischargeTab = ({ patient, reloadPatient }) => {
    const { can } = usePermission();
    const discharged = isYes(patient.discharged);
    const [plan, setPlan] = useState({ date: patient.discharge?.date ?? "", time: patient.discharge?.time ?? "" });
    const [summary, setSummary] = useState({ date: today(), time: nowTime(), summary: "" });
    const [saving, setSaving] = useState(false);
    const [confirming, setConfirming] = useState(false);

    const savePlan = async () => {
        setSaving(true);
        try {
            await patientsApi.scheduleDischarge(patient.username, { date: plan.date, time: plan.time || null });
            toast.success("Discharge date saved.");
            reloadPatient();
        } catch (e) {
            toast.error(errorMessage(e));
        } finally {
            setSaving(false);
        }
    };

    const discharge = async () => {
        setSaving(true);
        try {
            await patientsApi.discharge(patient.username, summary);
            toast.success(`${patient.patient_name} has been discharged.`);
            setConfirming(false);
            reloadPatient();
        } catch (e) {
            toast.error(errorMessage(e));
        } finally {
            setSaving(false);
        }
    };

    if (discharged) {
        return (
            <Card label="Discharged">
                <p style={{ fontSize: 13, color: colors.body, marginTop: 0 }}>This patient has been discharged. The summary is listed under Discharged patients.</p>
                {patient.discharge?.date && <InfoGrid items={[{ label: "Planned date", value: `${fmtDate(patient.discharge.date)} ${patient.discharge.time ? fmtTime(patient.discharge.time) : ""}` }]} />}
            </Card>
        );
    }

    const canManage = can("discharge.manage");

    return (
        <Grid container spacing={2}>
            <Grid item xs={12} md={5}>
                <Card label="Planned discharge">
                    {canManage ? (
                        <>
                            <Grid container columnSpacing={2}>
                                <Grid item xs={7}><TextField fullWidth margin="normal" type="date" label="Date" value={plan.date} onChange={(e) => setPlan({ ...plan, date: e.target.value })} InputLabelProps={{ shrink: true }} /></Grid>
                                <Grid item xs={5}><TextField fullWidth margin="normal" type="time" label="Time" value={plan.time} onChange={(e) => setPlan({ ...plan, time: e.target.value })} InputLabelProps={{ shrink: true }} /></Grid>
                            </Grid>
                            <Button small loading={saving} disabled={!plan.date} onClick={savePlan} style={{ marginTop: 8 }}>Save date</Button>
                        </>
                    ) : (
                        <p style={{ fontSize: 13, margin: 0 }}>{patient.discharge?.date ? `${fmtDate(patient.discharge.date)} ${fmtTime(patient.discharge.time)}` : "Not set."}</p>
                    )}
                </Card>
            </Grid>
            {canManage && (
                <Grid item xs={12} md={7}>
                    <Card label="Discharge now">
                        <Grid container columnSpacing={2}>
                            <Grid item xs={6}><TextField fullWidth margin="normal" type="date" label="Date" value={summary.date} onChange={(e) => setSummary({ ...summary, date: e.target.value })} InputLabelProps={{ shrink: true }} /></Grid>
                            <Grid item xs={6}><TextField fullWidth margin="normal" type="time" label="Time" value={summary.time} onChange={(e) => setSummary({ ...summary, time: e.target.value })} InputLabelProps={{ shrink: true }} /></Grid>
                            <Grid item xs={12}><TextField fullWidth margin="normal" multiline minRows={4} label="Discharge summary *" value={summary.summary} onChange={(e) => setSummary({ ...summary, summary: e.target.value })} /></Grid>
                        </Grid>
                        <Button variant="danger" small disabled={!summary.summary.trim()} onClick={() => setConfirming(true)} style={{ marginTop: 8 }}>Discharge patient</Button>
                    </Card>
                </Grid>
            )}
            <ConfirmDialog
                open={confirming}
                title="Discharge patient"
                message={`Discharge ${patient.patient_name}? They will move to Discharged patients.`}
                confirmLabel="Discharge"
                danger
                loading={saving}
                onConfirm={discharge}
                onClose={() => setConfirming(false)}
            />
        </Grid>
    );
};

export default DischargeTab;
