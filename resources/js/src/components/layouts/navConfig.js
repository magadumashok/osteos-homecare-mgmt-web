import DashboardOutlined from "@mui/icons-material/DashboardOutlined";

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
