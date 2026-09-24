import React from "react";
import { Navigate, Outlet, useLocation } from "react-router-dom";
import { useSelector } from "react-redux";
import { isLoggedIn } from "../services/AuthService";
import usePermission from "../hooks/usePermission";

/**
 * Route guard.
 *   <Route element={<PrivateRoute />}>                          — signed in
 *   <Route element={<PrivateRoute permission="staff.view" />}>  — and allowed
 *
 * Checks the token's expiry, not just its presence.
 */
const PrivateRoute = ({ permission, any }) => {
    const location = useLocation();
    const isAuthenticated = useSelector((s) => s.auth.isAuthenticated);
    const { can, canAny } = usePermission();

    if (!isAuthenticated || !isLoggedIn()) {
        return <Navigate to="/" replace state={{ from: location.pathname }} />;
    }

    if ((permission && !can(permission)) || (any && !canAny(any))) {
        return <Navigate to="/dashboard" replace />;
    }

    return <Outlet />;
};

export default PrivateRoute;
