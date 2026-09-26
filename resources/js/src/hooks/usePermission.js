import { useMemo } from "react";
import { useSelector } from "react-redux";

const EMPTY = [];

/**
 * usePermission()
 *
 * Returns helpers to gate UI elements based on the current user's permissions.
 * Reads from the auth slice (filled at login, persisted in localStorage), so
 * it updates immediately after login or a profile refresh.
 *
 * This only hides UI: the server enforces the same permissions on every route.
 *
 * Usage:
 *   const { can, canAny } = usePermission();
 *
 *   can('diagnosis.edit')              → true / false
 *   canAny(['lab.view', 'lab.edit'])   → true if either is held
 */
const usePermission = () => {
    const permissions = useSelector((s) => s.auth.permissions) ?? EMPTY;

    // Stable between renders (until the permissions change), so pages can
    // use the helpers as effect and memo dependencies.
    return useMemo(() => {
        /** Single permission check */
        const can = (permission) => {
            if (!permission) return true;   // no gate = always open
            return permissions.includes(permission);
        };

        /** True if the user holds ANY of the listed permissions */
        const canAny = (list = []) => {
            if (!list.length) return true;
            return list.some((p) => permissions.includes(p));
        };

        /** True only if the user holds ALL of the listed permissions */
        const canAll = (list = []) => {
            if (!list.length) return true;
            return list.every((p) => permissions.includes(p));
        };

        return { can, canAny, canAll, permissions };
    }, [permissions]);
};

export default usePermission;
