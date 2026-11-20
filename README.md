# Agro Weather Forecast API

A Dockerized PHP 8.3/PostgreSQL service that records forecasts from Open-Meteo and WeatherAPI. Each successful fetch is an immutable forecast run, retaining both normalized hourly/daily values and the provider response. This makes it possible to compare providers today and measure how a provider's forecast changed over time.

## Run

```sh
cp .env.example .env
# Set WEATHERAPI_KEY in .env
docker compose up --build -d
docker compose exec api php bin/console forecast:fetch
```

The first startup migrates the database and creates the configured demo location. The scheduler service fetches every 24 hours after it starts. Production deployments should replace that loop with a platform scheduler invoking `php bin/console forecast:fetch` at a fixed local or UTC time.

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

## Design notes

- Providers implement one adapter interface, so more sources can be added independently of storage and API querying.
- `forecast_runs` captures when a provider made a forecast; `forecast_values.valid_at` captures the time forecasted. The distinction preserves revisions.
- Failed requests create a failed run with a bounded error message; one provider failure does not prevent other locations/providers from running.
- Requests use short timeouts and up to three attempts with a small backoff, limiting transient failures without overusing free-tier quotas.
- Raw successful provider JSON is kept in `response_payload` for auditability and future variables, while normalized fields support fast comparisons.
- Open-Meteo is keyless. WeatherAPI requires `WEATHERAPI_KEY`; an absent or invalid key appears as a failed WeatherAPI run, rather than silently omitting that provider.

## Check

```sh
docker compose exec api php tests/ProviderMappingTest.php
```
