import DashboardOutlined from "@mui/icons-material/DashboardOutlined";
import BadgeOutlined from "@mui/icons-material/BadgeOutlined";
import AdminPanelSettingsOutlined from "@mui/icons-material/AdminPanelSettingsOutlined";
import ContactPhoneOutlined from "@mui/icons-material/ContactPhoneOutlined";
import AssignmentOutlined from "@mui/icons-material/AssignmentOutlined";
import PeopleAltOutlined from "@mui/icons-material/PeopleAltOutlined";
import ExitToAppOutlined from "@mui/icons-material/ExitToAppOutlined";
import EventNoteOutlined from "@mui/icons-material/EventNoteOutlined";

/**
 * Sidebar navigation. Each module adds its entries here as it is ported.
 *
 *   group        — collapsible section label (omit for a top-level link)
 *   permission   — hide unless the user holds it (the route checks it too)
 *   any          — hide unless the user holds at least one of these
 *   exact        — highlight only on an exact path match
 */
const navConfig = [
    {
        items: [
            { to: "/dashboard", label: "Dashboard", Icon: DashboardOutlined, exact: true },
        ],
    },
    {
        group: "Intake",
        items: [
            { to: "/leads", label: "Leads", Icon: ContactPhoneOutlined, permission: "leads.view" },
            { to: "/assessments", label: "Assessments", Icon: AssignmentOutlined, permission: "assessments.view" },
        ],
    },
    {
        group: "Patients",
        items: [
            { to: "/patients", label: "Patients", Icon: PeopleAltOutlined, permission: "patients.view" },
            { to: "/patients/discharged", label: "Discharged", Icon: ExitToAppOutlined, permission: "discharge.view", exact: true },
        ],
    },
    {
        group: "Scheduling",
        items: [
            { to: "/schedules", label: "Duty schedules", Icon: EventNoteOutlined, permission: "schedules.view" },
        ],
    },
    {
        group: "Administration",
        items: [
            { to: "/staff", label: "Staff members", Icon: BadgeOutlined, any: ["staff.view", "staff.manage"] },
            { to: "/roles", label: "Roles & permissions", Icon: AdminPanelSettingsOutlined, permission: "roles.manage" },
        ],
    },
];

export default navConfig;

/** Label of the nav item matching a path, for the topbar breadcrumb. */
export const labelForPath = (path) => {
    let best = null;
    navConfig.forEach((group) =>
        group.items.forEach((item) => {
            if (path === item.to || path.startsWith(item.to + "/")) {
                if (!best || item.to.length > best.to.length) best = { ...item, group: group.group };
            }
        })
    );
    return best ? [best.group, best.label].filter(Boolean) : [];
};
