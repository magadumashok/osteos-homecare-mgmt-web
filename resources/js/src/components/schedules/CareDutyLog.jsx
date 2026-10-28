import React, { useMemo, useState } from "react";
import { useNavigate, useParams } from "react-router-dom";
import { Button, Card, EmptyState, InfoGrid, Loader, PageHeader, colors } from "../common/ui";
import { PresencePhotosDialog, dutyStatus } from "../patients/tabs/SchedulesTab";
import { CareLogDetail, CoverDialog, careDayStatus } from "./CareUpdates";
import useAsync from "../../hooks/useAsync";
import usePermission from "../../hooks/usePermission";
import { careLogs } from "../../api/homecare";
import { fmtDate, today } from "../../utilities/format";

// Local-date arithmetic ("YYYY-MM-DD"), as in DutyForm.
const addDays = (date, n) => {
    const [y, m, d] = date.split("-").map(Number);
    const next = new Date(y, m - 1, d + n);
    return `${next.getFullYear()}-${String(next.getMonth() + 1).padStart(2, "0")}-${String(next.getDate()).padStart(2, "0")}`;
};

/** One caretaker duty, day by day: updates, skipped days and covers (/schedules/:id/care). */
const CareDutyLog = () => {
    const { id } = useParams();
    const navigate = useNavigate();
    const { can } = usePermission();
    const [covering, setCovering] = useState(null);
    const [showPhotos, setShowPhotos] = useState(false);
    const { data, loading, reload } = useAsync(() => careLogs.forDuty(id), [id], null);

    const duty = data?.duty;
    // Newest day first, from today (or the end date) back to the start.
    const days = useMemo(() => {
        if (!duty) return [];
        const logs = Object.fromEntries((data.logs ?? []).map((l) => [l.date, l]));
        const last = duty.end_date < today() ? duty.end_date : today();
        const list = [];
        for (let d = last; d >= duty.date && list.length < 400; d = addDays(d, -1)) list.push({ date: d, log: logs[d] });
        // Days skipped ahead of time are after "today" but still worth showing.
        Object.values(logs).filter((l) => l.date > last).forEach((l) => list.unshift({ date: l.date, log: l }));
        return list;
    }, [data, duty]);

    if (loading) return <Loader />;
    if (!duty) return <EmptyState title="Duty not found" />;

    const tasks = Array.isArray(duty.care_tasks) ? duty.care_tasks : [];

    return (
        <div style={{ maxWidth: 980 }}>
            <PageHeader
                eyebrow="Caretaker care log"
                title={duty.patient_name ?? duty.patient_id}
                subtitle={`${duty.staff_name ?? duty.staff_id} · 24-hour care ${fmtDate(duty.date)} → ${fmtDate(duty.end_date)}`}
                actions={
                    <>
                        <Button variant="secondary" onClick={() => navigate(`/patients/${duty.patient_id}?tab=schedules`)}>Patient schedules</Button>
                        <Button variant="secondary" onClick={() => setShowPhotos(true)}>Photos ({(duty.presence_photos ?? []).length})</Button>
                        {can("schedules.manage") && <Button onClick={() => navigate(`/schedules/${duty._id}/edit`)}>Edit duty</Button>}
                    </>
                }
            />
            <Card label="Duty">
                <InfoGrid items={[
                    { label: "Status", value: dutyStatus(duty) },
                    { label: "Caretaker", value: duty.staff_name ?? duty.staff_id },
                    { label: "Days logged", value: (data.logs ?? []).filter((l) => l.status === "logged").length },
                    { label: "Days skipped", value: (data.logs ?? []).filter((l) => l.status === "skipped").length },
                    { label: "Care tasks", value: tasks.join(", ") || "—" },
                ]} />
            </Card>
            <Card label="Day by day" style={{ marginTop: 16 }}>
                {days.length === 0 ? <EmptyState title="The duty has not started yet" /> : days.map(({ date, log }) => (
                    <div key={date} style={{ padding: "12px 0", borderBottom: `1px solid ${colors.line}`, display: "grid", gap: 6 }}>
                        <div style={{ display: "flex", alignItems: "center", gap: 12, flexWrap: "wrap" }}>
                            <strong style={{ flex: 1, minWidth: 160, color: colors.ink, fontSize: 13 }}>{fmtDate(date)}</strong>
                            {careDayStatus(log, date)}
                            {can("schedules.manage") && log?.status === "skipped" && !log.cover_duty_id && (
                                <Button small onClick={() => setCovering(date)}>Assign cover</Button>
                            )}
                        </div>
                        <CareLogDetail log={log} />
                    </div>
                ))}
            </Card>
            {showPhotos && <PresencePhotosDialog duty={duty} onClose={() => setShowPhotos(false)} />}
            {covering && <CoverDialog duty={duty} date={covering} onClose={() => setCovering(null)} onSaved={() => { setCovering(null); reload(); }} />}
        </div>
    );
};

export default CareDutyLog;
