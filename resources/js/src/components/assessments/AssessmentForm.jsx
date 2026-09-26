import React, { useEffect, useMemo, useState } from "react";
import { useNavigate, useParams, useSearchParams } from "react-router-dom";
import { Controller, useForm, useWatch } from "react-hook-form";
import { Autocomplete, Grid, TextField } from "@mui/material";
import { toast } from "react-toastify";
import RHFTextField from "../forms/RHFTextField";
import RHFSelect from "../forms/RHFSelect";
import RHFCheckbox from "../forms/RHFCheckbox";
import { Button, Card, Loader, PageHeader, colors } from "../common/ui";
import { assessments, leads, reference as refApi } from "../../api/homecare";
import * as reference from "../../api/reference";
import { errorMessage, fieldErrors } from "../../api/client";
import { SERVICES, invoiceTotals } from "../../utilities/invoice";
import { fmtMoney } from "../../utilities/format";
import { onInvalid } from "../../utilities/formErrors";

const GENDERS = ["Male", "Female", "Other"].map((v) => ({ value: v, label: v }));

const EQUIPMENT = [
    ["bed", "Medical bed"],
    ["nurse_bed_chair", "Bed / chair for nurse"],
    ["wheelchair_walker", "Wheelchair / walker"],
    ["electrotherapy_device", "Electrotherapy device"],
];
const PHYSIO_FINDINGS = [
    ["poorstrength", "Patient has poor strength"],
    ["spasticity", "Increased tone / spasticity on the neck muscles"],
    ["balancing", "Balancing problem"],
    ["defect", "Posture defect"],
];
const PHYSIO_PLAN = [
    ["mobility", "Mobility"],
    ["neckstretching", "Neck stretching"],
    ["sitbalance", "Sitting and balancing"],
    ["standbalance", "Standing balance"],
    ["limbfunctioning", "Upper limb functions"],
];

const blank = {
    patient_name: "", mobile: "", email: "", identity_number: "", birth_date: "", age: "", gender: "",
    address: "", nationality: "", physician: "", diagnosis_name: "",
    physical_assessment: "", daily_activities: "", medications: "",
    care_plan: { nursing: false, physiotherapy: false, doctor: false },
    nursing: { quotation: "", duration: "", fees: "" },
    physiotherapy: { quotation: "", duration: "", sessions: "", fees: "" },
    doctor: { quotation: "", duration: "", sessions: "", fees: "" },
    equipment: { bed: false, nurse_bed_chair: false, wheelchair_walker: false, electrotherapy_device: false, others: false, other_equipment_details: "" },
    physiotherapy_details: {},
    physiotherapy_treatment_plan: {},
    invoice_details: { discount: "", vat: "", other_fees: "" },
};

/** Merge stored values over the blank form (legacy documents may miss sections). */
const toForm = (doc) => {
    const merged = { ...blank };
    Object.keys(blank).forEach((k) => {
        const v = doc?.[k];
        if (v === undefined || v === null) return;
        merged[k] = typeof blank[k] === "object" && typeof v === "object" ? { ...blank[k], ...v } : v;
    });
    return merged;
};

/** ICD code search that also accepts free text. */
const DiagnosisField = ({ control }) => {
    const [options, setOptions] = useState([]);
    const [input, setInput] = useState("");

    useEffect(() => {
        if (input.trim().length < 2) return undefined;
        const t = setTimeout(() => refApi.icdCodes(input.trim()).then((list) => setOptions(list ?? [])).catch(() => setOptions([])), 300);
        return () => clearTimeout(t);
    }, [input]);

    return (
        <Controller
            name="diagnosis_name"
            control={control}
            render={({ field }) => (
                <Autocomplete
                    freeSolo
                    options={options}
                    value={field.value || ""}
                    onChange={(_, v) => field.onChange(v ?? "")}
                    inputValue={input || field.value || ""}
                    onInputChange={(_, v) => { setInput(v); field.onChange(v); }}
                    renderInput={(params) => <TextField {...params} label="Diagnosis (search ICD codes)" margin="normal" fullWidth />}
                />
            )}
        />
    );
};

/**
 * Record a lead's initial assessment (/assessments/new?lead=ID) or edit one
 * (/assessments/:id/edit). Care-plan fees feed the assessment invoice.
 */
