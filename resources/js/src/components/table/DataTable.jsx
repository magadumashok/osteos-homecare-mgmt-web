import React, { useState } from "react";
import {
    useReactTable,
    getCoreRowModel,
    getFilteredRowModel,
    getPaginationRowModel,
    flexRender,
} from "@tanstack/react-table";
import { rankItem } from "@tanstack/match-sorter-utils";

// ── Fuzzy filter ──────────────────────────────────────────────────────────────
const fuzzyFilter = (row, columnId, value, addMeta) => {
    const itemRank = rankItem(row.getValue(columnId), value);
    addMeta(itemRank);
    return itemRank.passed;
};

// ── DataTable ─────────────────────────────────────────────────────────────────
/**
 * Props:
 *   columns             TanStack column defs
 *   data                array of row objects
 *   globalSearch        bool    — show search bar (default true)
 *   pageSize            number  — rows per page (default 10)
 *   searchPlaceholder   string
 *   emptyMessage        string
 *   onRowClick          fn(rowOriginal) — if provided, entire row becomes clickable
 */
const DataTable = ({
    columns,
    data,
    globalSearch = true,
    pageSize = 10,
    searchPlaceholder = "Search…",
    emptyMessage = "No records found",
    onRowClick,
}) => {
    const [globalFilter, setGlobalFilter] = useState("");

    const table = useReactTable({
        data,
        columns,
        state: { globalFilter },
        onGlobalFilterChange: setGlobalFilter,
        globalFilterFn: fuzzyFilter,
        getCoreRowModel: getCoreRowModel(),
        getFilteredRowModel: getFilteredRowModel(),
        getPaginationRowModel: getPaginationRowModel(),
        initialState: { pagination: { pageSize } },
    });

    const currentPage  = table.getState().pagination.pageIndex + 1;
    const totalPages   = table.getPageCount();
    const filteredRows = table.getFilteredRowModel().rows.length;
    const totalRows    = data.length;

    const isClickable = typeof onRowClick === "function";

    return (
        <div style={{ fontFamily: "var(--shell-font, 'DM Sans', sans-serif)" }}>

            {/* ── Toolbar ── */}
            {globalSearch && (
                <div style={{ marginBottom: 16, display: "flex", alignItems: "center", gap: 12 }}>
                    <div style={{ position: "relative", flex: 1, maxWidth: 320 }}>
                        <span style={{
                            position: "absolute", left: 11, top: "50%",
                            transform: "translateY(-50%)",
                            color: "#a0b2bc", fontSize: 14, pointerEvents: "none",
                        }}>
                            🔍
                        </span>
                        <input
                            type="text"
                            value={globalFilter ?? ""}
                            onChange={(e) => setGlobalFilter(e.target.value)}
                            placeholder={searchPlaceholder}
                            style={{
                                width: "100%",
                                paddingLeft: 34,
                                paddingRight: 12,
                                paddingTop: 8,
                                paddingBottom: 8,
                                borderRadius: 8,
                                border: "1px solid #a4b9c9",
                                background: "#fff",
                                fontSize: 13,
                                color: "#0d2b36",
                                outline: "none",
                                transition: "border-color .18s",
                            }}
                            onFocus={(e) => e.target.style.borderColor = "#1a8fa8"}
                            onBlur={(e)  => e.target.style.borderColor = "#dde5eb"}
                        />
                    </div>

                    <span style={{ fontSize: 12, color: "#a0b2bc", flexShrink: 0 }}>
                        {globalFilter
                            ? `${filteredRows} of ${totalRows} records`
                            : `${totalRows} records`}
                    </span>
                </div>
            )}

            {/* ── Table ── */}
            <div style={{ overflowX: "auto", borderRadius: 10, border: "1px solid #9aadbc" }}>
                <table style={{ width: "100%", borderCollapse: "collapse", fontSize: 13 }}>

                    <thead>
                        {table.getHeaderGroups().map((hg) => (
                            <tr key={hg.id}>
                                {hg.headers.map((header) => (
                                    <th
                                        key={header.id}
                                        style={{
                                            padding: "11px 16px",
                                            textAlign: "center",
                                            fontWeight: 600,
                                            fontSize: 11,
                                            letterSpacing: ".6px",
                                            textTransform: "uppercase",
                                            color: "rgba(255,255,255,.9)",
                                            background: "linear-gradient(135deg, #093343, #15698a)",
                                            whiteSpace: "nowrap",
                                            borderBottom: "none",
                                        }}
                                    >
                                        {flexRender(header.column.columnDef.header, header.getContext())}
                                    </th>
                                ))}
                            </tr>
                        ))}
                    </thead>

                    <tbody>
                        {table.getRowModel().rows.length > 0 ? (
                            table.getRowModel().rows.map((row, i) => (
                                <tr
                                    key={row.id}
                                    onClick={isClickable ? () => onRowClick(row.original) : undefined}
                                    style={{
                                        background: i % 2 === 0 ? "#fff" : "#f7f9fc",
                                        transition: "background .15s",
                                        cursor: isClickable ? "pointer" : "default",
                                    }}
                                    onMouseEnter={(e) => e.currentTarget.style.background = "#eef4f7"}
                                    onMouseLeave={(e) => e.currentTarget.style.background = i % 2 === 0 ? "#fff" : "#f7f9fc"}
                                >
                                    {row.getVisibleCells().map((cell) => (
                                        <td
                                            key={cell.id}
                                            style={{
                                                padding: "10px 16px",
                                                borderBottom: "1px solid #f0f4f8",
                                                textAlign: "center",
                                                color: "#334155",
                                                verticalAlign: "middle",
                                            }}
                                        >
                                            {flexRender(cell.column.columnDef.cell, cell.getContext())}
                                        </td>
                                    ))}
                                </tr>
                            ))
                        ) : (
                            <tr>
                                <td
                                    colSpan={columns.length}
                                    style={{
                                        padding: "40px 16px",
                                        textAlign: "center",
                                        color: "#a0b2bc",
                                        fontSize: 13,
                                        fontStyle: "italic",
                                    }}
                                >
                                    {emptyMessage}
                                </td>
                            </tr>
                        )}
                    </tbody>
                </table>
            </div>

            {/* ── Pagination ── */}
            {totalPages > 1 && (
                <div style={{
                    display: "flex",
                    alignItems: "center",
                    justifyContent: "space-between",
                    marginTop: 14,
                    flexWrap: "wrap",
                    gap: 8,
                }}>
                    <span style={{ fontSize: 12, color: "#8a9baa" }}>
                        Page <strong>{currentPage}</strong> of <strong>{totalPages}</strong>
                    </span>

                    <div style={{ display: "flex", gap: 6 }}>
                        <button
                            onClick={() => table.setPageIndex(0)}
                            disabled={!table.getCanPreviousPage()}
                            style={pagerBtn(!table.getCanPreviousPage())}
                            title="First page"
                        >
                            «
                        </button>
                        <button
                            onClick={() => table.previousPage()}
                            disabled={!table.getCanPreviousPage()}
                            style={pagerBtn(!table.getCanPreviousPage())}
                            title="Previous"
                        >
                            ‹
                        </button>

                        {Array.from({ length: totalPages }, (_, i) => i + 1)
                            .filter((p) => p === 1 || p === totalPages || Math.abs(p - currentPage) <= 1)
                            .reduce((acc, p, idx, arr) => {
                                if (idx > 0 && p - arr[idx - 1] > 1) acc.push("…");
                                acc.push(p);
                                return acc;
                            }, [])
                            .map((item, idx) =>
                                item === "…" ? (
                                    <span key={`ellipsis-${idx}`} style={{ padding: "0 4px", color: "#a0b2bc", lineHeight: "30px" }}>…</span>
                                ) : (
                                    <button
                                        key={item}
                                        onClick={() => table.setPageIndex(item - 1)}
                                        style={{
                                            ...pagerBtn(false),
                                            background: item === currentPage
                                                ? "linear-gradient(135deg, #093343, #15698a)"
                                                : "#fff",
                                            color: item === currentPage ? "#fff" : "#4a6275",
                                            fontWeight: item === currentPage ? 600 : 400,
                                            borderColor: item === currentPage ? "transparent" : "#dde5eb",
                                        }}
                                    >
                                        {item}
                                    </button>
                                )
                            )}

                        <button
                            onClick={() => table.nextPage()}
                            disabled={!table.getCanNextPage()}
                            style={pagerBtn(!table.getCanNextPage())}
                            title="Next"
                        >
                            ›
                        </button>
                        <button
                            onClick={() => table.setPageIndex(totalPages - 1)}
                            disabled={!table.getCanNextPage()}
                            style={pagerBtn(!table.getCanNextPage())}
                            title="Last page"
                        >
                            »
                        </button>
                    </div>
                </div>
            )}
        </div>
    );
};

