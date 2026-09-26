import React, { useEffect, useMemo, useState } from "react";
import { useWatch } from "react-hook-form";
import { Grid } from "@mui/material";
import RHFSelect from "../forms/RHFSelect";
import RHFTextField from "../forms/RHFTextField";
import * as reference from "../../api/reference";

const toOptions = (rows, valueKey) =>
    (rows ?? []).map((r) => ({ value: r[valueKey] ?? r.name ?? r.label, label: r.name ?? r.label }));

// Countries rarely change; share one request between every address block.
let countriesRequest = null;
const loadCountries = () => (countriesRequest ??= reference.countries().catch((e) => {
    countriesRequest = null;
    throw e;
}));

/**
 * Country → region → city → street address, stored under `prefix`
 * (e.g. "temporary_address.country"). Country and region hold codes, city
 * holds the name, as the legacy data does.
 */
export default function AddressFields({ control, setValue, prefix, required = false, disabled = false, withCity = true }) {
    const name = (field) => (prefix ? `${prefix}.${field}` : field);
    const country = useWatch({ control, name: name("country") });
    const region = useWatch({ control, name: name("region") });

    const [countries, setCountries] = useState([]);
    const [regions, setRegions] = useState([]);
    const [cities, setCities] = useState([]);

    useEffect(() => {
        loadCountries().then(setCountries).catch(() => setCountries([]));
    }, []);

    useEffect(() => {
        setRegions([]);
        if (country) reference.regions(country).then(setRegions).catch(() => setRegions([]));
    }, [country]);

    useEffect(() => {
        setCities([]);
        if (region && withCity) reference.cities(region).then(setCities).catch(() => setCities([]));
    }, [region, withCity]);

    const countryOptions = useMemo(() => toOptions(countries, "code"), [countries]);
    const regionOptions = useMemo(() => toOptions(regions, "code"), [regions]);
    const cityOptions = useMemo(() => toOptions(cities, "name"), [cities]);
    const req = required ? { required: "Required" } : undefined;

    return (
        <Grid container columnSpacing={2}>
            <Grid item xs={12} sm={withCity ? 4 : 6}>
                <RHFSelect
                    name={name("country")}
                    control={control}
                    label={`Country${required ? " *" : ""}`}
                    options={countryOptions}
                    rules={req}
                    disabled={disabled}
                    onChange={() => {
                        setValue(name("region"), "");
                        if (withCity) setValue(name("city"), "");
                    }}
                />
            </Grid>
            <Grid item xs={12} sm={withCity ? 4 : 6}>
                <RHFSelect
                    name={name("region")}
                    control={control}
                    label="State / Region"
                    options={regionOptions}
                    disabled={disabled || !country}
                    onChange={() => withCity && setValue(name("city"), "")}
                />
            </Grid>
            {withCity && (
                <Grid item xs={12} sm={4}>
                    <RHFSelect name={name("city")} control={control} label="City" options={cityOptions} disabled={disabled || !region} />
                </Grid>
            )}
            <Grid item xs={12}>
                <RHFTextField name={name("address")} control={control} label="Street address" disabled={disabled} multiline minRows={2} />
            </Grid>
        </Grid>
    );
}
