import React from "react";
import { useParams } from "react-router-dom";
import PrintDocument, { DocTable, Letterhead } from "../common/PrintDocument";
import { InfoGrid, Loader, colors } from "../common/ui";
import useAsync from "../../hooks/useAsync";
import { assessments } from "../../api/homecare";
import { fmtDate, fmtMoney, today } from "../../utilities/format";
import { invoiceLines, invoiceTotals } from "../../utilities/invoice";

/** Quotation invoice for an assessed patient's care plan. */
const AssessmentInvoice = () => {
    const { id } = useParams();
    const { data: a } = useAsync(() => assessments.show(id), [id]);

    if (!a) return <Loader />;

    const lines = invoiceLines(a);
    const t = invoiceTotals(a);

    return (
        <PrintDocument>
            <Letterhead title="Invoice" meta={[["Date", fmtDate(today())], ["Customer ID", a.identity_number], ["Reference", a.assessment_id && `Lead #${a.assessment_id}`]]} />

            <InfoGrid columns={2} items={[
                { label: "Bill to", value: a.patient_name },
                { label: "Mobile", value: a.mobile },
                { label: "Address", value: a.address },
                { label: "Service", value: "Home care" },
            ]} />

            <DocTable
                head={["#", "Care plan", "Quotation", "Duration", "Amount"]}
                rows={lines.length ? lines.map((l, i) => [i + 1, l.label, l.quotation || "—", [l.duration, l.sessions && `${l.sessions} sessions`].filter(Boolean).join(" · ") || "—", fmtMoney(l.fees)]) : [["", "No services in the care plan", "", "", ""]]}
                foot={[
                    ["Subtotal", fmtMoney(t.subtotal)],
                    t.discountPercent ? [`Discount (${t.discountPercent}%)`, `− ${fmtMoney(t.discount)}`] : null,
                    t.vatPercent ? [`VAT (${t.vatPercent}%)`, fmtMoney(t.vat)] : null,
                    t.otherFees ? ["Other fees", fmtMoney(t.otherFees)] : null,
                    ["Total", fmtMoney(t.total), true],
                ].filter(Boolean)}
            />

            {a.digitalsignature && (
                <div style={{ marginTop: 24 }}>
                    <img src={a.digitalsignature} alt="Patient signature" style={{ maxHeight: 70 }} />
                    <div style={{ fontSize: 11, color: colors.muted }}>Patient signature</div>
                </div>
            )}
        </PrintDocument>
    );
};

export default AssessmentInvoice;
