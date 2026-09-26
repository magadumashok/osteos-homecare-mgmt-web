import { useCallback, useEffect, useRef, useState } from "react";
import { toast } from "react-toastify";
import { errorMessage } from "../api/client";

/**
 * Load data for a page: `const { data, loading, reload } = useAsync(() => api.list(), [deps])`.
 * Errors are shown as a toast; `data` keeps its initial value until the first load.
 */
export default function useAsync(fetcher, deps = [], initial = null) {
    const [data, setData] = useState(initial);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState(null);
    const latest = useRef(0);

    const reload = useCallback(() => {
        const call = ++latest.current;
        setLoading(true);
        return Promise.resolve()
            .then(fetcher)
            .then((result) => {
                if (call === latest.current) {
                    setData(result ?? initial);
                    setError(null);
                }
                return result;
            })
            .catch((e) => {
                if (call === latest.current) {
                    setError(e);
                    toast.error(errorMessage(e, "Could not load data."));
                }
            })
            .finally(() => {
                if (call === latest.current) setLoading(false);
            });
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, deps);

    useEffect(() => {
        reload();
    }, [reload]);

    return { data, loading, error, reload, setData };
}
