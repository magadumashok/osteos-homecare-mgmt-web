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

// Uploaded after the staff member is saved (POST staff/{id}/documents).
const DOCUMENTS = [
    { field: "profile_image", label: "Profile photo", accept: "image/*", hint: "JPG or PNG" },
    // A photo, not a PDF: it goes into the staff profile PDF sent to patients.
    { field: "aadhaar_card", label: "Aadhaar card", accept: "image/jpeg,image/png,image/webp", hint: "Photo of the card (JPG or PNG)" },
    { field: "police_verification", label: "Police verification", accept: "image/*,application/pdf", hint: "Image or PDF" },
];
const isPdf = (url) => /\.pdf($|\?)/i.test(url ?? "");

/** One document: what is saved now, the file chosen to replace it, and remove. */
const DocumentPicker = ({ doc, saved, chosen, onChoose, onRemove, disabled }) => {
    const preview = useMemo(() => (chosen && chosen.type.startsWith("image/") ? URL.createObjectURL(chosen) : null), [chosen]);
    useEffect(() => () => preview && URL.revokeObjectURL(preview), [preview]);
    const shown = preview ?? (saved && !isPdf(saved) ? saved : null);

    return (
        <div style={{ border: "1px solid #e3e8ee", borderRadius: 10, padding: 12, display: "flex", flexDirection: "column", gap: 8, height: "100%" }}>
            <div style={{ fontSize: 13, fontWeight: 600 }}>{doc.label}</div>
            <div style={{ height: 120, borderRadius: 8, background: "#f4f6f8", display: "flex", alignItems: "center", justifyContent: "center", overflow: "hidden" }}>
                {shown ? (
                    <img src={shown} alt={doc.label} style={{ maxWidth: "100%", maxHeight: "100%", objectFit: "contain" }} />
                ) : (
                    <span style={{ fontSize: 12, color: "#8a9baa" }}>
                        {chosen ? chosen.name : saved ? "PDF document" : "Not uploaded"}
                    </span>
                )}
            </div>
            {chosen && <div style={{ fontSize: 12, color: "#8a9baa", wordBreak: "break-all" }}>New: {chosen.name}</div>}
            <div style={{ display: "flex", gap: 8, flexWrap: "wrap", alignItems: "center" }}>
                <label style={{ fontSize: 13, color: "#1a7f8e", cursor: disabled ? "default" : "pointer", fontWeight: 600 }}>
                    {saved || chosen ? "Replace" : "Choose file"}
                    <input
                        type="file"
                        accept={doc.accept}
                        hidden
                        disabled={disabled}
                        onChange={(e) => {
                            onChoose(e.target.files?.[0] ?? null);
                            e.target.value = "";
                        }}
                    />
                </label>
                {chosen && (
                    <button type="button" onClick={() => onChoose(null)} disabled={disabled} style={linkBtn}>Undo</button>
                )}
                {saved && !chosen && (
                    <>
                        <a href={saved} target="_blank" rel="noreferrer" style={{ fontSize: 13 }}>View</a>
                        <button type="button" onClick={onRemove} disabled={disabled} style={{ ...linkBtn, color: "#c0392b" }}>Remove</button>
                    </>
                )}
            </div>
            <div style={{ fontSize: 11, color: "#8a9baa" }}>{doc.hint}</div>
        </div>
    );
};
const linkBtn = { background: "none", border: 0, padding: 0, fontSize: 13, color: "#1a7f8e", cursor: "pointer" };

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
    const [files, setFiles] = useState({}); // field → File chosen, uploaded on save
    const chooseFile = (field) => (file) => setFiles((f) => ({ ...f, [field]: file }));

    const removeDocument = async (field, label) => {
        if (!window.confirm(`Remove the ${label.toLowerCase()}?`)) return;
        try {
            setMember(await staff.removeDocument(id, field));
            toast.success(`${label} removed.`);
        } catch (e) {
            toast.error(errorMessage(e));
        }
    };

    const { control, handleSubmit, reset, setValue, setError, getValues } = useForm({
        defaultValues: {
            name: "", 
            // userID: "",
            role: "", roles: [], mobile: "", 
            // alternate_mobile: "",
            email: "", age: "", experience: "", daily_salary: "",
            password: "", confirm_password: "", 
            // identity_type: "", identity_number: "",
            address: EMPTY_ADDRESS
        },
    });
    // const userID = useWatch({ control, name: "userID" });
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
                    name: u.name ?? "", mobile: u.mobile ?? "", 
                    // alternate_mobile: u.alternate_mobile ?? "",
                    email: u.email ?? "", age: u.age ?? "", experience: u.experience ?? "", roles: u.roles ?? [],
                    daily_salary: u.daily_salary ?? "",
                    // identity_type: u.identity_type ?? "", identity_number: u.identity_number ?? "",
                    address: { ...EMPTY_ADDRESS, ...(u.address ?? {}) },
                    // permanent_address: { ...EMPTY_ADDRESS, ...(u.permanent_address ?? {}) },
                });
            })
            .catch((e) => active && toast.error(errorMessage(e)))
            .finally(() => active && setLoading(false));
        return () => { active = false; };
    }, [id, isEdit, reset]);

    // Preview of the username the server will generate: <center prefix><role letter><id>.
    const letter = useMemo(() => ({ admin: "a", nurse: "n", physiotherapist: "p", doctor: "d", staff: "s", maintenance: "m", transport: "t" }[role] ?? role?.[0] ?? ""), [role]);

    const onSubmit = async (values) => {
        console.log("staff form on submit",values)
        const payload = {
            ...compact({
                name: values.name, mobile: digits(values.mobile), 
                // alternate_mobile: digits(values.alternate_mobile), 
                email: values.email,
                age: values.age, 
                // identity_type: values.identity_type, identity_number: values.identity_number,
            }),
            address: compact(values.address),
            // Sent even when cleared, so it can be removed.
            experience: values.experience?.trim() || null,
            daily_salary: values.daily_salary === "" || values.daily_salary === null ? null : Number(values.daily_salary),
            // permanent_address: compact(sameAddress ? values.temporary_address : values.permanent_address),
        };

        const uploads = Object.fromEntries(Object.entries(files).filter(([, file]) => file));

        setSaving(true);
        try {
            let savedId = id;
            if (isEdit) {
                await staff.update(id, payload);
                if (can("roles.manage") && values.roles.join() !== (member?.roles ?? []).join()) {
                    await staff.setRoles(id, values.roles);
                }
            } else {
                const created = await staff.create({ ...payload, role: values.role,
                    // userID: values.userID,
                    password: values.password });
                savedId = created._id ?? created.id;
                toast.success(`${created.name} added. Username: ${created.username}`);
            }

            // The staff member is saved; documents go up separately, so a failed
            // upload keeps them and opens their edit page to try again.
            if (Object.keys(uploads).length) {
                try {
                    await staff.uploadDocuments(savedId, uploads);
                } catch (e) {
                    toast.error(`Staff member saved, but the documents were not uploaded: ${errorMessage(e)}`);
                    if (!isEdit) navigate(`/staff/${savedId}/edit`, { replace: true });
                    return;
                }
            }
            if (isEdit) toast.success("Staff member updated.");
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
                            <RHFTextField
                                name="experience"
                                control={control}
                                label="Experience"
                                placeholder="e.g. 5 years, ICU and home care"
                                inputProps={{ maxLength: 300 }}
                            />
                        </Grid>
                        <Grid item xs={12} sm={6}>
                            <RHFTextField
                                name="daily_salary"
                                control={control}
                                label="Salary (1 day)"
                                type="number"
                                inputProps={{ min: 0, step: "any" }}
                                rules={{ min: { value: 0, message: "Cannot be negative" } }}
                                helperText="Paid for each day with a completed sign in and sign out"
                            />
                        </Grid>
                        {/* <Grid item xs={12} sm={6}>
                            <RHFTextField name="alternate_mobile" control={control} label="Alternate mobile" />
                        </Grid>
                        <Grid item xs={12} sm={6}>
                            <RHFSelect name="identity_type" control={control} label="ID type" options={ID_TYPES} />
                        </Grid>
                        <Grid item xs={12} sm={6}>
                            <RHFTextField name="identity_number" control={control} label="ID number" />
                        </Grid> */}
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
                            {/* <Grid item xs={12} sm={6}>
                                <RHFTextField
                                    name="userID"
                                    control={control}
                                    label="User ID *"
                                    rules={{ required: "User ID is required", pattern: { value: /^[A-Za-z0-9_-]+$/, message: "Letters, digits, - or _" } }}
                                    helperText={userID ? `Username will be …${letter}${userID.toLowerCase()}` : "Used to build the username"}
                                />
                            </Grid> */}
                            <Grid item xs={12} sm={6}>
                                <RHFTextField name="password" control={control} label="Password *" type="password" autoComplete="new-password" rules={{ required: "Password is required", minLength: { value: 8, message: "At least 8 characters" } }} helperText="At least 8 characters" />
                            </Grid>
                            <Grid item xs={12} sm={6}>
                                <RHFTextField name="confirm_password" control={control} label="Confirm password *" type="password" autoComplete="new-password" rules={{ validate: (v) => v === getValues("password") || "Passwords do not match" }} />
                            </Grid>
                        </Grid>
                    )}
                </Card>

                <Card label="Address" style={{ marginTop: 16 }}>
                    <AddressFields control={control} setValue={setValue} prefix="address" required />
                </Card>

                <Card label="Photo & documents" style={{ marginTop: 16 }}>
                    <Grid container spacing={2}>
                        {DOCUMENTS.map((doc) => (
                            <Grid item xs={12} sm={4} key={doc.field}>
                                <DocumentPicker
                                    doc={doc}
                                    saved={member?.[doc.field]}
                                    chosen={files[doc.field]}
                                    onChoose={chooseFile(doc.field)}
                                    onRemove={() => removeDocument(doc.field, doc.label)}
                                    disabled={saving}
                                />
                            </Grid>
                        ))}
                    </Grid>
                    <p style={{ fontSize: 12, color: "#8a9baa", margin: "10px 0 0" }}>
                        Uploaded when you {isEdit ? "save changes" : "register the staff member"}. Only people who can open the staff record see the Aadhaar card and police verification. With a photo and Aadhaar card, a profile PDF (photo, name, experience, Aadhaar) is sent to patients with their duty WhatsApp.
                    </p>
                </Card>

                {/* <Card
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
                </Card> */}

                <div style={{ display: "flex", justifyContent: "flex-end", gap: 12, marginTop: 24 }}>
                    <Button variant="secondary" onClick={() => navigate(-1)} disabled={saving}>Cancel</Button>
                    <Button type="submit" loading={saving}>{isEdit ? "Save changes" : "Register staff →"}</Button>
                </div>
            </form>
        </div>
    );
};

export default StaffForm;
