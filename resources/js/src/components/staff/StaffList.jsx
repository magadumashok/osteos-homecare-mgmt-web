import React, { useMemo, useState } from "react";
import { useNavigate } from "react-router-dom";
import { useSelector } from "react-redux";
import { toast } from "react-toastify";
import DataTable, { ActionCell, TableBtn } from "../table/DataTable";
import {
    Badge,
    Button,
    Card,
    ConfirmDialog,
    PageHeader,
    PersonCell,
    StatStrip,
} from "../common/ui";
import useAsync from "../../hooks/useAsync";
import usePermission from "../../hooks/usePermission";
import { account, staff } from "../../api/homecare";
import { errorMessage } from "../../api/client";
import { humanize } from "../../utilities/format";

// Roles with duty or trip history worth monitoring (as in the legacy app).
const HAS_ATTENDANCE = ["nurse", "physiotherapist", "doctor", "caretaker","transport"];
// Roles that use the mobile app and can take a video call.
const CAN_CALL = ["nurse", "physiotherapist", "doctor", "caretaker","transport"];

const StaffList = () => {
    const navigate = useNavigate();
    const { can } = usePermission();
    const me = useSelector((s) => s.auth.user);
    const homecare = useSelector((s) => s.auth.homecare);
    const {
        data: members,
        loading,
        reload,
    } = useAsync(() => staff.list(), [], []);

    const [roleFilter, setRoleFilter] = useState("all");
    const [toDelete, setToDelete] = useState(null);
    const [toCall, setToCall] = useState(null);
    const [busy, setBusy] = useState(false);

    const counts = useMemo(() => {
        const c = {};
        members.forEach((m) =>
            (m.roles ?? []).forEach((r) => (c[r] = (c[r] ?? 0) + 1)),
        );
        return c;
    }, [members]);

    const rows = useMemo(
        () =>
            roleFilter === "all"
                ? members
                : members.filter((m) => (m.roles ?? []).includes(roleFilter)),
        [members, roleFilter],
    );

    const remove = async () => {
        setBusy(true);
        try {
            await staff.remove(toDelete._id);
            toast.success(`${toDelete.name} was removed.`);
            setToDelete(null);
            reload();
        } catch (e) {
            toast.error(errorMessage(e));
        } finally {
            setBusy(false);
        }
    };

    const call = async () => {
        const room = `https://meet.jit.si/${homecare?.homecare_id ?? "homecare"}-${toCall.username}-${Date.now().toString(36)}`;
        setBusy(true);
        try {
            await account.videoCall(toCall.username, room);
            window.open(room, "_blank", "noopener");
            setToCall(null);
        } catch (e) {
            toast.error(errorMessage(e));
        } finally {
            setBusy(false);
        }
    };

    const columns = useMemo(
        () => [
            {
                header: "Staff member",
                accessorKey: "name",
                // The owner account's `name` is the center; the person is in `owner`.
                cell: ({ row }) => {
                    const u = row.original;
                    return (
                        <PersonCell
                            name={
                                u.user_type === "homecare"
                                    ? u.owner || u.name
                                    : u.name
                            }
                            sub={u.username}
                        />
                    );
                },
            },
            {
                header: "Mobile",
                accessorKey: "mobile",
                cell: ({ row }) => row.original.mobile ?? "—",
            },
            {
                header: "Email",
                accessorKey: "email",
                cell: ({ row }) => row.original.email || "—",
            },
            {
                header: "Roles",
                accessorFn: (r) => (r.roles ?? []).join(" "),
                cell: ({ row }) => (
                    <div style={{ display: "flex", gap: 5, flexWrap: "wrap" }}>
                        {(row.original.roles ?? []).map((r) => (
                            <Badge key={r}>{r}</Badge>
                        ))}
                        {row.original.user_type === "homecare" && (
                            <Badge tone="warning">owner</Badge>
                        )}
                    </div>
                ),
            },
            {
                header: "Actions",
                id: "actions",
                cell: ({ row }) => {
                    const u = row.original;
                    const role = u.roles?.[0];
                    const isOwner = u.user_type === "homecare";
                    return (
                        <ActionCell>
                            {can("staff.manage") && (
                                <TableBtn
                                    onClick={() =>
                                        navigate(`/staff/${u._id}/edit`)
                                    }
                                >
                                    Edit
                                </TableBtn>
                            )}
                            {can("attendance.view") &&
                                HAS_ATTENDANCE.includes(role) && (
                                    <TableBtn
                                        variant="secondary"
                                        onClick={() =>
                                            navigate(
                                                `/staff/${u._id}/attendance`,
                                            )
                                        }
                                    >
                                        Attendance
                                    </TableBtn>
                                )}
                            {can("video.call") && CAN_CALL.includes(role) && (
                                <TableBtn
                                    variant="secondary"
                                    disabled={!u.firebase_token}
                                    onClick={() => setToCall(u)}
                                >
                                    Video call
                                </TableBtn>
                            )}
                            {can("staff.manage") &&
                                !isOwner &&
                                u.username !== me?.username && (
                                    <TableBtn
                                        variant="danger"
                                        onClick={() => setToDelete(u)}
                                    >
                                        Delete
                                    </TableBtn>
                                )}
                        </ActionCell>
                    );
                },
            },
        ],
        [can, navigate, me],
    );

    return (
        <>
            <PageHeader
                eyebrow="Administration"
                title="Staff Members"
                actions={
                    can("staff.manage") && (
                        <Button onClick={() => navigate("/staff/new")}>
                            + Add staff
                        </Button>
                    )
                }
            />

            <StatStrip
                items={[
                    { label: "Total staff", value: members.length },
                    { label: "Nurses", value: counts.nurse ?? 0 },
                    {
                        label: "Physiotherapists",
                        value: counts.physiotherapist ?? 0,
                    },
                    { label: "Doctors", value: counts.doctor ?? 0 },
                    { label: "Drivers", value: counts.transport ?? 0 },
                ]}
            />

            <Card
                label="Team"
                actions={["all", ...Object.keys(counts).sort()].map((r) => (
                    <button
                        key={r}
                        type="button"
                        onClick={() => setRoleFilter(r)}
                        style={{
                            border: "none",
                            background: "none",
                            cursor: "pointer",
                            padding: 0,
                        }}
                    >
                        <Badge tone={roleFilter === r ? "neutral" : "muted"}>
                            {r === "all" ? "All" : humanize(r)}
                        </Badge>
                    </button>
                ))}
            >
                <DataTable
                    data={rows}
                    columns={columns}
                    searchPlaceholder="Search by name, mobile or role…"
                    emptyMessage={loading ? "Loading…" : "No staff members yet"}
                />
            </Card>

            <ConfirmDialog
                open={!!toDelete}
                title="Remove staff member"
                message={
                    toDelete &&
                    `Remove ${toDelete.name} (${toDelete.username})? They will no longer be able to sign in.`
                }
                confirmLabel="Remove"
                danger
                loading={busy}
                onConfirm={remove}
                onClose={() => setToDelete(null)}
            />

            <ConfirmDialog
                open={!!toCall}
                title="Start video call"
                message={
                    toCall &&
                    `Ring ${toCall.name} on their phone and open the call room here?`
                }
                confirmLabel="Call"
                loading={busy}
                onConfirm={call}
                onClose={() => setToCall(null)}
            />
        </>
    );
};

export default StaffList;
