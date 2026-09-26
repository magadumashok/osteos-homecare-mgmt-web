import React, { useEffect, useMemo, useState } from "react";
import { useNavigate, useParams } from "react-router-dom";
import { useForm, useWatch } from "react-hook-form";
import { Checkbox, FormControlLabel, Grid } from "@mui/material";
import { toast } from "react-toastify";
import RHFTextField from "../forms/RHFTextField";
import RHFSelect from "../forms/RHFSelect";
import RHFMultiSelect from "../forms/RHFMultiSelect";
import AddressFields from "../common/AddressFields";
import { Button, Card, Loader, PageHeader } from "../common/ui";
import usePermission from "../../hooks/usePermission";
import { roles as rolesApi, staff } from "../../api/homecare";
import { errorMessage, fieldErrors } from "../../api/client";
import { compact, humanize } from "../../utilities/format";
import { onInvalid } from "../../utilities/formErrors";

const ID_TYPES = ["Emirates ID", "Passport", "Aadhaar", "National ID", "Driving licence"].map((v) => ({ value: v, label: v }));
const EMPTY_ADDRESS = { country: "", region: "", city: "", address: "" };

// "050 123-4567" → "0501234567" (the API takes digits only).
const digits = (v) => String(v ?? "").replace(/[\s-]/g, "");

