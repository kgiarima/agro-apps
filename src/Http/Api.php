<?php
declare(strict_types=1);

namespace App\Http;

use App\Repository\ForecastRepository;
use App\Repository\LocationRepository;

final class Api
{
    public function __construct(private readonly LocationRepository $locations, private readonly ForecastRepository $forecasts) {}

    public function handle(string $method, string $path, array $query, array $body): never
    {
        try {
            if ($method === 'GET' && $path === '/health') { $this->respond(['status' => 'ok']); }
            if ($method === 'GET' && $path === '/v1/locations') { $this->respond(['data' => $this->locations->all()]); }
            if ($method === 'POST' && $path === '/v1/locations') { $this->respond(['data' => $this->locations->create($this->validateLocation($body))], 201); }
            if ($method === 'GET' && $path === '/v1/forecast-runs') { $this->respond(['data' => $this->forecasts->runs(isset($query['location_id']) ? (int) $query['location_id'] : null)]); }
            if ($method === 'GET' && $path === '/v1/forecast-values') {
                if (!isset($query['location_id']) || (int) $query['location_id'] < 1) { throw new \InvalidArgumentException('location_id is required.'); }
                $this->validateForecastFilters($query);
                $this->respond(['data' => $this->forecasts->values($query)]);
            }
            $this->respond(['error' => 'Not found.'], 404);
        } catch (\InvalidArgumentException $exception) {
            $this->respond(['error' => $exception->getMessage()], 422);
        } catch (\Throwable $exception) {
            error_log($exception->getMessage());
            $this->respond(['error' => 'Internal server error.'], 500);
        }
    }

    private function validateLocation(array $input): array
    {
        foreach (['name', 'latitude', 'longitude', 'timezone'] as $key) { if (!isset($input[$key]) || $input[$key] === '') { throw new \InvalidArgumentException("{$key} is required."); } }
        if (!is_numeric($input['latitude']) || !is_numeric($input['longitude'])) { throw new \InvalidArgumentException('Coordinates must be numeric.'); }
        try { new \DateTimeZone($input['timezone']); } catch (\Exception) { throw new \InvalidArgumentException('timezone must be a valid IANA timezone.'); }
        return ['name' => (string) $input['name'], 'latitude' => (float) $input['latitude'], 'longitude' => (float) $input['longitude'], 'timezone' => (string) $input['timezone']];
    }

    private function validateForecastFilters(array &$query): void
    {
        if (isset($query['provider']) && !in_array($query['provider'], ['open-meteo', 'weatherapi'], true)) { throw new \InvalidArgumentException('provider is not supported.'); }
        if (isset($query['resolution']) && !in_array($query['resolution'], ['hourly', 'daily'], true)) { throw new \InvalidArgumentException('resolution must be hourly or daily.'); }
        if (isset($query['latest']) && !in_array($query['latest'], ['true', 'false'], true)) { throw new \InvalidArgumentException('latest must be true or false.'); }
        foreach (['from', 'to'] as $key) {
            if (isset($query[$key])) {
                try { new \DateTimeImmutable($query[$key]); } catch (\Exception) { throw new \InvalidArgumentException("{$key} must be ISO-8601."); }
            }
        }
        $limit = isset($query['limit']) ? filter_var($query['limit'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 1000]]) : 500;
        if ($limit === false) { throw new \InvalidArgumentException('limit must be between 1 and 1000.'); }
        $query['limit'] = $limit;
    }

    private function respond(array $data, int $status = 200): never
    {
        http_response_code($status);
        header('Content-Type: application/json');
        echo json_encode($data, JSON_THROW_ON_ERROR);
        exit;
    }
}
