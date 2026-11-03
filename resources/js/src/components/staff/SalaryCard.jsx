import React, { useMemo, useState } from "react";
import { MenuItem, TextField } from "@mui/material";
import DataTable from "../table/DataTable";
import { Card, StatStrip, colors } from "../common/ui";
import useAsync from "../../hooks/useAsync";
import { salary } from "../../api/homecare";
import { fmtDate, fmtMoney, fmtTime } from "../../utilities/format";

const pad = (n) => String(n).padStart(2, "0");
const iso = (d) => `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`;
const addDays = (d, n) => new Date(d.getFullYear(), d.getMonth(), d.getDate() + n);
const monday = (d) => addDays(d, -((d.getDay() + 6) % 7));

/** Weeks run Monday to Sunday. */
const PERIODS = {
    this_week: { label: "This week", range: (now) => [monday(now), addDays(monday(now), 6)] },
    last_week: { label: "Last week", range: (now) => [addDays(monday(now), -7), addDays(monday(now), -1)] },
    this_month: { label: "This month", range: (now) => [new Date(now.getFullYear(), now.getMonth(), 1), new Date(now.getFullYear(), now.getMonth() + 1, 0)] },
    last_month: { label: "Last month", range: (now) => [new Date(now.getFullYear(), now.getMonth() - 1, 1), new Date(now.getFullYear(), now.getMonth(), 0)] },
    month: { label: "Choose month" },
    custom: { label: "Custom dates" },
};

/**
 * Days worked and salary for a period: a day counts once the staff member
 * signed in and out of a duty that day. `staffId` (the user's key) shows
 * that staff member's salary (admins); without it, the signed-in user's.
 */
const SalaryCard = ({ staffId, title = "Salary" }) => {
    const now = new Date();
    const [period, setPeriod] = useState("this_month");
    const [month, setMonth] = useState(`${now.getFullYear()}-${pad(now.getMonth() + 1)}`);
    const [custom, setCustom] = useState({ from: iso(addDays(now, -6)), to: iso(now) });

    const [from, to] = useMemo(() => {
        if (period === "custom") return [custom.from, custom.to];
        if (period === "month") {
            const [y, m] = month.split("-").map(Number);
            return [iso(new Date(y, m - 1, 1)), iso(new Date(y, m, 0))];
        }
        return PERIODS[period].range(new Date()).map(iso);
    }, [period, month, custom]);

    const valid = Boolean(from && to && from <= to);
    const { data, loading } = useAsync(
        () => (valid ? (staffId ? salary.forStaff(staffId, { from, to }) : salary.mine({ from, to })) : null),
        [staffId, from, to, valid],
    );

    const currency = data?.currency || undefined;
    const rows = data?.days ?? [];

    const columns = useMemo(() => [
        { header: "Date", accessorKey: "date", cell: ({ row }) => fmtDate(row.original.date) },
        {
            header: "Duties",
            id: "duties",
            cell: ({ row }) => row.original.duties.map((d, i) => (
                <div key={i}>
                    {d.patient_name ?? d.patient_id}
                    <span style={{ color: colors.muted }}>
                        {" · "}{fmtTime(d.signin_time)} – {fmtTime(d.signout_time)}{d.live_in ? " · 24-hour" : ""}
                    </span>
                </div>
            )),
        },
        { header: "Day salary", accessorKey: "rate", cell: ({ row }) => fmtMoney(row.original.rate, currency) },
    ], [currency]);

    const filters = (
        <div style={{ display: "flex", gap: 8, flexWrap: "wrap" }}>
            <TextField select size="small" label="Period" value={period} onChange={(e) => setPeriod(e.target.value)} sx={{ minWidth: 150 }}>
                {Object.entries(PERIODS).map(([key, p]) => <MenuItem key={key} value={key}>{p.label}</MenuItem>)}
            </TextField>
            {period === "month" && (
                <TextField size="small" type="month" label="Month" value={month} onChange={(e) => setMonth(e.target.value)} InputLabelProps={{ shrink: true }} />
            )}
            {period === "custom" && (
                <>
                    <TextField size="small" type="date" label="From" value={custom.from} onChange={(e) => setCustom((c) => ({ ...c, from: e.target.value }))} InputLabelProps={{ shrink: true }} />
                    <TextField size="small" type="date" label="To" value={custom.to} onChange={(e) => setCustom((c) => ({ ...c, to: e.target.value }))} InputLabelProps={{ shrink: true }} />
                </>
            )}
        </div>
    );

    return (
        <Card label={title} actions={filters} style={{ marginTop: 16 }}>
            <p style={{ fontSize: 12, color: colors.muted, margin: "0 0 12px" }}>
                {valid ? `${fmtDate(from)} – ${fmtDate(to)}` : "The start date must be on or before the end date."}
                {" · "}A day counts once sign in and sign out are both done, whatever the hours.
            </p>
            <StatStrip items={[
                { label: "Days worked", value: data ? data.days_worked : "—" },
                { label: "Salary per day", value: data?.daily_salary != null ? fmtMoney(data.daily_salary, currency) : "Not set" },
                { label: "Total salary", value: data ? fmtMoney(data.total, currency) : "—" },
            ]} />
            <DataTable data={rows} columns={columns} emptyMessage={loading ? "Loading…" : "No completed days in this period"} />
        </Card>
    );
};

export default SalaryCard;
