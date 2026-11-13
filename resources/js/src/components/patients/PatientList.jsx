import React, { useMemo, useState } from "react";
import { useNavigate } from "react-router-dom";
import DataTable, { ActionCell, TableBtn } from "../table/DataTable";
import { Badge, Button, Card, PageHeader, PersonCell, StatStrip } from "../common/ui";
import useAsync from "../../hooks/useAsync";
import usePermission from "../../hooks/usePermission";
import { patients as patientsApi } from "../../api/homecare";
import { fmtDate, isYes } from "../../utilities/format";

/** Consent, terms and payment at a glance (the legacy "Status" popup). */
export const ConsentBadges = ({ patient }) => (
    <div style={{ display: "flex", gap: 4, flexWrap: "wrap" }}>
        <Badge tone={isYes(patient.terms_conditions) ? "success" : "muted"}>Terms</Badge>
        <Badge tone={isYes(patient.consent_form) ? "success" : "muted"}>Consent</Badge>
        <Badge tone={isYes(patient.amount_paid) ? "success" : "warning"}>{isYes(patient.amount_paid) ? "Paid" : "Unpaid"}</Badge>
    </div>
);

const PatientList = () => {
    const navigate = useNavigate();
    const { can } = usePermission();
    const [status, setStatus] = useState("admitted");
    // Nurses and caretakers see their assigned patients; "All registered" lists
    // everyone, with basic details only for patients not assigned to them.
    const seesAll = can("patients.view_all");
    const [registered, setRegistered] = useState(false);
    const { data: rows, loading } = useAsync(
        () => (registered ? patientsApi.registered() : patientsApi.list(status === "all" ? "all" : undefined)),
        [status, registered],
        [],
    );
    const locked = (p) => p.assigned === false;

    const columns = useMemo(() => [
        {
            header: "Patient",
            accessorKey: "patient_name",
            cell: ({ row }) => <PersonCell name={row.original.patient_name} sub={row.original.username} />,
        },
        { header: "Mobile", accessorKey: "mobile", cell: ({ row }) => row.original.mobile || "—" },
        {
            header: "Age / Gender",
            id: "age",
            accessorFn: (p) => `${p.age ?? ""} ${p.gender ?? ""}`,
            cell: ({ row }) => [row.original.age, row.original.gender].filter(Boolean).join(" · ") || "—",
        },
        { header: "Package", accessorKey: "service_package", cell: ({ row }) => row.original.service_package || "—" },
        { header: "Status", id: "status", cell: ({ row }) => (locked(row.original) ? "—" : <ConsentBadges patient={row.original} />) },
        {
            header: "Discharge",
            id: "discharge",
            cell: ({ row }) => {
                const p = row.original;
                if (isYes(p.discharged)) return <Badge tone="muted">Discharged</Badge>;
                return p.discharge?.date ? <Badge tone="warning">{fmtDate(p.discharge.date)}</Badge> : "—";
            },
        },
        {
            header: "Actions",
            id: "actions",
            cell: ({ row }) => {
                const u = row.original.username;
                if (locked(row.original)) return <Badge tone="muted">Not assigned to you</Badge>;
                return (
                    <ActionCell>
                        <TableBtn onClick={() => navigate(`/patients/${u}`)}>View details</TableBtn>
                        {can("schedules.view") && <TableBtn variant="secondary" onClick={() => navigate(`/patients/${u}?tab=schedules`)}>Schedules</TableBtn>}
                        <TableBtn variant="secondary" onClick={() => navigate(`/patients/${u}/consent`)}>Consent</TableBtn>
                    </ActionCell>
                );
            },
        },
    ], [can, navigate]);

    return (
        <>
            <PageHeader
                eyebrow="Patient records"
                title="Patients"
                actions={
                    <>
                        {!seesAll && (
                            <Button variant="secondary" onClick={() => setRegistered(!registered)}>
                                {registered ? "My patients" : "All registered patients"}
                            </Button>
                        )}
                        {!registered && (
                            <Button variant="secondary" onClick={() => setStatus(status === "all" ? "admitted" : "all")}>
                                {status === "all" ? "Show admitted only" : "Include discharged"}
                            </Button>
                        )}
                        {can("patients.manage") && <Button onClick={() => navigate("/patients/new")}>+ Register patient</Button>}
                    </>
                }
            />
            <StatStrip items={[
                { label: registered ? "All registered" : status === "all" ? "All patients" : seesAll ? "Admitted" : "Assigned to you", value: rows.length },
                ...(registered ? [{ label: "Assigned to you", value: rows.filter((p) => !locked(p)).length }] : [
                    { label: "Consent pending", value: rows.filter((p) => !isYes(p.consent_form)).length },
                    { label: "Unpaid", value: rows.filter((p) => !isYes(p.amount_paid)).length },
                    { label: "Discharge planned", value: rows.filter((p) => p.discharge?.date && !isYes(p.discharged)).length },
                ]),
            ]} />
            <Card>
                <DataTable
                    data={rows}
                    columns={columns}
                    searchPlaceholder="Search by name, patient ID or mobile…"
                    emptyMessage={
                        loading ? "Loading…"
                            : !seesAll && !registered ? "No patients are assigned to you right now. Use \"All registered patients\" to see everyone."
                            : "No patients yet. Admit one from Assessments or register one directly."
                    }
                />
            </Card>
        </>
    );
};

export default PatientList;
