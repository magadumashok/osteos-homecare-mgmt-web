import React from "react";

/**
 * ErrorBoundary
 *
 * Wraps layout sections so a crash in one module (e.g. diagnosis)
 * does not white-screen the entire app.
 *
 * Usage:
 *   <ErrorBoundary>
 *     <PatientDiagnosisLayout />
 *   </ErrorBoundary>
 */
class ErrorBoundary extends React.Component {
    constructor(props) {
        super(props);
        this.state = { hasError: false, error: null };
    }

    static getDerivedStateFromError(error) {
        return { hasError: true, error };
    }

    componentDidCatch(error, info) {
        // In production you'd send this to Sentry / Flare
        if (import.meta.env.DEV) {
        }
    }

    render() {
        if (this.state.hasError) {
            return (
                <div style={{
                    display: "flex",
                    flexDirection: "column",
                    alignItems: "center",
                    justifyContent: "center",
                    minHeight: "40vh",
                    padding: "2rem",
                    textAlign: "center",
                }}>
                    <div style={{ fontSize: 40, marginBottom: 16 }}>⚠️</div>
                    <h3 style={{ color: "#DC2626", marginBottom: 8 }}>Something went wrong</h3>
                    <p style={{ color: "#6B7280", marginBottom: 20, maxWidth: 400 }}>
                        An error occurred in this section. The rest of the app is still functional.
                    </p>
                    <button
                        onClick={() => this.setState({ hasError: false, error: null })}
                        style={{
                            padding: "8px 20px",
                            background: "#3B82F6",
                            color: "#fff",
                            border: "none",
                            borderRadius: 6,
                            cursor: "pointer",
                            fontSize: 14,
                        }}
                    >
                        Try Again
                    </button>
                    {import.meta.env.DEV && this.state.error && (
                        <pre style={{
                            marginTop: 16, fontSize: 11, color: "#DC2626",
                            background: "#FEF2F2", padding: 12, borderRadius: 6,
                            maxWidth: 600, overflow: "auto", textAlign: "left",
                        }}>
                            {this.state.error.toString()}
                        </pre>
                    )}
                </div>
            );
        }

        return this.props.children;
    }
}

export default ErrorBoundary;