const StaffForm = () => {
    const { id } = useParams();
    const isEdit = Boolean(id);
    const navigate = useNavigate();
    const { can } = usePermission();

    const [loading, setLoading] = useState(isEdit);
    const [saving, setSaving] = useState(false);
    const [roleOptions, setRoleOptions] = useState([]);
    const [sameAddress, setSameAddress] = useState(false);
    const [member, setMember] = useState(null);

    const { control, handleSubmit, reset, setValue, setError, getValues } = useForm({
        defaultValues: {
            name: "", userID: "", role: "", roles: [], mobile: "", alternate_mobile: "", email: "", age: "",
            password: "", confirm_password: "", identity_type: "", identity_number: "",
            temporary_address: EMPTY_ADDRESS, permanent_address: EMPTY_ADDRESS,
        },
    });
    const userID = useWatch({ control, name: "userID" });
    const role = useWatch({ control, name: "role" });

    useEffect(() => {
        rolesApi.list()
            .then((list) => setRoleOptions(list.map((r) => ({ value: r.name, label: humanize(r.name) }))))
            .catch(() => setRoleOptions([]));
    }, []);

    useEffect(() => {
        if (!isEdit) return undefined;
        let active = true; // ignore a stale load (see AssessmentForm)
        staff.show(id)
            .then((u) => {
                if (!active) return;
                setMember(u);
                reset({
                    name: u.name ?? "", mobile: u.mobile ?? "", alternate_mobile: u.alternate_mobile ?? "",
                    email: u.email ?? "", age: u.age ?? "", roles: u.roles ?? [],
                    identity_type: u.identity_type ?? "", identity_number: u.identity_number ?? "",
                    temporary_address: { ...EMPTY_ADDRESS, ...(u.temporary_address ?? {}) },
                    permanent_address: { ...EMPTY_ADDRESS, ...(u.permanent_address ?? {}) },
                });
            })
            .catch((e) => active && toast.error(errorMessage(e)))
            .finally(() => active && setLoading(false));
        return () => { active = false; };
    }, [id, isEdit, reset]);

    // Preview of the username the server will generate: <center prefix><role letter><id>.
    const letter = useMemo(() => ({ admin: "a", nurse: "n", physiotherapist: "p", doctor: "d", staff: "s", maintenance: "m", transport: "t" }[role] ?? role?.[0] ?? ""), [role]);

    const onSubmit = async (values) => {
        const payload = {
            ...compact({
                name: values.name, mobile: digits(values.mobile), alternate_mobile: digits(values.alternate_mobile), email: values.email,
                age: values.age, identity_type: values.identity_type, identity_number: values.identity_number,
            }),
            temporary_address: compact(values.temporary_address),
            permanent_address: compact(sameAddress ? values.temporary_address : values.permanent_address),
        };

        setSaving(true);
        try {
            if (isEdit) {
                await staff.update(id, payload);
                if (can("roles.manage") && values.roles.join() !== (member?.roles ?? []).join()) {
                    await staff.setRoles(id, values.roles);
                }
                toast.success("Staff member updated.");
            } else {
                const created = await staff.create({ ...payload, role: values.role, userID: values.userID, password: values.password });
                toast.success(`${created.name} added. Username: ${created.username}`);
            }
            navigate("/staff");
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
                eyebrow={isEdit ? "Edit staff member" : "New staff member"}
                title={isEdit ? member?.name ?? "Staff member" : "Register Staff"}
                subtitle={isEdit ? `Username ${member?.username}` : "They sign in to the mobile app with their mobile number and password."}
            />

            <form onSubmit={handleSubmit(onSubmit, onInvalid)} noValidate>
                <Card label="Personal information">
                    <Grid container columnSpacing={2}>
                        <Grid item xs={12} sm={6}>
                            <RHFTextField name="name" control={control} label="Full name *" rules={{ required: "Name is required" }} />
                        </Grid>
                        <Grid item xs={12} sm={3}>
                            <RHFTextField name="age" control={control} label="Age" type="number" />
                        </Grid>
                        <Grid item xs={12} sm={3}>
                            <RHFTextField name="email" control={control} label="Email" type="email" />
                        </Grid>
                        <Grid item xs={12} sm={6}>
                            <RHFTextField name="mobile" control={control} label="Mobile (without country code) *" rules={{ required: "Mobile is required", pattern: { value: /^\+?[\d\s-]{6,20}$/, message: "Enter the number using digits" } }} />
                        </Grid>
                        <Grid item xs={12} sm={6}>
                            <RHFTextField name="alternate_mobile" control={control} label="Alternate mobile" />
                        </Grid>
                        <Grid item xs={12} sm={6}>
                            <RHFSelect name="identity_type" control={control} label="ID type" options={ID_TYPES} />
                        </Grid>
                        <Grid item xs={12} sm={6}>
                            <RHFTextField name="identity_number" control={control} label="ID number" />
                        </Grid>
                    </Grid>
                </Card>

                <Card label="Account & role" style={{ marginTop: 16 }}>
                    {isEdit ? (
                        can("roles.manage") ? (
                            <RHFMultiSelect name="roles" control={control} label="Roles" options={roleOptions} rules={{ validate: (v) => v?.length > 0 || "At least one role" }} />
                        ) : (
                            <p style={{ fontSize: 13, margin: 0 }}>Roles: {(member?.roles ?? []).map(humanize).join(", ")}</p>
                        )
                    ) : (
                        <Grid container columnSpacing={2}>
                            <Grid item xs={12} sm={6}>
                                <RHFSelect name="role" control={control} label="Role *" options={roleOptions} rules={{ required: "Choose a role" }} />
                            </Grid>
                            <Grid item xs={12} sm={6}>
                                <RHFTextField
                                    name="userID"
                                    control={control}
                                    label="User ID *"
                                    rules={{ required: "User ID is required", pattern: { value: /^[A-Za-z0-9_-]+$/, message: "Letters, digits, - or _" } }}
                                    helperText={userID ? `Username will be …${letter}${userID.toLowerCase()}` : "Used to build the username"}
                                />
                            </Grid>
                            <Grid item xs={12} sm={6}>
                                <RHFTextField name="password" control={control} label="Password *" type="password" autoComplete="new-password" rules={{ required: "Password is required", minLength: { value: 8, message: "At least 8 characters" } }} helperText="At least 8 characters" />
                            </Grid>
                            <Grid item xs={12} sm={6}>
                                <RHFTextField name="confirm_password" control={control} label="Confirm password *" type="password" autoComplete="new-password" rules={{ validate: (v) => v === getValues("password") || "Passwords do not match" }} />
                            </Grid>
                        </Grid>
                    )}
                </Card>

                <Card label="Current address" style={{ marginTop: 16 }}>
                    <AddressFields control={control} setValue={setValue} prefix="temporary_address" required />
                </Card>

                <Card
                    label="Permanent address"
                    style={{ marginTop: 16 }}
                    actions={
                        <FormControlLabel
                            control={<Checkbox size="small" checked={sameAddress} onChange={(e) => setSameAddress(e.target.checked)} />}
                            label={<span style={{ fontSize: 12 }}>Same as current address</span>}
                        />
                    }
                >
                    {!sameAddress && <AddressFields control={control} setValue={setValue} prefix="permanent_address" />}
                </Card>

                <div style={{ display: "flex", justifyContent: "flex-end", gap: 12, marginTop: 24 }}>
                    <Button variant="secondary" onClick={() => navigate(-1)} disabled={saving}>Cancel</Button>
                    <Button type="submit" loading={saving}>{isEdit ? "Save changes" : "Register staff →"}</Button>
                </div>
            </form>
        </div>
    );
};

export default StaffForm;
