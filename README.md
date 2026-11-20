# Agro Weather Forecast API

A Dockerized PHP 8.3/PostgreSQL service that records forecasts from Open-Meteo and WeatherAPI. Each successful fetch is an immutable forecast run, retaining both normalized hourly/daily values and the provider response. This makes it possible to compare providers today and measure how a provider's forecast changed over time.

## Installation and setup

### Prerequisites

- Docker Engine or Docker Desktop with Docker Compose v2.
- A free [WeatherAPI](https://www.weatherapi.com/) API key. Open-Meteo does not require a key for this use case.

### Install and run locally

```sh
cp .env.example .env
# Edit .env and set WEATHERAPI_KEY
docker compose up --build -d
# Optional: run a collection immediately rather than waiting for the scheduler
docker compose exec api php bin/console forecast:fetch
```

The first startup creates the PostgreSQL schema and seeds a location. The `scheduler` service collects immediately at startup and then every 24 hours. Stop the stack with `docker compose down`; add `-v` only when intentionally discarding the database volume.

### Environment variables

| Variable | Required | Purpose | Default |
| --- | --- | --- | --- |
| `WEATHERAPI_KEY` | Yes | WeatherAPI authentication key | None |
| `APP_PORT` | No | Host port for the HTTP API | `8080` |
| `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, `DB_PASSWORD` | No | PostgreSQL connection settings | Values in `.env.example` |
| `SEED_LOCATION_NAME`, `SEED_LOCATION_LATITUDE`, `SEED_LOCATION_LONGITUDE`, `SEED_LOCATION_TIMEZONE` | No | Initial field created on first migration | Athens demo field |

Use an IANA timezone such as `Europe/Athens` for `SEED_LOCATION_TIMEZONE`. Seed-location values are applied only to a new database; add further fields through the API after startup.

## API

```sh
# Locations (the seeded location normally has ID 1)
curl http://localhost:8080/v1/locations

# Add a field
curl -X POST http://localhost:8080/v1/locations \
  -H 'Content-Type: application/json' \
  -d '{"name":"North field","latitude":40.6401,"longitude":22.9444,"timezone":"Europe/Athens"}'

# Latest forecast snapshot from each provider, hourly or daily
curl 'http://localhost:8080/v1/forecast-values?location_id=1&resolution=daily&from=2026-11-20T00:00:00Z'

# Set latest=false to return every historic forecast run and see forecast revisions
curl 'http://localhost:8080/v1/forecast-values?location_id=1&provider=open-meteo&resolution=hourly&latest=false'

# Ingestion outcomes, including failures
curl 'http://localhost:8080/v1/forecast-runs?location_id=1'
```

`GET /v1/forecast-values` requires `location_id`; optional filters are `provider`, `resolution` (`hourly`/`daily`), ISO-8601 `from`, `to`, `latest` (defaults to `true`), and `limit` (defaults to 500, maximum 1,000). Values use Celsius and millimetres.

## Implementation choices

- **Framework and dependencies:** The application uses plain PHP 8.3 rather than a framework, PostgreSQL 16, Docker Compose, and built-in PHP extensions (`pdo_pgsql` and `curl`). There is no Composer dependency tree, which keeps the example lightweight and reproducible. The trade-off is that routing, validation, dependency wiring, and migrations are intentionally small custom components rather than framework features.
- **Provider adapters:** Each source implements `ForecastProvider`. Provider-specific response mapping is isolated in its adapter, so adding a source or variable does not require changing the API/query layer.
- **Snapshot data model:** `forecast_runs` records when a forecast was fetched; `forecast_values.valid_at` records the time being forecast. This preserves revisions from the same provider, making provider comparison and forecast-accuracy analysis possible later.
- **Normalization and auditability:** Celsius and millimetres are stored in typed, queryable columns for both hourly and daily data. The original successful JSON response is retained as PostgreSQL `JSONB`, allowing audits and later extraction of additional variables without losing source detail.
- **Reliability and free-tier use:** Requests use connect/read timeouts and up to three attempts with a small incremental backoff. One provider failure does not block other locations or providers; it is stored as a failed run. WeatherAPI request URLs are redacted before persistence so the API key is not stored.
- **Read API:** The API defaults to the newest successful snapshot per provider. `latest=false` exposes prior snapshots for comparing forecast changes. Queries require a location, have validated filters, and cap results at 1,000 rows.

## Incomplete requirements

The requested core behavior is implemented: both providers are supported, data is collected daily, hourly and daily values are persisted in PostgreSQL, and the service is Dockerized.

## Next steps

- Add authontication/authorization.
- Add caching mechanism (Redis).
- Remove all hard-coded default secrets.
- Replace the interval loop with a configurable cron/Kubernetes scheduled job and add a job lock to prevent overlapping collection runs.
- Add pagination/cursors, structured logs, metrics, and alerting for repeated provider failures.
- Add integration tests against PostgreSQL, contract fixtures for provider responses, and a CI pipeline that runs migrations, tests, and container checks.
- Add forecast verification against observed weather, provider-agreement views, domain-oriented frost/irrigation/spraying risk calculations, and retention policies for raw payloads.
- Make providers, variables, units, forecast horizon, and location configuration data-driven through management endpoints or an admin interface.

## Additional notes

- WeatherAPI limits the `days` parameter according to account plan; the adapters request 3 days, which is suitable for common free plans but can be reduced in the adapter configuration if required.
- Open-Meteo is keyless but still has fair-use expectations. The once-daily cadence, one request per provider/location, short retry window, and stored snapshots are deliberately conservative.
- The API stores timestamps as `TIMESTAMPTZ`; daily values represent midnight in the location/provider timezone before being normalized by PostgreSQL. All temperature values are Celsius and all precipitation values are millimetres.
- To run the included adapter checks after the stack starts: `docker compose exec api php tests/ProviderMappingTest.php`.
