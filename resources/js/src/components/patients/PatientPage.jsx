import React, { useEffect, useMemo } from "react";
import { useNavigate, useOutletContext, useParams, useSearchParams } from "react-router-dom";
import { Tab, Tabs } from "@mui/material";
import { Avatar, Badge, Button, Loader, cardStyle, colors } from "../common/ui";
import useAsync from "../../hooks/useAsync";
import usePermission from "../../hooks/usePermission";
import { patients as patientsApi } from "../../api/homecare";
import { fmtDate, isYes } from "../../utilities/format";
import { ConsentBadges } from "./PatientList";
import OverviewTab from "./tabs/OverviewTab";
import SchedulesTab from "./tabs/SchedulesTab";
import VitalsTab from "./tabs/VitalsTab";
import MedicationTab from "./tabs/MedicationTab";
import NotesTab from "./tabs/NotesTab";
import DocumentsTab from "./tabs/DocumentsTab";
import PrescriptionsTab from "./tabs/PrescriptionsTab";
import DischargeTab from "./tabs/DischargeTab";

// The legacy "patient details dashboard" cards, as tabs. `any` = permissions that show the tab.
const TABS = [
    { key: "overview", label: "Profile", Component: OverviewTab },
    { key: "schedules", label: "Schedules", any: ["schedules.view"], Component: SchedulesTab },
    { key: "vitals", label: "Vital signs", any: ["vitals.view"], Component: VitalsTab },
    { key: "medication", label: "Medication", any: ["medications.view"], Component: MedicationTab },
    { key: "notes", label: "Notes", any: ["notes.view"], Component: NotesTab },
    { key: "documents", label: "Documents & IDs", any: ["documents.view"], Component: DocumentsTab },
    { key: "prescriptions", label: "Prescriptions", any: ["prescriptions.view"], Component: PrescriptionsTab },
    { key: "discharge", label: "Discharge", any: ["discharge.view", "discharge.manage"], Component: DischargeTab },
];

const PatientPage = () => {
    const { username } = useParams();
    const navigate = useNavigate();
    const [search, setSearch] = useSearchParams();
    const { can, canAny } = usePermission();
    const { setHeader } = useOutletContext() ?? {};
    const { data: patient, reload } = useAsync(() => patientsApi.show(username), [username]);

    const tabs = useMemo(() => TABS.filter((t) => !t.any || canAny(t.any)), [canAny]);
    const active = tabs.find((t) => t.key === search.get("tab")) ?? tabs[0];

    useEffect(() => {
        if (patient && setHeader) setHeader({ breadcrumb: ["Patients", patient.patient_name] });
    }, [patient, setHeader]);

    if (!patient) return <Loader />;

    const discharged = isYes(patient.discharged);
    const { Component } = active;

    return (
        <>
            <div style={{ ...cardStyle, display: "flex", alignItems: "center", gap: 18, flexWrap: "wrap", marginBottom: 16 }}>
                <Avatar name={patient.patient_name} size={56} />
                <div style={{ flex: 1, minWidth: 220 }}>
                    <p style={{ fontSize: 11, color: colors.muted, textTransform: "uppercase", letterSpacing: ".8px", margin: "0 0 2px" }}>Patient · {patient.username}</p>
                    <h2 style={{ fontFamily: "'Playfair Display', serif", fontSize: 24, fontWeight: 700, color: colors.ink, margin: 0 }}>{patient.patient_name}</h2>
                    <div style={{ fontSize: 13, color: colors.body, marginTop: 4 }}>
                        {[patient.gender, patient.age && `${patient.age} yrs`, patient.mobile, patient.service_package].filter(Boolean).join(" · ")}
                    </div>
                    <div style={{ display: "flex", gap: 6, marginTop: 8, flexWrap: "wrap" }}>
                        <ConsentBadges patient={patient} />
                        {discharged
                            ? <Badge tone="muted">Discharged</Badge>
                            : patient.discharge?.date && <Badge tone="warning">Discharge {fmtDate(patient.discharge.date)}</Badge>}
                    </div>
                </div>
                <div style={{ display: "flex", gap: 10, flexWrap: "wrap" }}>
                    <Button variant="secondary" small onClick={() => navigate(`/patients/${username}/consent`)}>Consent form</Button>
                    {can("patients.manage") && <Button variant="secondary" small onClick={() => navigate(`/patients/${username}/edit`)}>Edit</Button>}
                    {can("schedules.manage") && !discharged && <Button small onClick={() => navigate(`/schedules/new?patient=${username}`)}>+ Schedule duty</Button>}
                </div>
            </div>

            <div style={{ ...cardStyle, padding: "0 12px", marginBottom: 16 }}>
                <Tabs
                    value={active.key}
                    onChange={(_, key) => setSearch(key === "overview" ? {} : { tab: key }, { replace: true })}
                    variant="scrollable"
                    scrollButtons="auto"
                    sx={{ "& .MuiTab-root": { textTransform: "none", fontWeight: 600, fontSize: 13 }, "& .Mui-selected": { color: `${colors.brandDark} !important` }, "& .MuiTabs-indicator": { background: colors.brand } }}
                >
                    {tabs.map((t) => <Tab key={t.key} value={t.key} label={t.label} />)}
                </Tabs>
            </div>

            <Component patient={patient} reloadPatient={reload} />
        </>
    );
};

export default PatientPage;
