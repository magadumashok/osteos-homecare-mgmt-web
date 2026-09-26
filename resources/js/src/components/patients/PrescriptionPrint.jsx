import React from "react";
import { useParams } from "react-router-dom";
import PrintDocument, { DocTable, Letterhead } from "../common/PrintDocument";
import { InfoGrid, Loader, colors } from "../common/ui";
import useAsync from "../../hooks/useAsync";
import { patients as patientsApi } from "../../api/homecare";
import { fmtDate } from "../../utilities/format";
import { MEDICINE_FIELDS, TEST_FIELDS } from "./tabs/PrescriptionsTab";

/** A medicine or test prescription with the doctor's signature. */
const PrescriptionPrint = () => {
    const { type, id } = useParams();
    const { data: rx } = useAsync(() => patientsApi.prescription(type, id), [type, id]);

    if (!rx) return <Loader />;

    const fields = type === "medicine" ? MEDICINE_FIELDS : TEST_FIELDS;
    const items = Array.isArray(rx.prescription_data) ? rx.prescription_data : [];

    return (
        <PrintDocument>
            <Letterhead title={type === "medicine" ? "Prescription" : "Test request"} meta={[["Date", fmtDate(rx.date)], ["Patient ID", rx.patient_id]]} />
            <InfoGrid items={[
                { label: "Patient", value: rx.patient_name },
                { label: "Age / Gender", value: [rx.age, rx.gender].filter(Boolean).join(" · ") },
                { label: "Mobile", value: rx.mobile },
                { label: "ID number", value: rx.id_no },
                { label: "Nationality", value: rx.nationality },
            ]} />
            <DocTable
                head={["#", ...fields.map(([, label]) => label)]}
                rows={items.map((item, i) => [i + 1, ...fields.map(([k]) => item?.[k] || "—")])}
            />
            <div style={{ marginTop: 32, textAlign: "right" }}>
                {rx.doctor_signature && <img src={rx.doctor_signature} alt="Doctor's signature" style={{ maxHeight: 70 }} />}
                <div style={{ fontSize: 13, fontWeight: 600, color: colors.ink }}>{rx.doctor_name ?? rx.doctor_id}</div>
                <div style={{ fontSize: 11, color: colors.muted }}>Doctor</div>
            </div>
        </PrintDocument>
    );
};

export default PrescriptionPrint;
