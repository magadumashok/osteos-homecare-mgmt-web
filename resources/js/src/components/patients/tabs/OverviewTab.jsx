import React from "react";
import { Grid } from "@mui/material";
import { Badge, Card, InfoGrid, colors } from "../../common/ui";
import useAsync from "../../../hooks/useAsync";
import usePermission from "../../../hooks/usePermission";
import { patients as patientsApi } from "../../../api/homecare";
import { fmtDate, fmtMoney, fmtTime, humanize } from "../../../utilities/format";
import { SERVICES } from "../../../utilities/invoice";

const text = (v) => (typeof v === "string" ? v : Array.isArray(v) ? v.join(", ") : v && typeof v === "object" ? Object.keys(v).filter((k) => v[k] === true).map(humanize).join(", ") : v);

const EQUIPMENT_LABELS = {
    bed: "Medical bed", nurse_bed_chair: "Bed / chair for nurse", wheelchair_walker: "Wheelchair / walker",
    electrotherapy_device: "Electrotherapy device",
};

const equipmentText = (e) => {
    if (!e || typeof e !== "object") return text(e);
    const items = Object.keys(EQUIPMENT_LABELS).filter((k) => e[k] === true).map((k) => EQUIPMENT_LABELS[k]);
    if (e.others && e.other_equipment_details) items.push(e.other_equipment_details);
    return items.join(", ");
};

/** Profile: personal details, clinical summary from the assessment, services and latest vitals. */
const OverviewTab = ({ patient: p }) => {
    const { can } = usePermission();
    const { data: vitals } = useAsync(() => (can("vitals.view") ? patientsApi.latestVitals(p.username) : null), [p.username]);
    const services = Array.isArray(p.services) ? p.services : [];
    const address = [p.address, p.city, p.state].filter(Boolean).join(", ");

    return (
        <Grid container spacing={2}>
            <Grid item xs={12} md={8}>
                <Card label="Personal details">
                    <InfoGrid items={[
                        { label: "Patient ID", value: p.username },
                        { label: "Mobile", value: p.mobile },
                        { label: "Email", value: p.email },
                        { label: "Gender", value: p.gender },
                        { label: "Age", value: p.age },
                        { label: "Date of birth", value: fmtDate(p.date_of_birth ?? p.birth_date) },
                        { label: "Nationality", value: p.nationality },
                        { label: "ID number", value: p.id_no ?? p.identity_number },
                        { label: "Insurance", value: p.insurance_name },
                        { label: "Weight", value: p.weight && `${p.weight} kg` },
                        { label: "Source", value: p.source_of_contact },
                        { label: "Address", value: address && (p.address_link ? <a href={p.address_link} target="_blank" rel="noreferrer">{address}</a> : address) },
                    ]} />
                </Card>

                <Card label="Clinical summary" style={{ marginTop: 16 }}>
                    <InfoGrid columns={2} items={[
                        { label: "Diagnosis", value: text(p.diagnosis_name ?? p.diagnosis) },
                        { label: "Physician", value: p.physician },
                        { label: "Physical assessment", value: text(p.physical_assessment) },
                        { label: "Daily activities", value: text(p.daily_activities) },
                        { label: "Medications", value: text(p.medications) },
                        { label: "Equipment", value: equipmentText(p.equipment) },
                        {
                            label: "Care plan",
                            value: (
                                <div style={{ display: "flex", gap: 4, flexWrap: "wrap" }}>
                                    {SERVICES.filter(({ key }) => p.care_plan?.[key] || p[key]?.fees).map(({ key, label }) => <Badge key={key}>{label}</Badge>)}
                                </div>
                            ),
                        },
                        { label: "Comments", value: p.comments },
                    ]} />
                </Card>
            </Grid>

            <Grid item xs={12} md={4}>
                <Card label="Services">
                    {services.length ? services.map((s, i) => (
                        <div key={i} style={{ display: "flex", justifyContent: "space-between", fontSize: 13, padding: "6px 0", borderBottom: `1px solid ${colors.line}` }}>
                            <span>{s.service_package ?? s.name ?? "Service"}</span>
                            <strong>{fmtMoney(s.fees)}</strong>
                        </div>
                    )) : <p style={{ fontSize: 13, color: colors.muted, margin: 0 }}>{p.service_package ? `${p.service_package} · ${fmtMoney(p.fees)}` : "No service package."}</p>}
                </Card>

                {can("vitals.view") && (
                    <Card label="Latest vital signs" style={{ marginTop: 16 }}>
                        {vitals && vitals.date ? (
                            <>
                                <div style={{ fontSize: 12, color: colors.muted, marginBottom: 10 }}>{fmtDate(vitals.date)} {fmtTime(vitals.time)} · {vitals.nurse_name}</div>
                                <InfoGrid columns={2} items={[
                                    { label: "Blood pressure", value: vitals.patient_bp_systolic_value && `${vitals.patient_bp_systolic_value}/${vitals.patient_bp_diastolic_value ?? "—"}` },
                                    { label: "Pulse", value: vitals.pulse },
                                    { label: "Temperature", value: vitals.temperature },
                                    { label: "SpO₂", value: vitals.sat_O2 },
                                    { label: "Respiration", value: vitals.resp },
                                ]} />
                            </>
                        ) : <p style={{ fontSize: 13, color: colors.muted, margin: 0 }}>No readings yet.</p>}
                    </Card>
                )}
            </Grid>
        </Grid>
    );
};

export default OverviewTab;
