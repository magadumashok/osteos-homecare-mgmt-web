import React, { useEffect, useMemo, useState } from "react";
import { useNavigate } from "react-router-dom";
import { useForm } from "react-hook-form";
import { yupResolver } from "@hookform/resolvers/yup";
import { CircularProgress, Grid } from "@mui/material";
import { toast } from "react-toastify";
import RHFTextField from "../forms/RHFTextField";
import RHFSelect from "../forms/RHFSelect";
import AuthLayout from "./AuthLayout";
import { registerHomecare } from "../../api/auth";
import * as reference from "../../api/reference";
import { errorMessage, fieldErrors } from "../../api/client";
import { registerHomecareSchema } from "../../validations/authSchemas";

// Reference documents carry `name` (older ones `label`).
const toOptions = (rows, valueKey) =>
    rows.map((r) => ({ value: r[valueKey] ?? r.name ?? r.label, label: r.name ?? r.label }));

const RegisterHomecare = () => {
    const navigate = useNavigate();
    const [countries, setCountries] = useState([]);
    const [regions, setRegions] = useState([]);
    const [cities, setCities] = useState([]);

    const { handleSubmit, control, watch, setValue, setError, formState: { isSubmitting } } = useForm({
        resolver: yupResolver(registerHomecareSchema),
        defaultValues: {
            name: "", owner: "", email: "", mobile: "", password: "", password_confirmation: "",
            country: "", region: "", city: "", address: "", center_id: "",
        },
    });

    const country = watch("country");
    const region = watch("region");

    useEffect(() => {
        reference.countries().then(setCountries).catch(() => toast.error("Could not load the list of countries."));
    }, []);

    useEffect(() => {
        setValue("region", "");
        setValue("city", "");
        setRegions([]);
        if (country) reference.regions(country).then(setRegions).catch(() => setRegions([]));
    }, [country, setValue]);

    useEffect(() => {
        setValue("city", "");
        setCities([]);
        if (region) reference.cities(region).then(setCities).catch(() => setCities([]));
    }, [region, setValue]);

    const countryOptions = useMemo(() => toOptions(countries, "code"), [countries]);
    const regionOptions = useMemo(() => toOptions(regions, "code"), [regions]);
    const cityOptions = useMemo(() => toOptions(cities, "name"), [cities]);

    const onSubmit = async (values) => {
        // The form selects a region by code (to load its cities); the server stores its name.
        const regionName = regionOptions.find((r) => r.value === values.region)?.label ?? null;

        try {
            const res = await registerHomecare({ ...values, region: regionName });
            toast.success(res.message);
            navigate("/", { replace: true });
        } catch (error) {
            const errors = fieldErrors(error);
            Object.entries(errors).forEach(([field, messages]) => setError(field, { message: messages[0] }));
            if (!Object.keys(errors).length) toast.error(errorMessage(error));
        }
    };

    return (
        <AuthLayout wide>
            <p className="auth-eyebrow">Get started</p>
            <h2 className="auth-title">Register your homecare center</h2>
            <p className="auth-subtitle">You'll sign in with the owner's mobile number and manage your staff from there.</p>

            <form onSubmit={handleSubmit(onSubmit)} noValidate>
                <p className="auth-section-label">Center</p>
                <Grid container columnSpacing={2}>
                    <Grid item xs={12} sm={8}><RHFTextField name="name" control={control} label="Homecare center name" autoComplete="organization" /></Grid>
                    <Grid item xs={12} sm={4}><RHFTextField name="center_id" control={control} label="License / center ID (optional)" /></Grid>
                    <Grid item xs={12} sm={4}><RHFSelect name="country" control={control} label="Country" options={countryOptions} /></Grid>
                    <Grid item xs={12} sm={4}><RHFSelect name="region" control={control} label="Region" options={regionOptions} disabled={!country} /></Grid>
                    <Grid item xs={12} sm={4}><RHFSelect name="city" control={control} label="City" options={cityOptions} disabled={!region} /></Grid>
                    <Grid item xs={12}><RHFTextField name="address" control={control} label="Address (optional)" autoComplete="street-address" /></Grid>
                </Grid>

                <p className="auth-section-label">Owner account</p>
                <Grid container columnSpacing={2}>
                    <Grid item xs={12} sm={6}><RHFTextField name="owner" control={control} label="Owner's full name" autoComplete="name" /></Grid>
                    <Grid item xs={12} sm={6}><RHFTextField name="email" control={control} label="Email" type="email" autoComplete="email" /></Grid>
                    <Grid item xs={12} sm={6}><RHFTextField name="mobile" control={control} label="Mobile number" autoComplete="tel-national" inputProps={{ inputMode: "numeric" }} /></Grid>
                    <Grid item xs={12} sm={6} />
                    <Grid item xs={12} sm={6}><RHFTextField name="password" control={control} label="Password" type="password" autoComplete="new-password" /></Grid>
                    <Grid item xs={12} sm={6}><RHFTextField name="password_confirmation" control={control} label="Repeat password" type="password" autoComplete="new-password" /></Grid>
                </Grid>

                <div className="auth-btn-row" style={{ marginTop: 16 }}>
                    <button type="button" className="auth-btn-secondary" onClick={() => navigate("/")}>
                        Back to sign in
                    </button>
                    <button type="submit" className="auth-btn-primary" disabled={isSubmitting}>
                        {isSubmitting ? <><CircularProgress size={16} color="inherit" /> Registering…</> : "Register center"}
                    </button>
                </div>
            </form>
        </AuthLayout>
    );
};

export default RegisterHomecare;
