import React, { useMemo, useState } from "react";
import { useNavigate } from "react-router-dom";
import DataTable, { ActionCell, TableBtn } from "../table/DataTable";
import { Card, Modal, PageHeader, PersonCell, StatStrip, colors } from "../common/ui";
import useAsync from "../../hooks/useAsync";
import { patients as patientsApi } from "../../api/homecare";
import { fmtDate, fmtTime } from "../../utilities/format";

const DischargedList = () => {
    const navigate = useNavigate();
    const { data: rows, loading } = useAsync(() => patientsApi.discharged(), [], []);
    const [summary, setSummary] = useState(null);

    const columns = useMemo(() => [
        { header: "Patient", accessorKey: "patient_name", cell: ({ row }) => <PersonCell name={row.original.patient_name} sub={row.original.username} /> },
        { header: "Discharged", accessorKey: "discharged_date", cell: ({ row }) => `${fmtDate(row.original.discharged_date)} ${fmtTime(row.original.discharged_time)}` },
        { header: "By", accessorKey: "discharged_by", cell: ({ row }) => row.original.discharged_by ?? "—" },
        {
            header: "Summary",
            accessorKey: "discharged_summary",
            cell: ({ row }) => <span style={{ fontSize: 12, color: colors.body }}>{String(row.original.discharged_summary ?? "—").slice(0, 80)}{String(row.original.discharged_summary ?? "").length > 80 ? "…" : ""}</span>,
        },
        {
            header: "Actions",
            id: "actions",
            cell: ({ row }) => (
                <ActionCell>
                    <TableBtn variant="secondary" onClick={() => setSummary(row.original)}>Summary</TableBtn>
                    <TableBtn onClick={() => navigate(`/patients/${row.original.username}`)}>Record</TableBtn>
                </ActionCell>
            ),
        },
    ], [navigate]);

    return (
        <>
            <PageHeader eyebrow="Patient records" title="Discharged Patients" />
            <StatStrip items={[{ label: "Discharged", value: rows.length }]} />
            <Card>
                <DataTable data={rows} columns={columns} searchPlaceholder="Search by name or patient ID…" emptyMessage={loading ? "Loading…" : "No discharged patients"} />
            </Card>
            {summary && (
                <Modal open title={`Discharge summary · ${summary.patient_name}`} onClose={() => setSummary(null)}>
                    <div style={{ fontSize: 12, color: colors.muted, marginBottom: 10 }}>{fmtDate(summary.discharged_date)} {fmtTime(summary.discharged_time)} · {summary.discharged_by}</div>
                    <div style={{ fontSize: 14, whiteSpace: "pre-wrap", color: colors.ink }}>{summary.discharged_summary || "No summary written."}</div>
                </Modal>
            )}
        </>
    );
};

export default DischargedList;
