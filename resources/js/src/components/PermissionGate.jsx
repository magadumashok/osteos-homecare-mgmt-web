import usePermission from "../hooks/usePermission";

/**
 * PermissionGate
 *
 * Conditionally renders children based on permissions.
 *
 * Props:
 *   permission   string         — single permission required e.g. "diagnosis.edit"
 *   any          string[]       — show if user has ANY of these
 *   all          string[]       — show if user has ALL of these
 *   fallback     ReactNode      — what to render when access denied (default: null)
 *   disabled     bool           — if true, render children but disabled instead of hiding
 *
 * Usage:
 *   <PermissionGate permission="diagnosis.edit">
 *     <button>Save Diagnosis</button>
 *   </PermissionGate>
 *
 *   <PermissionGate permission="prescription.edit" disabled>
 *     <button>Save Prescription</button>
 *   </PermissionGate>
 *
 *   <PermissionGate any={["lab.view", "lab.edit"]} fallback={<p>No access</p>}>
 *     <LabSection />
 *   </PermissionGate>
 */
const PermissionGate = ({
    permission,
    any: anyList,
    all: allList,
    fallback = null,
    disabled = false,
    children,
}) => {
    const { can, canAny, canAll } = usePermission();

    const allowed =
        (permission ? can(permission)       : true) &&
        (anyList    ? canAny(anyList)        : true) &&
        (allList    ? canAll(allList)        : true);

    if (!allowed) {
        if (disabled) {
            // Render children but wrapped in a disabled container
            return (
                <span
                    style={{ opacity: 0.4, cursor: "not-allowed", pointerEvents: "none" }}
                    title="You don't have permission to perform this action"
                >
                    {children}
                </span>
            );
        }
        return fallback;
    }

    return children;
};

export default PermissionGate;
