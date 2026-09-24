// src/components/forms/RHFCheckbox.js
import React from "react";
import { Controller } from "react-hook-form";
import { FormControlLabel, Checkbox } from "@mui/material";

const RHFCheckbox = ({ name, control, label, rules }) => {
  return (
    <Controller
      name={name}
      control={control}
      rules={rules}
      render={({ field, fieldState: { error } }) => (
        <>
          <FormControlLabel
            control={
              <Checkbox
                {...field}
                checked={field.value || false}
              />
            }
            label={label}
          />
          {error && (
            <p style={{ color: "red", fontSize: "0.8rem", margin: "4px 0 0" }}>
              {error.message}
            </p>
          )}
        </>
      )}
    />
  );
};

export default RHFCheckbox;
