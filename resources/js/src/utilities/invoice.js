// Assessment invoice maths, as the legacy invoice did it: each care-plan
// service has a fee; discount and VAT are percentages; other fees are added last.

export const SERVICES = [
    { key: "nursing", label: "Nursing" },
    { key: "physiotherapy", label: "Physiotherapy" },
    { key: "doctor", label: "Doctor" },
];

const num = (v) => {
    const n = Number(v);
    return Number.isFinite(n) ? n : 0;
};

const round = (n) => Math.round(n * 100) / 100;

/** Services that are part of the care plan and so appear on the invoice. */
export const invoiceLines = (assessment) =>
    SERVICES.filter(({ key }) => assessment?.care_plan?.[key] || assessment?.[key]?.fees).map(({ key, label }) => ({
        key,
        label,
        quotation: assessment?.[key]?.quotation ?? "",
        duration: assessment?.[key]?.duration ?? "",
        sessions: assessment?.[key]?.sessions ?? "",
        fees: num(assessment?.[key]?.fees),
    }));

export const invoiceTotals = (assessment) => {
    const subtotal = invoiceLines(assessment).reduce((sum, line) => sum + line.fees, 0);
    const details = assessment?.invoice_details ?? {};
    const discount = round((subtotal * num(details.discount)) / 100);
    const vat = round(((subtotal - discount) * num(details.vat)) / 100);
    const otherFees = num(details.other_fees);

    return {
        subtotal: round(subtotal),
        discountPercent: num(details.discount),
        discount,
        vatPercent: num(details.vat),
        vat,
        otherFees,
        total: round(subtotal - discount + vat + otherFees),
    };
};
