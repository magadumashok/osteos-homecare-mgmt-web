import React from "react";
import { Link } from "react-router-dom";

export default function NotFound() {
    return (
        <div style={{ minHeight: "70vh", display: "flex", alignItems: "center", justifyContent: "center", textAlign: "center", padding: 24 }}>
            <div>
                <p className="auth-eyebrow">Page not found</p>
                <h2 className="auth-title">This page doesn't exist</h2>
                <p className="auth-subtitle">Check the address, or go back to your dashboard.</p>
                <Link to="/dashboard" className="auth-btn-primary" style={{ display: "inline-flex", textDecoration: "none" }}>
                    Go to dashboard
                </Link>
            </div>
        </div>
    );
}
