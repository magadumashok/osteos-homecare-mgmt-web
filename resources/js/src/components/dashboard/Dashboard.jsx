import React, { useEffect, useState } from "react";
import { useNavigate } from "react-router-dom";
import { useSelector } from "react-redux";
import { Grid } from "@mui/material";
import { Badge, Button, Card, EmptyState, PageHeader, cardStyle, colors } from "../common/ui";
import { dutyStatus, dutyTime } from "../patients/tabs/SchedulesTab";
import usePermission from "../../hooks/usePermission";
import { assessments, duties, leads, patients } from "../../api/homecare";
import { humanize, today } from "../../utilities/format";

const Stat = ({ label, value, to }) => {
    const navigate = useNavigate();
    return (
        <button type="button" onClick={() => navigate(to)} style={{ ...cardStyle, width: "100%", textAlign: "left", cursor: "pointer" }}>
            <div style={{ fontFamily: "'Playfair Display', serif", fontSize: 30, fontWeight: 700, color: colors.ink }}>{value ?? "—"}</div>
            <div style={{ fontSize: 11, color: colors.muted, textTransform: "uppercase", letterSpacing: ".6px" }}>{label}</div>
        </button>
    );
};

const Dashboard = () => {
    const navigate = useNavigate();
    const { can } = usePermission();
    const user = useSelector((s) => s.auth.user);
    const homecare = useSelector((s) => s.auth.homecare);
    const [counts, setCounts] = useState({});
    const [todays, setTodays] = useState(null);

    const name = user?.user_type === "homecare" ? user?.owner || user?.name : user?.name ?? user?.username;

    useEffect(() => {
        const safe = (p) => p.catch(() => null);
        if (can("patients.view")) safe(patients.list()).then((l) => l && setCounts((c) => ({ ...c, patients: l.length })));
        if (can("leads.view")) safe(leads.list()).then((l) => l && setCounts((c) => ({ ...c, leads: l.length })));
        if (can("assessments.view")) safe(assessments.list()).then((l) => l && setCounts((c) => ({ ...c, assessments: l.length })));
        if (can("schedules.view")) safe(duties.list({ date: today() })).then((l) => setTodays(l ?? []));
        else if (can("attendance.record")) safe(duties.mine(today())).then((l) => setTodays(l ?? []));
    }, [can]);

    return (
        <>
            <PageHeader
                eyebrow={homecare?.name ?? "Homecare"}
                title={`Welcome, ${name ?? ""}`}
                subtitle={(user?.roles ?? []).map(humanize).join(", ")}
                actions={
                    <>
                        {can("leads.manage") && <Button variant="secondary" onClick={() => navigate("/leads")}>+ New lead</Button>}
                        {can("schedules.manage") && <Button onClick={() => navigate("/schedules/new")}>+ Schedule duty</Button>}
                    </>
                }
            />

            <Grid container spacing={2} sx={{ mb: 2 }}>
                {can("patients.view") && <Grid item xs={6} md={3}><Stat label="Admitted patients" value={counts.patients} to="/patients" /></Grid>}
                {can("leads.view") && <Grid item xs={6} md={3}><Stat label="Open leads" value={counts.leads} to="/leads" /></Grid>}
                {can("assessments.view") && <Grid item xs={6} md={3}><Stat label="Awaiting admission" value={counts.assessments} to="/assessments" /></Grid>}
                {todays && <Grid item xs={6} md={3}><Stat label="Duties today" value={todays.length} to="/schedules" /></Grid>}
            </Grid>

            {todays && (
                <Card label={can("schedules.view") ? "Today's duties" : "My duties today"} actions={can("schedules.view") && <Button variant="secondary" small onClick={() => navigate("/schedules")}>Open schedule</Button>}>
                    {todays.length ? todays.slice(0, 12).map((d) => (
                        <div key={d._id} style={{ display: "flex", alignItems: "center", gap: 14, padding: "10px 0", borderBottom: `1px solid ${colors.line}`, flexWrap: "wrap" }}>
                            <div style={{ width: 120, fontSize: 13, fontWeight: 600, color: colors.ink }}>{dutyTime(d)}</div>
                            <div style={{ flex: 1, minWidth: 180, fontSize: 13 }}>
                                <strong>{d.patient_name ?? d.patient_id}</strong>
                                <span style={{ color: colors.muted }}> · {d.staff_name ?? d.name ?? d.staff_id} ({humanize(d.role)})</span>
                            </div>
                            {dutyStatus(d)}
                            {d.driver && <Badge tone="muted">Driver: {d.driver}</Badge>}
                        </div>
                    )) : <EmptyState title="No duties today" />}
                </Card>
            )}
        </>
    );
};

export default Dashboard;