const AssessmentForm = () => {
    const { id } = useParams();
    const [search] = useSearchParams();
    const leadId = search.get("lead");
    const isEdit = Boolean(id);
    const navigate = useNavigate();

    const [loading, setLoading] = useState(true);
    const [saving, setSaving] = useState(false);
    const [nationalities, setNationalities] = useState([]);

    const { control, handleSubmit, reset, setError } = useForm({ defaultValues: blank });
    const values = useWatch({ control });
    const totals = useMemo(() => invoiceTotals(values), [values]);

    useEffect(() => {
        reference.nationalities().then((list) => setNationalities(list.map((n) => ({ value: n.name ?? n.label, label: n.name ?? n.label })))).catch(() => {});
    }, []);

    useEffect(() => {
        // Ignore a load that finishes after a newer one (or after unmount), so
        // it can never overwrite what the user has typed since.
        let active = true;
        const load = isEdit
            ? assessments.show(id).then((a) => active && reset(toForm(a)))
            : leadId
                ? leads.show(leadId).then((l) => active && reset(toForm({ patient_name: l.patient_name, mobile: l.mobile, email: l.email })))
                : Promise.resolve();
        load.catch((e) => active && toast.error(errorMessage(e))).finally(() => active && setLoading(false));
        return () => { active = false; };
    }, [id, isEdit, leadId, reset]);

    const onSubmit = async (form) => {
        const t = invoiceTotals(form);
        const body = {
            ...form,
            age: form.age === "" ? null : Number(form.age),
            birth_date: form.birth_date || null,
            email: form.email || null,
            invoice_details: { ...form.invoice_details, subtotal: t.subtotal, total_amount: t.total },
        };

        setSaving(true);
        try {
            if (isEdit) {
                await assessments.update(id, body);
                toast.success("Assessment updated.");
            } else {
                await assessments.create({ ...body, lead_id: leadId });
                toast.success("Assessment saved.");
            }
            navigate("/assessments");
        } catch (e) {
            Object.entries(fieldErrors(e)).forEach(([field, messages]) => setError(field, { message: messages[0] }));
            toast.error(errorMessage(e));
        } finally {
            setSaving(false);
        }
    };

    if (loading) return <Loader />;
    if (!isEdit && !leadId) {
        return <p style={{ color: colors.muted }}>Start an assessment from a lead on the Leads page.</p>;
    }

    const care = values.care_plan ?? {};

    return (
        <div style={{ maxWidth: 980 }}>
            <PageHeader
                eyebrow={isEdit ? "Edit assessment" : `Lead #${leadId}`}
                title={isEdit ? values.patient_name || "Assessment" : "Initial Assessment"}
                subtitle="Home visit findings, the care plan and its quotation."
            />
            <form onSubmit={handleSubmit(onSubmit, onInvalid)} noValidate>
                <Card label="Patient">
                    <Grid container columnSpacing={2}>
                        <Grid item xs={12} sm={6}><RHFTextField name="patient_name" control={control} label="Patient name *" rules={{ required: "Required" }} /></Grid>
                        <Grid item xs={12} sm={3}><RHFTextField name="mobile" control={control} label="Mobile" /></Grid>
                        <Grid item xs={12} sm={3}><RHFTextField name="email" control={control} label="Email" type="email" /></Grid>
                        <Grid item xs={6} sm={3}><RHFTextField name="birth_date" control={control} label="Date of birth" type="date" /></Grid>
                        <Grid item xs={6} sm={2}><RHFTextField name="age" control={control} label="Age" type="number" /></Grid>
                        <Grid item xs={6} sm={3}><RHFSelect name="gender" control={control} label="Gender" options={GENDERS} /></Grid>
                        <Grid item xs={6} sm={4}>
                            {nationalities.length
                                ? <RHFSelect name="nationality" control={control} label="Nationality" options={nationalities} />
                                : <RHFTextField name="nationality" control={control} label="Nationality" />}
                        </Grid>
                        <Grid item xs={12} sm={4}><RHFTextField name="identity_number" control={control} label="ID number" /></Grid>
                        <Grid item xs={12} sm={8}><RHFTextField name="address" control={control} label="Address" /></Grid>
                    </Grid>
                </Card>

                <Card label="Assessment" style={{ marginTop: 16 }}>
                    <Grid container columnSpacing={2}>
                        <Grid item xs={12} sm={5}><RHFTextField name="physician" control={control} label="Referring physician" /></Grid>
                        <Grid item xs={12} sm={7}><DiagnosisField control={control} /></Grid>
                        <Grid item xs={12}><RHFTextField name="physical_assessment" control={control} label="Physical assessment" multiline minRows={3} /></Grid>
                        <Grid item xs={12} sm={6}><RHFTextField name="daily_activities" control={control} label="Daily activities" multiline minRows={3} /></Grid>
                        <Grid item xs={12} sm={6}><RHFTextField name="medications" control={control} label="Current medications" multiline minRows={3} /></Grid>
                    </Grid>
                </Card>

                <Card label="Care plan & quotation" style={{ marginTop: 16 }}>
                    <div style={{ display: "flex", gap: 24, flexWrap: "wrap" }}>
                        {SERVICES.map(({ key, label }) => <RHFCheckbox key={key} name={`care_plan.${key}`} control={control} label={label} />)}
                    </div>
                    {SERVICES.filter(({ key }) => care[key]).map(({ key, label }) => (
                        <Grid container columnSpacing={2} key={key} alignItems="center">
                            <Grid item xs={12} sm={2}><strong style={{ fontSize: 13, color: colors.ink }}>{label}</strong></Grid>
                            <Grid item xs={12} sm={4}><RHFTextField name={`${key}.quotation`} control={control} label="Quotation / plan" /></Grid>
                            <Grid item xs={4} sm={2}><RHFTextField name={`${key}.duration`} control={control} label="Duration" /></Grid>
                            {key !== "nursing"
                                ? <Grid item xs={4} sm={2}><RHFTextField name={`${key}.sessions`} control={control} label="Sessions" /></Grid>
                                : <Grid item xs={false} sm={2} />}
                            <Grid item xs={4} sm={2}><RHFTextField name={`${key}.fees`} control={control} label="Fees" type="number" /></Grid>
                        </Grid>
                    ))}
                </Card>

                {care.physiotherapy && (
                    <Card label="Physiotherapy" style={{ marginTop: 16 }}>
                        <Grid container columnSpacing={2}>
                            <Grid item xs={12} sm={6}>
                                <div style={{ fontSize: 12, color: colors.muted, margin: "4px 0" }}>Findings</div>
                                {PHYSIO_FINDINGS.map(([k, l]) => <div key={k}><RHFCheckbox name={`physiotherapy_details.${k}`} control={control} label={l} /></div>)}
                            </Grid>
                            <Grid item xs={12} sm={6}>
                                <div style={{ fontSize: 12, color: colors.muted, margin: "4px 0" }}>Treatment plan</div>
                                {PHYSIO_PLAN.map(([k, l]) => <div key={k}><RHFCheckbox name={`physiotherapy_treatment_plan.${k}`} control={control} label={l} /></div>)}
                            </Grid>
                        </Grid>
                    </Card>
                )}

                <Card label="Equipment needed" style={{ marginTop: 16 }}>
                    <div style={{ display: "flex", gap: 20, flexWrap: "wrap" }}>
                        {EQUIPMENT.map(([k, l]) => <RHFCheckbox key={k} name={`equipment.${k}`} control={control} label={l} />)}
                        <RHFCheckbox name="equipment.others" control={control} label="Other" />
                    </div>
                    {values.equipment?.others && <RHFTextField name="equipment.other_equipment_details" control={control} label="Other equipment" />}
                </Card>

                <Card label="Invoice" style={{ marginTop: 16 }}>
                    <Grid container columnSpacing={2} alignItems="center">
                        <Grid item xs={4} sm={2}><RHFTextField name="invoice_details.discount" control={control} label="Discount %" type="number" /></Grid>
                        <Grid item xs={4} sm={2}><RHFTextField name="invoice_details.vat" control={control} label="VAT %" type="number" /></Grid>
                        <Grid item xs={4} sm={2}><RHFTextField name="invoice_details.other_fees" control={control} label="Other fees" type="number" /></Grid>
                        <Grid item xs={12} sm={6}>
                            <div style={{ display: "flex", justifyContent: "flex-end", gap: 24, fontSize: 13, color: colors.body }}>
                                <span>Subtotal <strong>{fmtMoney(totals.subtotal)}</strong></span>
                                <span>Total <strong style={{ color: colors.ink, fontSize: 15 }}>{fmtMoney(totals.total)}</strong></span>
                            </div>
                        </Grid>
                    </Grid>
                </Card>

                <div style={{ display: "flex", justifyContent: "flex-end", gap: 12, marginTop: 24 }}>
                    <Button variant="secondary" onClick={() => navigate(-1)} disabled={saving}>Cancel</Button>
                    <Button type="submit" loading={saving}>{isEdit ? "Save changes" : "Save assessment"}</Button>
                </div>
            </form>
        </div>
    );
};

export default AssessmentForm;
