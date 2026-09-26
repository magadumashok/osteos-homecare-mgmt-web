import React, { useMemo, useState } from "react";
import { useNavigate } from "react-router-dom";
import { toast } from "react-toastify";
import DataTable, { ActionCell, TableBtn } from "../table/DataTable";
import { Badge, Card, ConfirmDialog, PageHeader, PersonCell, StatStrip } from "../common/ui";
import useAsync from "../../hooks/useAsync";
import usePermission from "../../hooks/usePermission";
import { assessments } from "../../api/homecare";
import { errorMessage } from "../../api/client";
import { fmtMoney } from "../../utilities/format";
import { SERVICES, invoiceTotals } from "../../utilities/invoice";

/** Assessed patients waiting to be admitted. */
const AssessmentList = () => {
    const navigate = useNavigate();
    const { can, canAny } = usePermission();
    const { data: rows, loading, reload } = useAsync(() => assessments.list(), [], []);
    const [toAdmit, setToAdmit] = useState(null);
    const [busy, setBusy] = useState(false);

    const admit = async () => {
        setBusy(true);
        try {
            const patient = await assessments.admit(toAdmit._id);
            toast.success(`${patient.patient_name} admitted as ${patient.username}.`);
            navigate(`/patients/${patient.username}`);
        } catch (e) {
            toast.error(errorMessage(e));
            reload();
        } finally {
            setBusy(false);
            setToAdmit(null);
        }
    };

    const columns = useMemo(() => [
        { header: "Patient", accessorKey: "patient_name", cell: ({ row }) => <PersonCell name={row.original.patient_name} sub={row.original.mobile} /> },
        { header: "Lead #", accessorKey: "assessment_id" },
        { header: "Diagnosis", accessorKey: "diagnosis_name", cell: ({ row }) => row.original.diagnosis_name || "—" },
        {
            header: "Care plan",
            id: "care",
            cell: ({ row }) => (
                <div style={{ display: "flex", gap: 4, flexWrap: "wrap" }}>
                    {SERVICES.filter(({ key }) => row.original.care_plan?.[key]).map(({ key, label }) => <Badge key={key}>{label}</Badge>)}
                </div>
            ),
        },
        { header: "Quotation", id: "total", cell: ({ row }) => fmtMoney(row.original.invoice_details?.total_amount ?? invoiceTotals(row.original).total) },
        {
            header: "Actions",
            id: "actions",
            cell: ({ row }) => {
                const a = row.original;
                return (
                    <ActionCell>
                        {can("assessments.manage") && <TableBtn variant="secondary" onClick={() => navigate(`/assessments/${a._id}/edit`)}>Edit</TableBtn>}
                        {canAny(["invoices.view", "assessments.view"]) && <TableBtn variant="secondary" onClick={() => navigate(`/assessments/${a._id}/invoice`)}>Invoice</TableBtn>}
                        {can("patients.manage") && <TableBtn onClick={() => setToAdmit(a)}>Admit</TableBtn>}
                    </ActionCell>
                );
            },
        },
    ], [can, canAny, navigate]);

    return (
        <>
            <PageHeader eyebrow="Patient intake" title="Assessments" subtitle="Assessed patients. Admitting one creates their patient record." />
            <StatStrip items={[
                { label: "Awaiting admission", value: rows.length },
                { label: "Nursing", value: rows.filter((r) => r.care_plan?.nursing).length },
                { label: "Physiotherapy", value: rows.filter((r) => r.care_plan?.physiotherapy).length },
            ]} />
            <Card>
                <DataTable data={rows} columns={columns} searchPlaceholder="Search by name, mobile or diagnosis…" emptyMessage={loading ? "Loading…" : "No assessments waiting. Record one from a lead."} />
            </Card>
            <ConfirmDialog
                open={!!toAdmit}
                title="Admit patient"
                message={toAdmit && `Admit ${toAdmit.patient_name}? A patient record is created from this assessment and the assessment is closed.`}
                confirmLabel="Admit"
                loading={busy}
                onConfirm={admit}
                onClose={() => setToAdmit(null)}
            />
        </>
    );
};

export default AssessmentList;
