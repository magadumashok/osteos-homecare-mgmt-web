import React, { useEffect, useMemo, useState } from "react";
import { useNavigate, useParams } from "react-router-dom";
import { useForm, useWatch } from "react-hook-form";
import { Grid } from "@mui/material";
import { toast } from "react-toastify";
import RHFTextField from "../forms/RHFTextField";
import RHFSelect from "../forms/RHFSelect";
import { Button, Card, Loader, PageHeader } from "../common/ui";
import { patients as patientsApi, reference as refApi } from "../../api/homecare";
import * as reference from "../../api/reference";
import { errorMessage, fieldErrors } from "../../api/client";
import { compact } from "../../utilities/format";
import { onInvalid } from "../../utilities/formErrors";
import { SERVICE_OPTIONS, serviceChoice, serviceValue } from "../../utilities/services";

const GENDERS = ["Male", "Female", "Other"].map((v) => ({ value: v, label: v }));
const SOURCES = ["Phone call", "Walk-in", "Referral", "Hospital", "Website", "Social media", "Other"].map((v) => ({ value: v, label: v }));

const FIELDS = [
    "patient_name", "mobile", "email", "gender", "age", "date_of_birth", "id_no", "nationality", "insurance_name",
    "weight", "country", "state", "city", "address", "source_of_contact", "status", "service", "service_package", "fees", "comments",
];

// Service packages come from shared reference data; field names vary between imports.
const packageName = (p) => p.name ?? p.package_name ?? p.service_package ?? p.title ?? "";
const packageFees = (p) => p.fees ?? p.price ?? p.amount ?? "";

// Options valued by name. Older patients have free-text states, so a saved
// value that is not in the list is kept as an option.
const namedOptions = (rows, current) => {
    const options = rows.map((r) => ({ value: r.name ?? r.label, label: r.name ?? r.label })).filter((o) => o.value);
    return current && !options.some((o) => o.value === current) ? [{ value: current, label: current }, ...options] : options;
};