// ── Pagination button style helper ────────────────────────────────────────────
const pagerBtn = (disabled) => ({
    width: 30, height: 30,
    display: "inline-flex", alignItems: "center", justifyContent: "center",
    border: "1px solid #dde5eb",
    borderRadius: 6,
    background: "#fff",
    color: disabled ? "#c5d2da" : "#4a6275",
    fontSize: 14,
    cursor: disabled ? "not-allowed" : "pointer",
    opacity: disabled ? .5 : 1,
    transition: "all .15s",
    padding: 0,
    lineHeight: 1,
});

// ── Action button helpers (use in column cell defs) ───────────────────────────

/** Small primary action button for table cells */
export const TableBtn = ({ onClick, children, variant = "primary", disabled = false }) => {
    const isPrimary   = variant === "primary";
    const isDanger    = variant === "danger";

    const bg    = isPrimary ? "linear-gradient(135deg, #093343, #15698a)" : isDanger ? "rgba(255,80,80,.1)" : "transparent";
    const color  = isPrimary ? "#fff" : isDanger ? "#e05555" : "#4a6275";
    const border = isPrimary ? "none" : isDanger ? "1px solid rgba(255,80,80,.25)" : "1px solid #535c64";
    const shadow = isPrimary ? "0 2px 8px rgba(13,80,104,.2)" : "none";

    return (
        <button
            onClick={onClick}
            disabled={disabled}
            style={{
                padding: "5px 12px",
                borderRadius: 6,
                border,
                background: bg,
                color,
                fontSize: 12,
                fontWeight: 500,
                cursor: disabled ? "not-allowed" : "pointer",
                opacity: disabled ? .6 : 1,
                transition: "all .18s",
                boxShadow: shadow,
                whiteSpace: "nowrap",
                fontFamily: "var(--shell-font, 'DM Sans', sans-serif)",
            }}
            onMouseEnter={(e) => { if (!disabled) e.currentTarget.style.filter = "brightness(1.1)"; }}
            onMouseLeave={(e) => { e.currentTarget.style.filter = "none"; }}
        >
            {children}
        </button>
    );
};

/** Container for multiple action buttons in a cell */
export const ActionCell = ({ children }) => (
    <div style={{ display: "flex", gap: 6, flexWrap: "wrap", alignItems: "center" }}>
        {children}
    </div>
);

export default DataTable;
