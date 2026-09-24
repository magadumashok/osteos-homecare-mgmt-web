// src/components/forms/RHFDatePicker.js
import { Controller } from "react-hook-form";
import { DatePicker } from "@mui/x-date-pickers/DatePicker";

const RHFDatePicker = ({ name, control, label, rules }) => (
  <Controller
    name={name}
    control={control}
    rules={rules}
    render={({ field, fieldState: { error } }) => (
      <DatePicker
        value={field.value || null}
        onChange={(newValue) => field.onChange(newValue)}
        label={label}
        slotProps={{
          textField: {
            fullWidth: true,
            variant: "outlined",
            margin: "normal",
            error: !!error,
            helperText: error?.message,
            InputProps: {
              classes: { notchedOutline: "input-modern label-modern" },
            },
          },
        }}
      />
    )}
  />
);

export default RHFDatePicker;
