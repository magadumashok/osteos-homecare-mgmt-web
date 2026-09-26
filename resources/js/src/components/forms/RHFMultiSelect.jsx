import React from "react";
import { Controller } from "react-hook-form";
import { Autocomplete, Chip, TextField } from "@mui/material";

/**
 * Multi-select that stores an array of option values (not option objects).
 * options: [{ value, label }]
 */
const RHFMultiSelect = ({ name, control, label, options = [], rules, disabled = false }) => (
    <Controller
        name={name}
        control={control}
        rules={rules}
        render={({ field: { value, onChange }, fieldState: { error } }) => {
            const selected = options.filter((o) => (value ?? []).includes(o.value));
            return (
                <Autocomplete
                    multiple
                    disableCloseOnSelect
                    filterSelectedOptions
                    disabled={disabled}
                    options={options}
                    value={selected}
                    getOptionLabel={(o) => o.label}
                    isOptionEqualToValue={(o, v) => o.value === v.value}
                    onChange={(_, next) => onChange(next.map((o) => o.value))}
                    renderTags={(tags, getTagProps) =>
                        tags.map((o, index) => (
                            <Chip
                                {...getTagProps({ index })}
                                key={o.value}
                                label={o.label}
                                size="small"
                                sx={{ background: "linear-gradient(135deg, #093343, #15698a)", color: "#fff", "& .MuiChip-deleteIcon": { color: "rgba(255,255,255,.7)" } }}
                            />
                        ))
                    }
                    renderInput={(params) => (
                        <TextField {...params} label={label} margin="normal" error={!!error} helperText={error?.message} />
                    )}
                />
            );
        }}
    />
);

export default RHFMultiSelect;
