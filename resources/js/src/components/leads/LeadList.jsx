import React, { useEffect, useMemo, useState } from "react";
import { useNavigate } from "react-router-dom";
import { useForm } from "react-hook-form";
import { Grid } from "@mui/material";
import { toast } from "react-toastify";
import DataTable, { ActionCell, TableBtn } from "../table/DataTable";
import RHFTextField from "../forms/RHFTextField";
import RHFSelect from "../forms/RHFSelect";
import { Badge, Button, Card, Modal, PageHeader, PersonCell, StatStrip } from "../common/ui";
import useAsync from "../../hooks/useAsync";
import usePermission from "../../hooks/usePermission";
import { leads as leadsApi, staff as staffApi } from "../../api/homecare";
import * as reference from "../../api/reference";
import { errorMessage, fieldErrors } from "../../api/client";
import { compact, fmtDate, fmtTime, humanize, nowTime, today } from "../../utilities/format";
import { onInvalid } from "../../utilities/formErrors";

const FOLLOWUPS = ["new", "follow_up", "not_reachable", "not_interested", "to_be_assessed"].map((v) => ({ value: v, label: humanize(v) }));
const SOURCES = ["Phone call", "Walk-in", "Referral", "Hospital", "Website", "Social media", "Other"].map((v) => ({ value: v, label: v }));

const followupTone = (f) => (f === "to_be_assessed" ? "success" : f === "not_interested" ? "danger" : f === "new" ? "neutral" : "warning");

/** Add or edit a lead. On create the mobile is local; the server adds the country code. */
const LeadDialog = ({ lead, onClose, onSaved }) => {
    const isEdit = Boolean(lead?.lead_id);
    const [countries, setCountries] = useState([]);
    const [staffOptions, setStaffOptions] = useState([]);
    const [saving, setSaving] = useState(false);

    const { control, handleSubmit, setError } = useForm({
        defaultValues: {
            patient_name: lead?.patient_name ?? "", mobile: lead?.mobile ?? "", email: lead?.email ?? "",
            country: lead?.country ?? "", staff: lead?.staff ?? "", source: lead?.source ?? "",
            date: lead?.date ?? today(), time: lead?.time ?? nowTime(), followup: lead?.followup ?? "new",
            comments: lead?.comments ?? "",
        },
    });

    useEffect(() => {
        reference.countries().then((list) => setCountries(list.map((c) => ({ value: c.name, label: c.name })))).catch(() => {});
        staffApi.list({ group: "except-drivers" })
            .then((list) => setStaffOptions(list.map((s) => ({ value: s.name, label: s.name }))))
            .catch(() => {});
    }, []);

    const submit = async (values) => {
        setSaving(true);
        try {
            const body = compact(values);
            if (isEdit) await leadsApi.update(lead.lead_id, body);
            else await leadsApi.create(body);
            toast.success(isEdit ? "Lead updated." : "Lead added.");
            onSaved();
        } catch (e) {
            Object.entries(fieldErrors(e)).forEach(([field, messages]) => setError(field, { message: messages[0] }));
            toast.error(errorMessage(e));
        } finally {
            setSaving(false);
        }
    };

    return (
        <Modal
            open
            title={isEdit ? `Lead #${lead.lead_id}` : "New lead"}
            onClose={onClose}
            maxWidth="md"
            actions={
                <>
                    <Button variant="secondary" small onClick={onClose} disabled={saving}>Cancel</Button>
                    <Button small loading={saving} onClick={handleSubmit(submit, onInvalid)}>{isEdit ? "Save" : "Add lead"}</Button>
                </>
            }
        >
            <Grid container columnSpacing={2}>
                <Grid item xs={12} sm={6}><RHFTextField name="patient_name" control={control} label="Patient name *" rules={{ required: "Required" }} /></Grid>
                <Grid item xs={12} sm={6}><RHFSelect name="country" control={control} label={isEdit ? "Country" : "Country *"} options={countries} rules={isEdit ? undefined : { required: "Required" }} /></Grid>
                <Grid item xs={12} sm={6}><RHFTextField name="mobile" control={control} label={isEdit ? "Mobile" : "Mobile (without country code) *"} rules={{ required: "Required" }} /></Grid>
                <Grid item xs={12} sm={6}><RHFTextField name="email" control={control} label="Email" type="email" /></Grid>
                <Grid item xs={12} sm={4}><RHFSelect name="source" control={control} label="Source" options={SOURCES} /></Grid>
                <Grid item xs={12} sm={4}><RHFSelect name="staff" control={control} label="Handled by" options={staffOptions} /></Grid>
                <Grid item xs={12} sm={4}><RHFSelect name="followup" control={control} label="Follow-up" options={FOLLOWUPS} /></Grid>
                <Grid item xs={6} sm={3}><RHFTextField name="date" control={control} label="Date" type="date" /></Grid>
                <Grid item xs={6} sm={3}><RHFTextField name="time" control={control} label="Time" type="time" /></Grid>
                <Grid item xs={12} sm={6}><RHFTextField name="comments" control={control} label="Comments" multiline minRows={2} /></Grid>
            </Grid>
        </Modal>
    );
};

