import React, { useMemo, useState } from "react";
import { Checkbox, FormControlLabel, Grid, TextField } from "@mui/material";
import { toast } from "react-toastify";
import DataTable, { ActionCell, TableBtn } from "../table/DataTable";
import { Badge, Button, Card, ConfirmDialog, Modal, PageHeader, colors } from "../common/ui";
import useAsync from "../../hooks/useAsync";
import { roles as rolesApi } from "../../api/homecare";
import { errorMessage } from "../../api/client";
import { humanize } from "../../utilities/format";

// Group the catalogue by area ("patients.view" → "patients").
const groupPermissions = (catalogue) =>
    catalogue.reduce((groups, p) => {
        const area = p.name.split(".")[0];
        (groups[area] ??= []).push(p);
        return groups;
    }, {});

/** Roles and the permissions each holds. Permissions themselves are fixed in code. */
const RolesPage = () => {
    const { data: roleList, loading, reload } = useAsync(() => rolesApi.list(), [], []);
    const { data: catalogue } = useAsync(() => rolesApi.permissions(), [], []);
    const groups = useMemo(() => groupPermissions(catalogue), [catalogue]);

    const [editing, setEditing] = useState(null); // { _id?, name, description, permissions: Set }
    const [toDelete, setToDelete] = useState(null);
    const [saving, setSaving] = useState(false);

    const open = (role) => setEditing({
        _id: role?._id,
        name: role?.name ?? "",
        description: role?.description ?? "",
        system: role?.system,
        permissions: new Set(role?.permissions?.includes("*") ? catalogue.map((p) => p.name) : role?.permissions ?? []),
    });

    const toggle = (names, on) => setEditing((e) => {
        const next = new Set(e.permissions);
        names.forEach((n) => (on ? next.add(n) : next.delete(n)));
        return { ...e, permissions: next };
    });

    const isAdmin = editing?.name === "admin";

    const save = async () => {
        setSaving(true);
        try {
            const permissions = [...editing.permissions];
            if (editing._id) {
                await rolesApi.update(editing._id, { name: editing.name, description: editing.description });
                if (!isAdmin) await rolesApi.setPermissions(editing._id, permissions);
            } else {
                await rolesApi.create({ name: editing.name.trim().toLowerCase(), description: editing.description, permissions });
            }
            toast.success("Role saved.");
            setEditing(null);
            reload();
        } catch (e) {
            toast.error(errorMessage(e));
        } finally {
            setSaving(false);
        }
    };

    const remove = async () => {
        setSaving(true);
        try {
            await rolesApi.remove(toDelete._id);
            toast.success("Role deleted.");
            setToDelete(null);
            reload();
        } catch (e) {
            toast.error(errorMessage(e));
        } finally {
            setSaving(false);
        }
    };

    const columns = useMemo(() => [
        {
            header: "Role",
            accessorKey: "name",
            cell: ({ row }) => (
                <div>
                    <div style={{ fontWeight: 600, color: colors.ink }}>{humanize(row.original.name)} {row.original.system && <Badge tone="muted">built-in</Badge>}</div>
                    <div style={{ fontSize: 12, color: colors.muted }}>{row.original.description}</div>
                </div>
            ),
        },
        {
            header: "Permissions",
            id: "permissions",
            cell: ({ row }) => {
                const list = row.original.permissions ?? [];
                return list.includes("*") || row.original.name === "admin"
                    ? <Badge tone="success">Full access</Badge>
                    : <span style={{ fontSize: 12, color: colors.body }}>{list.length} of {catalogue.length}</span>;
            },
        },
        {
            header: "Actions",
            id: "actions",
            cell: ({ row }) => (
                <ActionCell>
                    <TableBtn onClick={() => open(row.original)}>Edit</TableBtn>
                    {!row.original.system && <TableBtn variant="danger" onClick={() => setToDelete(row.original)}>Delete</TableBtn>}
                </ActionCell>
            ),
        },
    // eslint-disable-next-line react-hooks/exhaustive-deps
    ], [catalogue]);

    return (
        <>
            <PageHeader
                eyebrow="Administration"
                title="Roles & Permissions"
                subtitle="A role is a set of permissions. Staff can hold several roles."
                actions={<Button onClick={() => open(null)}>+ New role</Button>}
            />
            <Card>
                <DataTable data={roleList} columns={columns} globalSearch={false} emptyMessage={loading ? "Loading…" : "No roles"} />
            </Card>

            <Modal
                open={!!editing}
                title={editing?._id ? `Edit role: ${humanize(editing.name)}` : "New role"}
                onClose={() => setEditing(null)}
                maxWidth="md"
                actions={
                    <>
                        <Button variant="secondary" small onClick={() => setEditing(null)} disabled={saving}>Cancel</Button>
                        <Button small loading={saving} disabled={!editing?.name?.trim()} onClick={save}>Save role</Button>
                    </>
                }
            >
                {editing && (
                    <>
                        <Grid container columnSpacing={2}>
                            <Grid item xs={12} sm={5}>
                                <TextField fullWidth margin="dense" label="Name" value={editing.name} disabled={editing.system}
                                    helperText="Lowercase, e.g. senior_nurse"
                                    onChange={(e) => setEditing({ ...editing, name: e.target.value })} />
                            </Grid>
                            <Grid item xs={12} sm={7}>
                                <TextField fullWidth margin="dense" label="Description" value={editing.description}
                                    onChange={(e) => setEditing({ ...editing, description: e.target.value })} />
                            </Grid>
                        </Grid>

                        {isAdmin ? (
                            <p style={{ fontSize: 13, color: colors.body, marginTop: 16 }}>The admin role always has every permission.</p>
                        ) : (
                            <div style={{ display: "grid", gridTemplateColumns: "repeat(auto-fill, minmax(260px, 1fr))", gap: 12, marginTop: 16 }}>
                                {Object.entries(groups).map(([area, perms]) => {
                                    const names = perms.map((p) => p.name);
                                    const all = names.every((n) => editing.permissions.has(n));
                                    const some = names.some((n) => editing.permissions.has(n));
                                    return (
                                        <div key={area} style={{ border: `1px solid ${colors.line}`, borderRadius: 10, padding: "8px 12px" }}>
                                            <FormControlLabel
                                                control={<Checkbox size="small" checked={all} indeterminate={some && !all} onChange={(e) => toggle(names, e.target.checked)} />}
                                                label={<strong style={{ fontSize: 13 }}>{humanize(area)}</strong>}
                                            />
                                            {perms.map((p) => (
                                                <FormControlLabel
                                                    key={p.name}
                                                    sx={{ display: "flex", ml: 2 }}
                                                    control={<Checkbox size="small" checked={editing.permissions.has(p.name)} onChange={(e) => toggle([p.name], e.target.checked)} />}
                                                    label={<span style={{ fontSize: 12, color: colors.body }}>{p.description}</span>}
                                                />
                                            ))}
                                        </div>
                                    );
                                })}
                            </div>
                        )}
                    </>
                )}
            </Modal>

            <ConfirmDialog
                open={!!toDelete}
                title="Delete role"
                message={toDelete && `Delete the ${humanize(toDelete.name)} role?`}
                confirmLabel="Delete"
                danger
                loading={saving}
                onConfirm={remove}
                onClose={() => setToDelete(null)}
            />
        </>
    );
};

export default RolesPage;
