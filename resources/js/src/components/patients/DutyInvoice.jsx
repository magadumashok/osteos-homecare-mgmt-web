import React from "react";
import { useParams } from "react-router-dom";
import PrintDocument, { DocTable, Letterhead } from "../common/PrintDocument";
import { InfoGrid, Loader } from "../common/ui";
import useAsync from "../../hooks/useAsync";
import { duties, patients as patientsApi } from "../../api/homecare";
import { fmtDate, fmtMoney, fmtTime, humanize } from "../../utilities/format";

/** Invoice for one visit: the services recorded on the duty (or the patient's package). */
const DutyInvoice = () => {
    const { username, id } = useParams();
    const { data } = useAsync(async () => {
        const [patient, duty] = await Promise.all([patientsApi.show(username), duties.show(id)]);
        return { patient, duty };
    }, [username, id]);

    if (!data) return <Loader />;
    const { patient: p, duty: d } = data;

    const services = Array.isArray(d.services) && d.services.length
        ? d.services
        : d.service_package ? [{ service_package: d.service_package, fees: d.fees }] : [];
    const total = services.reduce((sum, s) => sum + (Number(s.fees) || 0), 0);

    return (
        <PrintDocument>
            <Letterhead title="Invoice" meta={[["Date", fmtDate(d.date)], ["Patient ID", p.username], ["Visit", d._id?.slice(-6).toUpperCase()]]} />
            <InfoGrid columns={2} items={[
                { label: "Bill to", value: p.patient_name },
                { label: "Mobile", value: p.mobile },
                { label: "Address", value: p.address },
                { label: "Visit", value: `${fmtDate(d.date)}, ${fmtTime(d.start_time)} – ${fmtTime(d.end_time)} · ${humanize(d.role)}` },
            ]} />
            <DocTable
                head={["#", "Service", "Amount"]}
                rows={services.length ? services.map((s, i) => [i + 1, s.service_package ?? s.name ?? "Service", fmtMoney(s.fees)]) : [["", "No services recorded for this visit", ""]]}
                foot={[["Total", fmtMoney(total), true]]}
            />
        </PrintDocument>
    );
};

export default DutyInvoice;