/** Register a patient directly (/patients/new) or edit one (/patients/:username/edit). */
const PatientForm = () => {
    const { username } = useParams();
    const isEdit = Boolean(username);
    const navigate = useNavigate();

    const [loading, setLoading] = useState(isEdit);
    const [saving, setSaving] = useState(false);
    const [countries, setCountries] = useState([]);
    const [regions, setRegions] = useState([]);
    const [cities, setCities] = useState([]);
    const [nationalities, setNationalities] = useState([]);
    const [packages, setPackages] = useState([]);

    const { control, handleSubmit, reset, setValue, setError } = useForm({
        defaultValues: { ...Object.fromEntries(FIELDS.map((f) => [f, ""])), service_other: "" },
    });
    const service = useWatch({ control, name: "service" });
    const chosenPackage = useWatch({ control, name: "service_package" });
    const country = useWatch({ control, name: "country" });
    const state = useWatch({ control, name: "state" });
    const city = useWatch({ control, name: "city" });

    useEffect(() => {
        reference.countries().then((list) => setCountries(list.map((c) => ({ value: c.code, label: c.name })))).catch(() => {});
        reference.nationalities().then((list) => setNationalities(list.map((n) => ({ value: n.name ?? n.label, label: n.name ?? n.label })))).catch(() => {});
        refApi.servicePackages().then((list) => setPackages(list ?? [])).catch(() => {});
    }, []);

    useEffect(() => {
        if (!isEdit) return undefined;
        let active = true; // ignore a stale load (see AssessmentForm)
        patientsApi.show(username)
            .then((p) => {
                if (!active) return;
                const choice = serviceChoice(p.service);
                reset({ ...Object.fromEntries(FIELDS.map((f) => [f, p[f] ?? ""])), service: choice.service, service_other: choice.other });
            })
            .catch((e) => active && toast.error(errorMessage(e)))
            .finally(() => active && setLoading(false));
        return () => { active = false; };
    }, [isEdit, username, reset]);

    // States of the chosen country, as on the staff form.
    useEffect(() => {
        setRegions([]);
        if (country) reference.regions(country).then(setRegions).catch(() => setRegions([]));
    }, [country]);

    // State and city are saved by name (they go into the directions link),
    // so the chosen state's code is looked up to list its cities.
    const regionCode = useMemo(() => regions.find((r) => (r.name ?? r.label) === state)?.code ?? "", [regions, state]);

    useEffect(() => {
        setCities([]);
        if (regionCode) reference.cities(regionCode).then(setCities).catch(() => setCities([]));
    }, [regionCode]);

    const stateOptions = useMemo(() => namedOptions(regions, state), [regions, state]);
    const cityOptions = useMemo(() => namedOptions(cities, city), [cities, city]);

    const packageOptions = useMemo(() => packages.map((p) => ({ value: packageName(p), label: packageName(p) })).filter((o) => o.value), [packages]);

    // Picking a package fills in its fee (it can still be changed).
    useEffect(() => {
        const pkg = packages.find((p) => packageName(p) === chosenPackage);
        if (pkg && packageFees(pkg) !== "") setValue("fees", packageFees(pkg));
    }, [chosenPackage, packages, setValue]);

    const onSubmit = async ({ service_other: serviceOther, ...values }) => {
        if (values.service === "other" && !serviceOther.trim()) {
            setError("service_other", { message: "Enter the service" });
            return;
        }
        const body = compact({ ...values, service: serviceValue(values.service, serviceOther) });
        setSaving(true);
        try {
            const patient = isEdit ? await patientsApi.update(username, body) : await patientsApi.create(body);
            toast.success(isEdit ? "Patient updated." : `Patient registered as ${patient.username}.`);
            navigate(`/patients/${patient.username}`);
        } catch (e) {
            Object.entries(fieldErrors(e)).forEach(([field, messages]) => setError(field, { message: messages[0] }));
            toast.error(errorMessage(e));
        } finally {
            setSaving(false);
        }
    };

    if (loading) return <Loader />;

    return (
        <div style={{ maxWidth: 900 }}>
            <PageHeader
                eyebrow={isEdit ? `Patient ${username}` : "New patient"}
                title={isEdit ? "Edit Patient" : "Register Patient"}
                subtitle={isEdit ? null : "For patients admitted without the lead and assessment steps. The patient gets a WhatsApp welcome message."}
            />
            <form onSubmit={handleSubmit(onSubmit, onInvalid)} noValidate>
                <Card label="Personal details">
                    <Grid container columnSpacing={2}>
                        <Grid item xs={12} sm={6}><RHFTextField name="patient_name" control={control} label="Patient name *" rules={{ required: "Required" }} /></Grid>
                        <Grid item xs={6} sm={3}><RHFSelect name="gender" control={control} label="Gender" options={GENDERS} /></Grid>
                        <Grid item xs={6} sm={3}><RHFTextField name="age" control={control} label="Age" type="number" /></Grid>
                        {/* <Grid item xs={6} sm={3}><RHFTextField name="date_of_birth" control={control} label="Date of birth" type="date" /></Grid>
                        <Grid item xs={6} sm={3}><RHFTextField name="weight" control={control} label="Weight (kg)" type="number" /></Grid>
                        <Grid item xs={12} sm={3}>
                            {nationalities.length
                                ? <RHFSelect name="nationality" control={control} label="Nationality" options={nationalities} />
                                : <RHFTextField name="nationality" control={control} label="Nationality" />}
                        </Grid>
                        <Grid item xs={12} sm={3}><RHFTextField name="id_no" control={control} label="ID number" /></Grid>
                        <Grid item xs={12} sm={6}><RHFTextField name="insurance_name" control={control} label="Insurance" /></Grid> */}
                    </Grid>
                </Card>

                <Card label="Contact & address" style={{ marginTop: 16 }}>
                    <Grid container columnSpacing={2}>
                        <Grid item xs={12} sm={4}>
                            <RHFSelect
                                name="country"
                                control={control}
                                label={isEdit ? "Country" : "Country *"}
                                options={countries}
                                rules={isEdit ? undefined : { required: "Required" }}
                                onChange={() => {
                                    setValue("state", "");
                                    setValue("city", "");
                                }}
                            />
                        </Grid>
                        <Grid item xs={12} sm={4}>
                            <RHFTextField name="mobile" control={control} label={isEdit ? "Mobile (with country code)" : "Mobile (without country code)"} />
                        </Grid>
                        <Grid item xs={12} sm={4}><RHFTextField name="email" control={control} label="Email" type="email" /></Grid>
                        <Grid item xs={12} sm={4}>
                            <RHFSelect
                                name="state"
                                control={control}
                                label="State / Region"
                                options={stateOptions}
                                disabled={!country}
                                onChange={() => setValue("city", "")}
                            />
                        </Grid>
                        <Grid item xs={12} sm={4}>
                            <RHFSelect name="city" control={control} label="City" options={cityOptions} disabled={!state} />
                        </Grid>
                        <Grid item xs={12}><RHFTextField name="address" control={control} label="Street address (used for directions)" /></Grid>
                    </Grid>
                </Card>

                <Card label="Service" style={{ marginTop: 16 }}>
                    <Grid container columnSpacing={2}>
                        <Grid item xs={12} sm={service === "other" ? 6 : 12}>
                            <RHFSelect name="service" control={control} label="Service" options={SERVICE_OPTIONS} />
                        </Grid>
                        {service === "other" && (
                            <Grid item xs={12} sm={6}><RHFTextField name="service_other" control={control} label="Service name *" /></Grid>
                        )}
                        <Grid item xs={12} sm={6}>
                            {packageOptions.length
                                ? <RHFSelect name="service_package" control={control} label="Service package" options={packageOptions} />
                                : <RHFTextField name="service_package" control={control} label="Service package" />}
                        </Grid>
                        <Grid item xs={6} sm={3}><RHFTextField name="fees" control={control} label="Fees" type="number" /></Grid>
                        <Grid item xs={6} sm={3}><RHFSelect name="source_of_contact" control={control} label="Source" options={SOURCES} /></Grid>
                        <Grid item xs={12}><RHFTextField name="comments" control={control} label="Comments" multiline minRows={2} /></Grid>
                    </Grid>
                </Card>

                <div style={{ display: "flex", justifyContent: "flex-end", gap: 12, marginTop: 24 }}>
                    <Button variant="secondary" onClick={() => navigate(-1)} disabled={saving}>Cancel</Button>
                    <Button type="submit" loading={saving}>{isEdit ? "Save changes" : "Register patient"}</Button>
                </div>
            </form>
        </div>
    );
};

export default PatientForm;
