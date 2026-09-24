import { instance } from "./client";

const get = (url) => instance.get(url).then((r) => r.data.data);

export const countries = () => get("reference/countries");
export const regions = (countryCode) => get(`reference/regions/${encodeURIComponent(countryCode)}`);
export const cities = (regionCode) => get(`reference/cities/${encodeURIComponent(regionCode)}`);
export const nationalities = () => get("reference/nationalities");
