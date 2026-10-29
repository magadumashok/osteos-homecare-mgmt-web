import { authInstance } from "./client";

// Signed-in API calls. Every endpoint returns { success, message, data }; these return `data`.
const data = (r) => r.data.data;
const get = (url, params) => authInstance.get(url, { params }).then(data);
const post = (url, body, config) => authInstance.post(url, body, config).then(data);
const put = (url, body) => authInstance.put(url, body).then(data);
const del = (url, body) => authInstance.delete(url, { data: body }).then(data);
const enc = encodeURIComponent;
// FormData bodies: axios sets the multipart Content-Type (with boundary) itself.

/** Build FormData from plain values; arrays of files go in as `key[]`. */
export const toFormData = (values) => {
    const form = new FormData();
    Object.entries(values).forEach(([key, value]) => {
        if (value === undefined || value === null || value === "") return;
        if (Array.isArray(value) && value.every((v) => v instanceof Blob)) {
            value.forEach((file) => form.append(`${key}[]`, file));
        } else if (value instanceof Blob) {
            form.append(key, value);
        } else if (typeof value === "object") {
            form.append(key, JSON.stringify(value));
        } else {
            form.append(key, value);
        }
    });
    return form;
};

// ── Reference ───────────────────────────────────────────────────────────────
export const reference = {
    servicePackages: () => get("reference/service-packages"),
    icdCodes: (search) => get("reference/icd-codes", { search }),
};

// ── Staff & roles ─────────────────────────────────────────────────────────────
export const staff = {
    list: (params) => get("staff", params),
    directory: () => get("staff/directory"),
    show: (id) => get(`staff/${enc(id)}`),
    create: (body) => post("staff", body),
    update: (id, body) => put(`staff/${enc(id)}`, body),
    remove: (id) => del(`staff/${enc(id)}`),
    setRoles: (id, roles) => put(`staff/${enc(id)}/roles`, { roles }),
    patients: (id) => get(`staff/${enc(id)}/patients`),
};

export const roles = {
    list: () => get("roles"),
    permissions: () => get("permissions"),
    create: (body) => post("roles", body),
    update: (id, body) => put(`roles/${enc(id)}`, body),
    setPermissions: (id, permissions) => put(`roles/${enc(id)}/permissions`, { permissions }),
    remove: (id) => del(`roles/${enc(id)}`),
};

// ── Leads & assessments ───────────────────────────────────────────────────────
export const leads = {
    list: () => get("leads"),
    show: (leadId) => get(`leads/${enc(leadId)}`),
    create: (body) => post("leads", body),
    update: (leadId, body) => put(`leads/${enc(leadId)}`, body),
    toBeAssessed: (leadId) => post(`leads/${enc(leadId)}/to-be-assessed`),
};

export const assessments = {
    list: () => get("assessments"),
    show: (id) => get(`assessments/${enc(id)}`),
    create: (body) => post("assessments", body),
    update: (id, body) => put(`assessments/${enc(id)}`, body),
    admit: (id, body = {}) => post(`assessments/${enc(id)}/admit`, body),
};

// ── Patients ──────────────────────────────────────────────────────────────────
const p = (username) => `patients/${enc(username)}`;

export const patients = {
    list: (status) => get("patients", status ? { status } : undefined),
    show: (username) => get(p(username)),
    create: (body) => post("patients", body),
    update: (username, body) => put(p(username), body),
    discharged: () => get("patients/discharged"),
    consent: (username, body) => post(`${p(username)}/consent`, body),
    scheduleDischarge: (username, body) => put(`${p(username)}/discharge-date`, body),
    discharge: (username, body) => post(`${p(username)}/discharge`, body),
    schedules: (username) => get(`${p(username)}/schedules`),
    updateServices: (username, body) => put(`${p(username)}/services`, body),

    documents: (username) => get(`${p(username)}/documents`),
    upload: (username, field, files) => post(`${p(username)}/documents`, toFormData({ field, files })),
    removeDocument: (username, url, field) => del(`${p(username)}/documents`, { url, field }),
    requestMissing: (username, message) => post(`${p(username)}/missing-documents`, { message }),

    vitals: (username, params) => get(`${p(username)}/vitals`, params),
    latestVitals: (username) => get(`${p(username)}/vitals/latest`),
    addVitals: (username, body) => post(`${p(username)}/vitals`, body),
    updateVitals: (id, body) => put(`vitals/${enc(id)}`, body),

    notes: (username, kind, params) => get(`${p(username)}/notes/${kind}`, params),
    addNote: (username, kind, body) => post(`${p(username)}/notes/${kind}`, body),

    medications: (username) => get(`${p(username)}/medications`),
    addMedication: (username, body) => post(`${p(username)}/medications`, body),
    deleteMedication: (username, id) => del(`${p(username)}/medications/${enc(id)}`),
    medicationChecklist: (username, date) => get(`${p(username)}/medications/checklist`, { date }),
    administered: (username, date) => get(`${p(username)}/medications/administered`, { date }),
    administer: (username, body) => post(`${p(username)}/medications/administered`, body),

    prescriptions: (username, type) => get(`${p(username)}/prescriptions/${type}`),
    addPrescription: (username, type, body) => post(`${p(username)}/prescriptions/${type}`, body),
    prescription: (type, id) => get(`prescriptions/${type}/${enc(id)}`),
};

// ── Schedules & attendance ────────────────────────────────────────────────────
export const duties = {
    list: (params) => get("duties", params),
    mine: (date) => get("duties/mine", { date }),
    show: (id) => get(`duties/${enc(id)}`),
    create: (body) => post("duties", body),
    createSeries: (body) => post("duties/series", body),
    reschedule: (id, body) => put(`duties/${enc(id)}`, body),
    notAttended: (id, reason) => post(`duties/${enc(id)}/not-attended`, { reason }),
    staffAttendance: (staffId, patientId) => get(`attendance/staff/${enc(staffId)}`, patientId ? { patient_id: patientId } : undefined),
    driverAttendance: (driverId) => get(`attendance/drivers/${enc(driverId)}`),
    createShortVisit: (body) => post("short-visits", body),
};

// 24-hour caretaker duties: daily care log, skipped days and covers.
export const careLogs = {
    day: (params) => get("care-logs", params),
    tasks: () => get("care-logs/tasks"),
    forDuty: (dutyId) => get(`duties/${enc(dutyId)}/care-logs`),
    save: (dutyId, date, body) => put(`duties/${enc(dutyId)}/care-logs/${enc(date)}`, body),
    skipDay: (dutyId, date, reason) => post(`duties/${enc(dutyId)}/skip-day`, { date, reason }),
    coverDay: (dutyId, date, staffId) => post(`duties/${enc(dutyId)}/cover-day`, { date, staff_id: staffId }),
};

export const driverTrips = {
    create: (body) => post("driver-trips", body),
};

// ── Own account ───────────────────────────────────────────────────────────────
export const account = {
    feedback: (feedback) => post("account/feedback", { feedback }),
    videoCall: (username, videoRoomUrl) => post("video-calls", { username, video_room_url: videoRoomUrl }),
};
