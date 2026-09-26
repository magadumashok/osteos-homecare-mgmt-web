import React, { useState } from "react";
import { TextField, ToggleButton, ToggleButtonGroup } from "@mui/material";
import { toast } from "react-toastify";
import { Button, Card, EmptyState, Loader, Modal, colors } from "../../common/ui";
import useAsync from "../../../hooks/useAsync";
import usePermission from "../../../hooks/usePermission";
import { patients as patientsApi, toFormData } from "../../../api/homecare";
import { errorMessage } from "../../../api/client";
import { fmtDate, fmtTime, nowTime, today } from "../../../utilities/format";

const TYPES = [
    ["text_notes", "Text"],
    ["audio_notes", "Audio"],
    ["image_notes", "Images"],
];

const toggleSx = { "& .MuiToggleButton-root": { textTransform: "none", fontSize: 12, py: 0.5, px: 1.5 }, "& .Mui-selected": { background: "rgba(26,143,168,.12) !important", color: `${colors.brandDark} !important` } };

/** Write a text note, or attach a photo as an image note. */
const NoteDialog = ({ patient, kind, onClose, onSaved }) => {
    const [notes, setNotes] = useState("");
    const [image, setImage] = useState(null);
    const [saving, setSaving] = useState(false);

    const save = async () => {
        setSaving(true);
        try {
            const base = { date: today(), time: nowTime() };
            const body = image ? toFormData({ ...base, image }) : { ...base, notes };
            await patientsApi.addNote(patient.username, kind, body);
            toast.success("Note saved.");
            onSaved();
        } catch (e) {
            toast.error(errorMessage(e));
        } finally {
            setSaving(false);
        }
    };

    return (
        <Modal open title={kind === "nursing" ? "Nursing note" : "Staff note"} onClose={onClose}
            actions={<><Button variant="secondary" small onClick={onClose}>Cancel</Button><Button small loading={saving} disabled={!notes.trim() && !image} onClick={save}>Save note</Button></>}>
            <TextField fullWidth multiline minRows={4} label="Note" value={notes} disabled={!!image} onChange={(e) => setNotes(e.target.value)} />
            <div style={{ marginTop: 14, fontSize: 13, color: colors.body }}>
                Or attach a photo:{" "}
                <input type="file" accept="image/*" onChange={(e) => setImage(e.target.files?.[0] ?? null)} />
            </div>
        </Modal>
    );
};

const NoteItem = ({ note, kind }) => {
    const author = kind === "nursing" ? note.nurse_name : note.nurse_name ?? note.staff_name;
    const role = kind === "staff" ? note.role_name ?? note.staff_role : "Nurse";
    return (
        <div style={{ padding: "12px 0", borderBottom: `1px solid ${colors.line}` }}>
            <div style={{ fontSize: 12, color: colors.muted, marginBottom: 6 }}>
                <strong style={{ color: colors.ink }}>{author ?? "—"}</strong> · {role} · {fmtDate(note.date)} {fmtTime(note.time)}
            </div>
            {note.notes_type === "audio_notes" && note.audio_notes && <audio controls src={note.audio_notes} style={{ width: "100%", maxWidth: 420 }} />}
            {note.notes_type === "image_notes" && note.image_notes && (
                <a href={note.image_notes} target="_blank" rel="noreferrer">
                    <img src={note.image_notes} alt="Note" style={{ maxWidth: 260, maxHeight: 200, borderRadius: 8, border: `1px solid ${colors.line}` }} />
                </a>
            )}
            {note.notes && <div style={{ fontSize: 14, color: colors.ink, whiteSpace: "pre-wrap" }}>{note.notes}</div>}
        </div>
    );
};

/** Nursing notes and staff notes (any role), filtered by type and day. */
const NotesTab = ({ patient }) => {
    const { can } = usePermission();
    const [kind, setKind] = useState("staff");
    const [type, setType] = useState("text_notes");
    const [date, setDate] = useState("");
    const [writing, setWriting] = useState(false);

    const { data: notes, loading, reload } = useAsync(
        () => patientsApi.notes(patient.username, kind, { type, ...(date ? { date } : {}) }),
        [patient.username, kind, type, date],
        []
    );

    return (
        <Card
            label="Notes"
            actions={
                <>
                    <ToggleButtonGroup size="small" exclusive value={kind} onChange={(_, v) => v && setKind(v)} sx={toggleSx}>
                        <ToggleButton value="staff">Staff notes</ToggleButton>
                        <ToggleButton value="nursing">Nursing notes</ToggleButton>
                    </ToggleButtonGroup>
                    <ToggleButtonGroup size="small" exclusive value={type} onChange={(_, v) => v && setType(v)} sx={toggleSx}>
                        {TYPES.map(([v, l]) => <ToggleButton key={v} value={v}>{l}</ToggleButton>)}
                    </ToggleButtonGroup>
                    <TextField size="small" type="date" label="Day" value={date} onChange={(e) => setDate(e.target.value)} InputLabelProps={{ shrink: true }} />
                    {can("notes.write") && <Button small onClick={() => setWriting(true)}>+ Add note</Button>}
                </>
            }
        >
            {loading ? <Loader /> : notes.length ? notes.map((n) => <NoteItem key={n._id} note={n} kind={kind} />) : (
                <EmptyState title="No notes">{date ? `Nothing on ${fmtDate(date)}.` : "Staff add notes from the mobile app during visits."}</EmptyState>
            )}
            {writing && <NoteDialog patient={patient} kind={kind} onClose={() => setWriting(false)} onSaved={() => { setWriting(false); reload(); }} />}
        </Card>
    );
};

export default NotesTab;