const LeadList = () => {
    const navigate = useNavigate();
    const { can } = usePermission();
    const { data: leads, loading, reload } = useAsync(() => leadsApi.list(), [], []);
    const [editing, setEditing] = useState(null);

    const moveToAssessment = async (lead) => {
        try {
            await leadsApi.toBeAssessed(lead.lead_id);
            toast.success(`${lead.patient_name} is ready for assessment.`);
            reload();
        } catch (e) {
            toast.error(errorMessage(e));
        }
    };

    const columns = useMemo(() => [
        { header: "#", accessorKey: "lead_id" },
        { header: "Patient", accessorKey: "patient_name", cell: ({ row }) => <PersonCell name={row.original.patient_name} sub={row.original.mobile} /> },
        { header: "Source", accessorKey: "source", cell: ({ row }) => row.original.source || "—" },
        { header: "Handled by", accessorKey: "staff", cell: ({ row }) => row.original.staff || "—" },
        { header: "Received", accessorKey: "date", cell: ({ row }) => `${fmtDate(row.original.date)} ${row.original.time ? fmtTime(row.original.time) : ""}` },
        { header: "Follow-up", accessorKey: "followup", cell: ({ row }) => <Badge tone={followupTone(row.original.followup)}>{humanize(row.original.followup) || "New"}</Badge> },
        {
            header: "Actions",
            id: "actions",
            cell: ({ row }) => {
                const lead = row.original;
                return (
                    <ActionCell>
                        {can("leads.manage") && <TableBtn variant="secondary" onClick={() => setEditing(lead)}>Edit</TableBtn>}
                        {can("leads.manage") && lead.followup !== "to_be_assessed" && (
                            <TableBtn variant="secondary" onClick={() => moveToAssessment(lead)}>To be assessed</TableBtn>
                        )}
                        {can("assessments.manage") && (
                            <TableBtn onClick={() => navigate(`/assessments/new?lead=${lead.lead_id}`)}>Assess</TableBtn>
                        )}
                    </ActionCell>
                );
            },
        },
    // eslint-disable-next-line react-hooks/exhaustive-deps
    ], [can, navigate]);

    return (
        <>
            <PageHeader
                eyebrow="Patient intake"
                title="Leads"
                subtitle="Enquiries waiting for an initial assessment."
                actions={can("leads.manage") && <Button onClick={() => setEditing({})}>+ New lead</Button>}
            />
            <StatStrip items={[
                { label: "Open leads", value: leads.length },
                { label: "To be assessed", value: leads.filter((l) => l.followup === "to_be_assessed").length },
                { label: "Received today", value: leads.filter((l) => l.date === today()).length },
            ]} />
            <Card>
                <DataTable data={leads} columns={columns} searchPlaceholder="Search by name, mobile or source…" emptyMessage={loading ? "Loading…" : "No open leads"} />
            </Card>
            {editing && <LeadDialog lead={editing} onClose={() => setEditing(null)} onSaved={() => { setEditing(null); reload(); }} />}
        </>
    );
};

export default LeadList;
