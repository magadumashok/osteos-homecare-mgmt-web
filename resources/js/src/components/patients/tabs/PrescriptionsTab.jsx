import React, { useMemo, useState } from "react";
import { useNavigate } from "react-router-dom";
import { IconButton, MenuItem, TextField, ToggleButton, ToggleButtonGroup } from "@mui/material";
import DeleteOutline from "@mui/icons-material/DeleteOutline";
import { toast } from "react-toastify";
import DataTable, { ActionCell, TableBtn } from "../../table/DataTable";
import { Button, Card, Modal, colors } from "../../common/ui";
import useAsync from "../../../hooks/useAsync";
import usePermission from "../../../hooks/usePermission";
import { patients as patientsApi } from "../../../api/homecare";
import { errorMessage } from "../../../api/client";
import { fmtDate, today } from "../../../utilities/format";

// Row fields as the legacy mobile app stores them.
export const MEDICINE_FIELDS = [
    ["drug_name", "Medicine"],
    ["strength", "Strength"],
    ["medication_frequency", "Frequency"],
    ["medication_count", "Count"],
    ["before_or_after_food", "Before / after food"],
];
export const TEST_FIELDS = [["service_package", "Test"]];

const FOOD = ["Before food", "After food", "With food"];

const PrescriptionDialog = ({ patient, type, onClose, onSaved }) => {
    const fields = type === "medicine" ? MEDICINE_FIELDS : TEST_FIELDS;
    const empty = () => Object.fromEntries(fields.map(([k]) => [k, ""]));
    const [rows, setRows] = useState([empty()]);
    const [date, setDate] = useState(today());
    const [saving, setSaving] = useState(false);

    const set = (i, k, v) => setRows((r) => r.map((row, idx) => (idx === i ? { ...row, [k]: v } : row)));
    const filled = rows.filter((r) => String(r[fields[0][0]]).trim());

    const save = async () => {
        setSaving(true);
        try {
            await patientsApi.addPrescription(patient.username, type, { date, prescription_data: filled });
            toast.success("Prescription saved.");
            onSaved();
        } catch (e) {
            toast.error(errorMessage(e));
        } finally {
            setSaving(false);
        }
    };

    return (
        <Modal open title={type === "medicine" ? "Medicine prescription" : "Test prescription"} onClose={onClose} maxWidth="lg"
            actions={<><Button variant="secondary" small onClick={onClose}>Cancel</Button><Button small loading={saving} disabled={!filled.length} onClick={save}>Save prescription</Button></>}>
            <TextField size="small" type="date" label="Date" value={date} onChange={(e) => setDate(e.target.value)} InputLabelProps={{ shrink: true }} sx={{ mb: 2 }} />
            {rows.map((row, i) => (
                <div key={i} style={{ display: "flex", gap: 8, alignItems: "center", marginBottom: 8 }}>
                    {fields.map(([k, label]) => k === "before_or_after_food" ? (
                        <TextField key={k} select size="small" label={label} value={row[k]} onChange={(e) => set(i, k, e.target.value)} sx={{ minWidth: 150 }}>
                            <MenuItem value="">—</MenuItem>
                            {FOOD.map((f) => <MenuItem key={f} value={f}>{f}</MenuItem>)}
                        </TextField>
                    ) : (
                        <TextField key={k} size="small" label={label} value={row[k]} onChange={(e) => set(i, k, e.target.value)} sx={{ flex: k === fields[0][0] ? 2 : 1 }} />
                    ))}
                    <IconButton size="small" aria-label="Remove line" disabled={rows.length === 1} onClick={() => setRows((r) => r.filter((_, idx) => idx !== i))}>
                        <DeleteOutline fontSize="small" />
                    </IconButton>
                </div>
            ))}
            <Button variant="secondary" small onClick={() => setRows((r) => [...r, empty()])}>+ Add line</Button>
        </Modal>
    );
};

const PrescriptionsTab = ({ patient }) => {
    const navigate = useNavigate();
    const { can } = usePermission();
    const [type, setType] = useState("medicine");
    const [writing, setWriting] = useState(false);
    const { data: rows, loading, reload } = useAsync(() => patientsApi.prescriptions(patient.username, type), [patient.username, type], []);

    const columns = useMemo(() => [
        { header: "Date", accessorKey: "date", cell: ({ row }) => fmtDate(row.original.date) },
        {
            header: type === "medicine" ? "Medicines" : "Tests",
            id: "items",
            cell: ({ row }) => {
                const items = Array.isArray(row.original.prescription_data) ? row.original.prescription_data : [];
                const key = type === "medicine" ? "drug_name" : "service_package";
                return <span style={{ fontSize: 13 }}>{items.map((it) => it?.[key] ?? it?.name).filter(Boolean).join(", ") || "—"}</span>;
            },
        },
        { header: "Doctor", accessorKey: "doctor_id" },
        {
            header: "Actions",
            id: "actions",
            cell: ({ row }) => (
                <ActionCell>
                    <TableBtn onClick={() => navigate(`/prescriptions/${type}/${row.original._id}`)}>View / print</TableBtn>
                </ActionCell>
            ),
        },
    ], [type, navigate]);

    return (
        <Card
            label="Prescriptions"
            actions={
                <>
                    <ToggleButtonGroup size="small" exclusive value={type} onChange={(_, v) => v && setType(v)}
                        sx={{ "& .MuiToggleButton-root": { textTransform: "none", fontSize: 12, py: 0.5, px: 1.5 }, "& .Mui-selected": { color: `${colors.brandDark} !important` } }}>
                        <ToggleButton value="medicine">Medicines</ToggleButton>
                        <ToggleButton value="test">Tests</ToggleButton>
                    </ToggleButtonGroup>
                    {can("prescriptions.write") && <Button small onClick={() => setWriting(true)}>+ Prescribe</Button>}
                </>
            }
        >
            <DataTable data={rows} columns={columns} globalSearch={false} emptyMessage={loading ? "Loading…" : "No prescriptions"} />
            {writing && <PrescriptionDialog patient={patient} type={type} onClose={() => setWriting(false)} onSaved={() => { setWriting(false); reload(); }} />}
        </Card>
    );
};

export default PrescriptionsTab;
