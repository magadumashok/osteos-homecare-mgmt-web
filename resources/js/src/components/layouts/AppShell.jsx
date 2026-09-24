import React, { useCallback, useEffect, useRef, useState } from "react";
import { useNavigate, useLocation, NavLink, Outlet } from "react-router-dom";
import { useDispatch, useSelector } from "react-redux";
import LogoutOutlined from "@mui/icons-material/LogoutOutlined";
import ArrowBack from "@mui/icons-material/ArrowBack";
import { logout } from "../../store/authSlice";
import usePermission from "../../hooks/usePermission";
import navConfig, { labelForPath } from "./navConfig";

// Sign out after 50 minutes without activity (same as the EMR).
const IDLE_MS = 50 * 60 * 1000;

// ─── Collapsible nav group (from the EMR shell) ───────────────────────────────
function NavGroup({ label, isOpen, onToggle, children }) {
    const contentRef = useRef(null);
    return (
        <div className="shell-group">
            <button type="button" className={`shell-group-btn ${isOpen ? "open" : ""}`} onClick={onToggle} aria-expanded={isOpen}>
                <span className="shell-group-icon"><span>{label}</span></span>
                <span className="shell-group-arrow">▶</span>
            </button>
            <div
                className="shell-group-items"
                ref={contentRef}
                style={{ maxHeight: isOpen ? (contentRef.current?.scrollHeight ?? 600) + "px" : "0px" }}
            >
                {children}
            </div>
        </div>
    );
}

const NavItem = ({ to, label, Icon, exact }) => (
    <NavLink to={to} end={exact} className={({ isActive }) => `shell-nav-item ${isActive ? "active" : ""}`}>
        {Icon && <Icon fontSize="small" />}<span>{label}</span>
    </NavLink>
);

// ─── AppShell ─────────────────────────────────────────────────────────────────
/**
 * Layout route for every signed-in page: sidebar, topbar, and the page in
 * <Outlet />. Pages can pass extra breadcrumb/badges through outlet context:
 *
 *   const { setHeader } = useOutletContext();
 *   useEffect(() => setHeader({ breadcrumb: ["Patients", name], badges: [...] }), [name]);
 */
export default function AppShell() {
    const navigate = useNavigate();
    const location = useLocation();
    const dispatch = useDispatch();
    const { can, canAny } = usePermission();
    const user = useSelector((s) => s.auth.user);
    const homecare = useSelector((s) => s.auth.homecare);
    const path = location.pathname;

    const [header, setHeader] = useState({});
    useEffect(() => setHeader({}), [path]);

    const visible = (item) => (!item.permission || can(item.permission)) && (!item.any || canAny(item.any));
    const groups = navConfig
        .map((g) => ({ ...g, items: g.items.filter(visible) }))
        .filter((g) => g.items.length);

    const groupForPath = (p) => groups.find((g) => g.group && g.items.some((i) => p.startsWith(i.to)))?.group;
    const [openGroup, setOpenGroup] = useState(() => groupForPath(path));
    useEffect(() => setOpenGroup(groupForPath(path)), [path]); // eslint-disable-line react-hooks/exhaustive-deps

    const handleLogout = useCallback(() => {
        dispatch(logout()).finally(() => navigate("/", { replace: true }));
    }, [dispatch, navigate]);

    // ── Idle logout ───────────────────────────────────────────────────────────
    const timerRef = useRef(null);
    const resetTimer = useCallback(() => {
        clearTimeout(timerRef.current);
        timerRef.current = setTimeout(handleLogout, IDLE_MS);
    }, [handleLogout]);

    useEffect(() => {
        const events = ["mousemove", "mousedown", "click", "scroll", "keypress"];
        events.forEach((e) => window.addEventListener(e, resetTimer));
        resetTimer();
        return () => {
            clearTimeout(timerRef.current);
            events.forEach((e) => window.removeEventListener(e, resetTimer));
        };
    }, [resetTimer]);

    const crumbs = header.breadcrumb ?? labelForPath(path);
    const badges = header.badges ?? [];

    return (
        <div className="shell-root">
            {/* ── SIDEBAR ── */}
            <aside className="shell-sidebar">
                <div className="shell-brand">
                    <div>
                        <div className="shell-brand-name">Homecare</div>
                        <div className="shell-brand-sub">{homecare?.name ?? "Care Management"}</div>
                    </div>
                </div>

                <nav className="shell-nav" aria-label="Main">
                    {groups.map((g, i) =>
                        g.group ? (
                            <NavGroup
                                key={g.group}
                                label={g.group}
                                isOpen={openGroup === g.group}
                                onToggle={() => setOpenGroup((o) => (o === g.group ? null : g.group))}
                            >
                                {g.items.map((item) => <NavItem key={item.to} {...item} />)}
                            </NavGroup>
                        ) : (
                            <React.Fragment key={i}>
                                {g.items.map((item) => <NavItem key={item.to} {...item} />)}
                            </React.Fragment>
                        )
                    )}
                </nav>

                <div className="shell-footer">
                    {user && (
                        <div className="shell-section-label" title={user.email ?? ""}>
                            {user.name ?? user.firstname ?? user.username}
                        </div>
                    )}
                    <button type="button" className="shell-logout-btn" onClick={handleLogout}>
                        <LogoutOutlined fontSize="small" />
                        <span>Sign out</span>
                    </button>
                </div>
            </aside>

            {/* ── BODY ── */}
            <div className="shell-body">
                <div className="shell-topbar">
                    {header.showBack && (
                        <button type="button" className="shell-back-btn" onClick={header.onBack ?? (() => navigate(-1))} aria-label="Back">
                            <ArrowBack fontSize="small" />
                        </button>
                    )}

                    <div className="shell-breadcrumb">
                        <span>Homecare</span>
                        {crumbs.map((c, i) => (
                            <React.Fragment key={i}>
                                <span className="shell-breadcrumb-sep">›</span>
                                <span className={i === crumbs.length - 1 ? "shell-breadcrumb-current" : undefined}>{c}</span>
                            </React.Fragment>
                        ))}
                    </div>

                    {badges.length > 0 && (
                        <div className="shell-context-badges">
                            {badges.map((b, i) => (
                                <span key={i} className="shell-badge"><strong>{b.label}:</strong> {b.value}</span>
                            ))}
                        </div>
                    )}
                </div>

                <div className="shell-scroll">
                    <Outlet context={{ setHeader }} />
                </div>
            </div>
        </div>
    );
}
