// src/components/forms/RHFToggle.js
import React from "react";
import { Controller } from "react-hook-form";
import { FormControlLabel, Switch } from "@mui/material";

const RHFToggle = ({ name, control, label, defaultValue = false, ...props }) => {
  return (
    <Controller
      name={name}
      control={control}
      defaultValue={defaultValue}
      render={({ field }) => (
        <FormControlLabel
          control={
            <Switch
              {...field}
              checked={!!field.value}
              onChange={(e) => field.onChange(e.target.checked)}
              color="primary"
              {...props}
            />
          }
          label={label}
        />
      )}
    />
  );
};

export default RHFToggle;
