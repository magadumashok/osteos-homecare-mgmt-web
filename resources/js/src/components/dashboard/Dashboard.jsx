import React from "react";
import { useSelector } from "react-redux";
import { Card, CardContent, Chip, Stack, Typography } from "@mui/material";

const Dashboard = () => {
    const user = useSelector((s) => s.auth.user);
    const homecare = useSelector((s) => s.auth.homecare);
    const name = user?.name ?? user?.firstname ?? user?.username;
    const roles = user?.roles ?? [];

    return (
        <Stack spacing={3} sx={{ maxWidth: 960 }}>
            <div>
                <Typography variant="h4" component="h1" gutterBottom>
                    Welcome, {name}
                </Typography>
                <Typography color="text.secondary">
                    {homecare?.name}{homecare?.city ? ` · ${homecare.city}` : ""}
                </Typography>
            </div>

            <Card variant="outlined">
                <CardContent>
                    <Typography variant="overline" color="text.secondary">Signed in as</Typography>
                    <Stack direction="row" spacing={1} sx={{ mt: 1, flexWrap: "wrap" }}>
                        {roles.length
                            ? roles.map((r) => <Chip key={r} label={r} size="small" color="primary" variant="outlined" />)
                            : <Typography variant="body2">No role assigned yet. Ask your administrator.</Typography>}
                    </Stack>
                </CardContent>
            </Card>
        </Stack>
    );
};

export default Dashboard;
