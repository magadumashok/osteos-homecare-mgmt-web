// src/components/forms/RHFSelect.js
import React from "react";
import { Controller } from "react-hook-form";
import { TextField, MenuItem } from "@mui/material";

const RHFSelect = ({
  name,
  control,
  label,
  options = [],
  rules,
  onChange: onChangeProp,   // <- allow external onChange for cascades
  disabled,
  ...rest
}) => {
  return (
    <Controller
      name={name}
      control={control}
      rules={rules}
      render={({ field: { onChange, value, ref, ...fieldRest }, fieldState: { error } }) => (
        <TextField
          {...fieldRest}
          {...rest}
          inputRef={ref}
          select
          label={label}
          fullWidth
          variant="outlined"
          margin="normal"
          value={value ?? ""}                // <- guard against undefined while options load
          onChange={(e) => {                 // <- update RHF AND fire parent onChange
            onChange(e.target.value);
            if (onChangeProp) onChangeProp(e);
          }}
          error={!!error}
          helperText={error ? error.message : ""}
          InputLabelProps={{ shrink: true }}
          // If you want your custom border class:
          InputProps={{
        classes: {
        notchedOutline: "input-modern label-modern"
         }}}
        >
          {options.length ? (
            options.map((opt) => (
              <MenuItem key={opt.value} value={opt.value}>
                {opt.label}
              </MenuItem>
            ))
          ) : (
            <MenuItem value="" disabled>
              No options
            </MenuItem>
          )}
        </TextField>
      )}
    />
  );
};

export default RHFSelect;
