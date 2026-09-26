import React, { useRef, useState } from "react";
import { TextField } from "@mui/material";
import { toast } from "react-toastify";
import { Button, Card, ConfirmDialog, EmptyState, Modal, colors } from "../../common/ui";
import useAsync from "../../../hooks/useAsync";
import usePermission from "../../../hooks/usePermission";
import { patients as patientsApi } from "../../../api/homecare";
import { errorMessage } from "../../../api/client";

const SECTIONS = [
    { field: "images", label: "Documents", hint: "Reports, discharge letters, insurance papers." },
    { field: "emirates_id", label: "Patient ID", hint: "Emirates ID, passport or other identity documents." },
    { field: "specimen_images", label: "Specimen images", hint: "Photos of specimens taken during visits." },
];

const isImage = (url) => /\.(png|jpe?g|gif|webp|bmp|heic)(\?|$)/i.test(url);
const fileName = (url) => decodeURIComponent(String(url).split("/").pop() ?? "file");

/** Uploaded files of one kind, with upload and remove. */
const DocumentSection = ({ patient, section, items, onChanged }) => {
    const { can } = usePermission();
    const input = useRef(null);
    const [uploading, setUploading] = useState(false);
    const [toRemove, setToRemove] = useState(null);
    const [removing, setRemoving] = useState(false);

    const upload = async (files) => {
        if (!files.length) return;
        setUploading(true);
        try {
            await patientsApi.upload(patient.username, section.field, files);
            toast.success(`${files.length} file(s) uploaded.`);
            onChanged();
        } catch (e) {
            toast.error(errorMessage(e));
        } finally {
            setUploading(false);
            if (input.current) input.current.value = "";
        }
    };

    const remove = async () => {
        setRemoving(true);
        try {
            await patientsApi.removeDocument(patient.username, toRemove, section.field);
            toast.success("File removed.");
            setToRemove(null);
            onChanged();
        } catch (e) {
            toast.error(errorMessage(e));
        } finally {
            setRemoving(false);
        }
    };

    return (
        <Card
            label={section.label}
            style={{ marginBottom: 16 }}
            actions={can("documents.upload") && (
                <>
                    <input ref={input} type="file" multiple hidden onChange={(e) => upload([...e.target.files])} />
                    <Button small loading={uploading} onClick={() => input.current?.click()}>Upload</Button>
                </>
            )}
        >
            {items.length ? (
                <div style={{ display: "grid", gridTemplateColumns: "repeat(auto-fill, minmax(170px, 1fr))", gap: 14 }}>
                    {items.map((item, i) => {
                        const url = typeof item === "string" ? item : item?.image_path;
                        if (!url) return null;
                        return (
                            <div key={i} style={{ border: `1px solid ${colors.line}`, borderRadius: 10, overflow: "hidden", background: "#fafcfd" }}>
                                <a href={url} target="_blank" rel="noreferrer" style={{ display: "block", height: 120, background: "#eef3f6" }}>
                                    {isImage(url)
                                        ? <img src={url} alt={fileName(url)} style={{ width: "100%", height: "100%", objectFit: "cover" }} />
                                        : <div style={{ height: "100%", display: "flex", alignItems: "center", justifyContent: "center", fontSize: 28, color: colors.muted }}>📄</div>}
                                </a>
                                <div style={{ padding: "8px 10px", fontSize: 12 }}>
                                    <div style={{ overflow: "hidden", textOverflow: "ellipsis", whiteSpace: "nowrap", color: colors.ink }} title={fileName(url)}>{fileName(url)}</div>
                                    {item?.lab_details && <div style={{ color: colors.muted }}>{item.lab_details}</div>}
                                    {can("documents.delete") && typeof item === "string" && (
                                        <button type="button" onClick={() => setToRemove(url)} style={{ border: "none", background: "none", color: colors.danger, padding: 0, marginTop: 4, cursor: "pointer", fontSize: 12 }}>Remove</button>
                                    )}
                                </div>
                            </div>
                        );
                    })}
                </div>
            ) : <EmptyState title={`No ${section.label.toLowerCase()}`}>{section.hint}</EmptyState>}

            <ConfirmDialog open={!!toRemove} title="Remove file" message="Remove this file from the patient's record?" confirmLabel="Remove" danger loading={removing} onConfirm={remove} onClose={() => setToRemove(null)} />
        </Card>
    );
};

const MissingDocumentDialog = ({ patient, onClose }) => {
    const [message, setMessage] = useState("");
    const [sending, setSending] = useState(false);
    const send = async () => {
        setSending(true);
        try {
            await patientsApi.requestMissing(patient.username, message);
            toast.success("The nurses have been asked by email.");
            onClose();
        } catch (e) {
            toast.error(errorMessage(e));
        } finally {
            setSending(false);
        }
    };
    return (
        <Modal open title="Ask nurses for a missing document" onClose={onClose}
            actions={<><Button variant="secondary" small onClick={onClose}>Cancel</Button><Button small loading={sending} disabled={!message.trim()} onClick={send}>Send</Button></>}>
            <TextField fullWidth multiline minRows={3} label="What is missing?" value={message} onChange={(e) => setMessage(e.target.value)} />
        </Modal>
    );
};

const DocumentsTab = ({ patient }) => {
    const { can } = usePermission();
    const [asking, setAsking] = useState(false);
    const { data, reload } = useAsync(() => patientsApi.documents(patient.username), [patient.username], {});

    return (
        <>
            <div style={{ display: "flex", justifyContent: "flex-end", marginBottom: 12 }}>
                {can("documents.view") && <Button variant="secondary" small onClick={() => setAsking(true)}>Request missing document</Button>}
            </div>
            {SECTIONS.map((s) => (
                <DocumentSection key={s.field} patient={patient} section={s} items={Array.isArray(data?.[s.field]) ? data[s.field] : []} onChanged={reload} />
            ))}
            {asking && <MissingDocumentDialog patient={patient} onClose={() => setAsking(false)} />}
        </>
    );
};

export default DocumentsTab;
