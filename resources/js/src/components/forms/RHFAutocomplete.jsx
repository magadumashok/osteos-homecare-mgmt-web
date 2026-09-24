import React from "react";
import { Controller } from "react-hook-form";
import { Autocomplete, TextField } from "@mui/material";

const RHFAutocomplete = ({
  name,
  control,
  label,
  options = [],
  freeSolo = false,
  rules,
  disabled = false,
}) => {
  return (
    <Controller
      name={name}
      control={control}
      rules={rules}
      render={({ field, fieldState: { error } }) => (
        <Autocomplete
          options={options}
          freeSolo={freeSolo}
          value={field.value || ""}
          onChange={(_, value) => field.onChange(value)}
          onInputChange={(_, value) => {
            if (freeSolo) field.onChange(value);
          }}
          disabled={disabled}
          renderInput={(params) => (
            <TextField
              {...params}
              label={label}
              error={!!error}
              helperText={error?.message}
              margin="normal"
              fullWidth
              variant="outlined"
            />
          )}
        />
      )}
    />
  );
};

export default RHFAutocomplete;
