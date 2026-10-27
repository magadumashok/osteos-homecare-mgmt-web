import React, { lazy, Suspense } from "react";
import { Routes, Route, Navigate } from "react-router-dom";
import { useSelector } from "react-redux";
import PrivateRoute from "./PrivateRoute.jsx";
import ErrorBoundary from "./ErrorBoundary.jsx";
import PermissionGate from "./PermissionGate.jsx";
import AppShell from "./layouts/AppShell.jsx";
import { isLoggedIn } from "../services/AuthService";

// ─── Loading fallback ─────────────────────────────────────────────────────────
const PageLoader = () => (
    <div style={{ display: "flex", justifyContent: "center", alignItems: "center", height: "60vh" }}>
        <div style={{ textAlign: "center", color: "#8a9baa" }}>
            <div style={{ fontSize: 28, marginBottom: 10, animation: "spin 1s linear infinite" }}>⟳</div>
            <div style={{ fontSize: 13 }}>Loading…</div>
        </div>
        <style>{`@keyframes spin { to { transform: rotate(360deg); } }`}</style>
    </div>
);

// ─── Lazy imports ─────────────────────────────────────────────────────────────

// Auth
const LoginComponent   = lazy(() => import("./auth/LoginComponent.jsx"));
const RegisterHomecare = lazy(() => import("./auth/RegisterHomecare.jsx"));

// Dashboard
const Dashboard        = lazy(() => import("./dashboard/Dashboard.jsx"));

// Staff & roles
const StaffList        = lazy(() => import("./staff/StaffList.jsx"));
const StaffForm        = lazy(() => import("./staff/StaffForm.jsx"));
const StaffAttendance  = lazy(() => import("./staff/StaffAttendance.jsx"));
const RolesPage        = lazy(() => import("./staff/RolesPage.jsx"));

// Intake
const LeadList          = lazy(() => import("./leads/LeadList.jsx"));
const AssessmentList    = lazy(() => import("./assessments/AssessmentList.jsx"));
const AssessmentForm    = lazy(() => import("./assessments/AssessmentForm.jsx"));
const AssessmentInvoice = lazy(() => import("./assessments/AssessmentInvoice.jsx"));

// Patients
const PatientList       = lazy(() => import("./patients/PatientList.jsx"));
const PatientForm       = lazy(() => import("./patients/PatientForm.jsx"));
const PatientPage       = lazy(() => import("./patients/PatientPage.jsx"));
const ConsentForm       = lazy(() => import("./patients/ConsentForm.jsx"));
const DutyInvoice       = lazy(() => import("./patients/DutyInvoice.jsx"));
const PrescriptionPrint = lazy(() => import("./patients/PrescriptionPrint.jsx"));
const DischargedList    = lazy(() => import("./patients/DischargedList.jsx"));

// Scheduling
const DutyList          = lazy(() => import("./schedules/DutyList.jsx"));
const DutyForm          = lazy(() => import("./schedules/DutyForm.jsx"));
const CareUpdates       = lazy(() => import("./schedules/CareUpdates.jsx"));
const CareDutyLog       = lazy(() => import("./schedules/CareDutyLog.jsx"));

const NotFound         = lazy(() => import("./NotFound.jsx"));

// Signed-in users skip the login and registration pages.
const GuestOnly = ({ children }) => {
    const isAuthenticated = useSelector((s) => s.auth.isAuthenticated);
    return isAuthenticated && isLoggedIn() ? <Navigate to="/dashboard" replace /> : children;
};

// Pages the user may not open show a notice instead (the API refuses them too).
const Allowed = ({ permission, any, children }) => (
    <PermissionGate
        permission={permission}
        any={any}
        fallback={<p style={{ color: "#8a9baa", fontSize: 14 }}>You do not have access to this page. Ask your administrator.</p>}
    >
        {children}
    </PermissionGate>
);

// Every signed-in page renders inside the shell (sidebar + topbar).
const ShellLayout = () => (
    <ErrorBoundary>
        <AppShell />
    </ErrorBoundary>
);

export default function AppRoutes() {
    return (
        <Suspense fallback={<PageLoader />}>
            <Routes>
                {/* Public */}
                <Route path="/" element={<GuestOnly><LoginComponent /></GuestOnly>} />
                <Route path="/register" element={<GuestOnly><RegisterHomecare /></GuestOnly>} />

                {/* Signed in */}
                <Route element={<PrivateRoute />}>
                    <Route element={<ShellLayout />}>
                        <Route path="/dashboard" element={<Dashboard />} />

                        <Route path="/staff" element={<Allowed any={["staff.view", "staff.manage"]}><StaffList /></Allowed>} />
                        <Route path="/staff/new" element={<Allowed permission="staff.manage"><StaffForm /></Allowed>} />
                        <Route path="/staff/:id/edit" element={<Allowed permission="staff.manage"><StaffForm /></Allowed>} />
                        <Route path="/staff/:id/attendance" element={<Allowed permission="attendance.view"><StaffAttendance /></Allowed>} />
                        <Route path="/roles" element={<Allowed permission="roles.manage"><RolesPage /></Allowed>} />

                        <Route path="/leads" element={<Allowed permission="leads.view"><LeadList /></Allowed>} />
                        <Route path="/assessments" element={<Allowed permission="assessments.view"><AssessmentList /></Allowed>} />
                        <Route path="/assessments/new" element={<Allowed permission="assessments.manage"><AssessmentForm /></Allowed>} />
                        <Route path="/assessments/:id/edit" element={<Allowed permission="assessments.manage"><AssessmentForm /></Allowed>} />
                        <Route path="/assessments/:id/invoice" element={<Allowed any={["assessments.view", "invoices.view"]}><AssessmentInvoice /></Allowed>} />

                        <Route path="/patients" element={<Allowed permission="patients.view"><PatientList /></Allowed>} />
                        <Route path="/patients/new" element={<Allowed permission="patients.manage"><PatientForm /></Allowed>} />
                        <Route path="/patients/discharged" element={<Allowed permission="discharge.view"><DischargedList /></Allowed>} />
                        <Route path="/patients/:username" element={<Allowed any={["patients.view", "invoices.view"]}><PatientPage /></Allowed>} />
                        <Route path="/patients/:username/edit" element={<Allowed permission="patients.manage"><PatientForm /></Allowed>} />
                        <Route path="/patients/:username/consent" element={<Allowed any={["patients.view", "invoices.view"]}><ConsentForm /></Allowed>} />
                        <Route path="/patients/:username/duties/:id/invoice" element={<Allowed permission="schedules.view"><DutyInvoice /></Allowed>} />
                        <Route path="/prescriptions/:type/:id" element={<Allowed permission="prescriptions.view"><PrescriptionPrint /></Allowed>} />

                        <Route path="/schedules" element={<Allowed permission="schedules.view"><DutyList /></Allowed>} />
                        <Route path="/schedules/new" element={<Allowed permission="schedules.manage"><DutyForm /></Allowed>} />
                        <Route path="/schedules/:id/edit" element={<Allowed permission="schedules.manage"><DutyForm /></Allowed>} />
                        <Route path="/schedules/:id/care" element={<Allowed permission="schedules.view"><CareDutyLog /></Allowed>} />
                        <Route path="/care-updates" element={<Allowed permission="schedules.view"><CareUpdates /></Allowed>} />
                    </Route>
                </Route>

                <Route path="*" element={<NotFound />} />
            </Routes>
        </Suspense>
    );
}
