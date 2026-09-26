import React, { useState } from "react";
import { useParams } from "react-router-dom";
import { Checkbox, FormControlLabel } from "@mui/material";
import { toast } from "react-toastify";
import PrintDocument, { Letterhead } from "../common/PrintDocument";
import { Button, InfoGrid, Loader, YesNo, colors } from "../common/ui";
import useAsync from "../../hooks/useAsync";
import usePermission from "../../hooks/usePermission";
import { patients as patientsApi } from "../../api/homecare";
import { errorMessage } from "../../api/client";
import { fmtDate, isYes, today } from "../../utilities/format";

const TERMS = [
    "I am asking for medical care and treatment from this homecare center and agree to accept services which may treat my condition. I have not been given any guarantees as to the results of the services I will receive.",
    "I understand that my agreement to accept these services will remain in effect unless I say that I no longer want these services or until my treatment is completed.",
    "I understand that my agreement to accept these services is called a general consent and that it includes any routine procedure(s) or treatment(s).",
];
const FINANCIAL = [
    "I agree, whether signing as agent or as the patient, that in consideration of the services rendered I am individually obliged to pay the center's bill, unless eligibility for free services is proved.",
    "I understand that the estimated charges may differ from the final bill depending on the services actually rendered, and that the running bill is to be settled within the specified period.",
    "To the best of my knowledge the above information is complete and correct. I understand it is my responsibility to inform my doctor of any change in health.",
];

/** The general consent the patient signs in the app, for viewing and printing. */
const ConsentForm = () => {
    const { username } = useParams();
    const { can } = usePermission();
    const { data: p, setData } = useAsync(() => patientsApi.show(username), [username]);
    const [saving, setSaving] = useState(false);

    if (!p) return <Loader />;

    const flags = { terms_conditions: isYes(p.terms_conditions), consent_form: isYes(p.consent_form), amount_paid: isYes(p.amount_paid) };

    // Show the change at once; put it back if the server refuses it.
    const toggle = async (key) => {
        const previous = p;
        setData({ ...p, [key]: !flags[key] });
        setSaving(true);
        try {
            await patientsApi.consent(username, { ...flags, [key]: !flags[key] });
        } catch (e) {
            setData(previous);
            toast.error(errorMessage(e));
        } finally {
            setSaving(false);
        }
    };

    return (
        <PrintDocument>
            <Letterhead title="Consent" meta={[["Date", fmtDate(today())], ["Patient ID", p.username]]} />

            <InfoGrid items={[
                { label: "Patient name", value: p.patient_name },
                { label: "Gender", value: p.gender },
                { label: "Mobile", value: p.mobile },
                { label: "Date of birth", value: fmtDate(p.date_of_birth ?? p.birth_date) },
                { label: "Nationality", value: p.nationality },
            ]} />

            <h4 style={{ fontSize: 14, margin: "24px 0 8px", color: colors.brandDark }}>General consent</h4>
            <ol style={{ fontSize: 13, lineHeight: 1.6, paddingLeft: 20, margin: 0 }}>{TERMS.map((t) => <li key={t}>{t}</li>)}</ol>

            <h4 style={{ fontSize: 14, margin: "20px 0 8px", color: colors.brandDark }}>Financial agreement</h4>
            <ol start={TERMS.length + 1} style={{ fontSize: 13, lineHeight: 1.6, paddingLeft: 20, margin: 0 }}>{FINANCIAL.map((t) => <li key={t}>{t}</li>)}</ol>

            <div style={{ display: "flex", gap: 24, marginTop: 24, flexWrap: "wrap", fontSize: 13 }}>
                <span>Terms and conditions: <YesNo value={flags.terms_conditions} yes="Accepted" no="Not accepted" /></span>
                <span>Consent: <YesNo value={flags.consent_form} yes="Accepted" no="Not accepted" /></span>
                <span>Amount paid: <YesNo value={flags.amount_paid} /></span>
            </div>

            <div style={{ marginTop: 32 }}>
                {p.digitalsignature
                    ? <img src={p.digitalsignature} alt="Patient signature" style={{ maxHeight: 80 }} />
                    : <div style={{ height: 60, width: 240, borderBottom: `1px solid ${colors.body}` }} />}
                <div style={{ fontSize: 11, color: colors.muted, marginTop: 4 }}>Patient signature {p.digitalsignature ? "(signed in the app)" : ""}</div>
            </div>

            {can("assessments.manage") && (
                <div className="no-print" style={{ marginTop: 28, paddingTop: 16, borderTop: `1px dashed ${colors.line}` }}>
                    <div style={{ fontSize: 12, color: colors.muted, marginBottom: 6 }}>Record on the patient's behalf (the signature is captured in the mobile app):</div>
                    {[["terms_conditions", "Terms accepted"], ["consent_form", "Consent given"], ["amount_paid", "Amount paid"]].map(([k, l]) => (
                        <FormControlLabel key={k} disabled={saving} control={<Checkbox size="small" checked={flags[k]} onChange={() => toggle(k)} />} label={<span style={{ fontSize: 13 }}>{l}</span>} />
                    ))}
                    {saving && <Button variant="secondary" small loading />}
                </div>
            )}
        </PrintDocument>
    );
};

export default ConsentForm;
