import React, { lazy, Suspense } from "react";
import { Routes, Route, Navigate } from "react-router-dom";
import { useSelector } from "react-redux";
import PrivateRoute from "./PrivateRoute.jsx";
import ErrorBoundary from "./ErrorBoundary.jsx";
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

const NotFound         = lazy(() => import("./NotFound.jsx"));

// Signed-in users skip the login and registration pages.
const GuestOnly = ({ children }) => {
    const isAuthenticated = useSelector((s) => s.auth.isAuthenticated);
    return isAuthenticated && isLoggedIn() ? <Navigate to="/dashboard" replace /> : children;
};

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
                    </Route>
                </Route>

                <Route path="*" element={<NotFound />} />
            </Routes>
        </Suspense>
    );
}
