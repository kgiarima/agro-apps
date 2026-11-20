CREATE TABLE IF NOT EXISTS schema_migrations (
    version TEXT PRIMARY KEY,
    applied_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE TABLE IF NOT EXISTS locations (
    id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    name TEXT NOT NULL,
    latitude NUMERIC(9,6) NOT NULL CHECK (latitude BETWEEN -90 AND 90),
    longitude NUMERIC(9,6) NOT NULL CHECK (longitude BETWEEN -180 AND 180),
    timezone TEXT NOT NULL,
    active BOOLEAN NOT NULL DEFAULT TRUE,
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    UNIQUE (latitude, longitude)
);

CREATE TABLE IF NOT EXISTS weather_providers (
    id SMALLINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    slug TEXT NOT NULL UNIQUE,
    name TEXT NOT NULL,
    enabled BOOLEAN NOT NULL DEFAULT TRUE,
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE TABLE IF NOT EXISTS forecast_runs (
    id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    location_id BIGINT NOT NULL REFERENCES locations(id),
    provider_id SMALLINT NOT NULL REFERENCES weather_providers(id),
    fetched_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    status TEXT NOT NULL CHECK (status IN ('succeeded', 'failed')),
    request_url TEXT,
    response_payload JSONB,
    error_message TEXT,
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    CHECK ((status = 'succeeded' AND error_message IS NULL) OR status = 'failed'),
    UNIQUE (location_id, provider_id, fetched_at)
);

CREATE INDEX IF NOT EXISTS forecast_runs_lookup_idx
    ON forecast_runs (location_id, provider_id, fetched_at DESC);

CREATE TABLE IF NOT EXISTS forecast_values (
    id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    forecast_run_id BIGINT NOT NULL REFERENCES forecast_runs(id) ON DELETE CASCADE,
    resolution TEXT NOT NULL CHECK (resolution IN ('hourly', 'daily')),
    valid_at TIMESTAMPTZ NOT NULL,
    temperature_c NUMERIC(6,2),
    precipitation_mm NUMERIC(7,2),
    metadata JSONB NOT NULL DEFAULT '{}'::jsonb,
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    CHECK (temperature_c IS NOT NULL OR precipitation_mm IS NOT NULL),
    UNIQUE (forecast_run_id, resolution, valid_at)
);

CREATE INDEX IF NOT EXISTS forecast_values_query_idx
    ON forecast_values (resolution, valid_at, forecast_run_id);
