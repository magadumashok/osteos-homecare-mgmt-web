import React from "react";

/** Two-panel auth layout from the EMR design system (theme.css auth-*). */
export default function AuthLayout({ wide = false, children }) {
    return (
        <div className="auth-root">
            <div className="auth-left">
                <div className="auth-brand">
                    <span className="auth-brand-name">Homecare</span>
                </div>
                <div className="auth-deco auth-deco-1" />
                <div className="auth-deco auth-deco-2" />
                <div className="auth-deco auth-deco-3" />
                <div className="auth-pulse" />
                <div className="auth-left-content">
                    <div className="auth-tag">
                        <span className="auth-tag-dot" />
                        Homecare Management
                    </div>
                    <h1 className="auth-headline">
                        Care at home,<br />
                        <em>coordinated.</em>
                    </h1>
                    <p className="auth-sub">
                        Assessments, duty schedules, medication, vitals and nursing notes
                        for your whole care team, in one place.
                    </p>
                </div>
            </div>

            <div className={`auth-right ${wide ? "auth-right-wide" : ""}`}>
                <div className={`auth-form-wrap ${wide ? "auth-form-wrap-wide" : "auth-form-wrap-narrow"}`}>
                    {children}
                </div>
            </div>
        </div>
    );
}
