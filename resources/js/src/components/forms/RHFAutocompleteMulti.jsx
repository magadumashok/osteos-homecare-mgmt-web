import React from "react";
import { Controller } from "react-hook-form";
import Autocomplete from "@mui/material/Autocomplete";
import TextField from "@mui/material/TextField";

const RHFAutocompleteMulti = ({
  name,
  control,
  label,
  options = [],
  loading = false,
  onInputChange,
  disabled = false,
}) => {
  return (
    <Controller
      name={name}
      control={control}
      render={({ field: { value, onChange }, fieldState: { error } }) => (
        <Autocomplete
          multiple
          options={options}
          value={value || []}
          disableCloseOnSelect
          filterSelectedOptions
          disabled={disabled}
          loading={loading}
          getOptionLabel={(opt) => opt.label || ""}
          onInputChange={(e, v) => onInputChange && onInputChange(v)}
          onChange={(e, newValue) => onChange(newValue)}
          renderInput={(params) => (
            <TextField
              {...params}
              label={label}
              error={!!error}
              helperText={error?.message}
              variant="outlined"
            />
          )}
        />
      )}
    />
  );
};

export default RHFAutocompleteMulti;
