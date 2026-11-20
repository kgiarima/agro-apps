<?php
declare(strict_types=1);

namespace App\Forecast;

use App\Http\HttpClient;
use DateTimeImmutable;

final class OpenMeteoProvider implements ForecastProvider
{
    public function __construct(private readonly HttpClient $http) {}
    public function slug(): string { return 'open-meteo'; }
    public function isConfigured(): bool { return true; }

    public function fetch(array $location): ProviderResult
    {
        $query = http_build_query([
            'latitude' => $location['latitude'], 'longitude' => $location['longitude'], 'timezone' => $location['timezone'],
            'hourly' => 'temperature_2m,precipitation', 'daily' => 'temperature_2m_mean,precipitation_sum', 'forecast_days' => 3,
        ]);
        $url = "https://api.open-meteo.com/v1/forecast?{$query}";
        $payload = $this->http->get($url);
        $values = [];
        foreach (($payload['hourly']['time'] ?? []) as $index => $time) {
            $values[] = $this->value('hourly', $time, $location['timezone'], $payload['hourly']['temperature_2m'][$index] ?? null, $payload['hourly']['precipitation'][$index] ?? null);
        }
        foreach (($payload['daily']['time'] ?? []) as $index => $time) {
            $values[] = $this->value('daily', $time . ' 00:00:00', $location['timezone'], $payload['daily']['temperature_2m_mean'][$index] ?? null, $payload['daily']['precipitation_sum'][$index] ?? null);
        }
        return new ProviderResult($url, $values, $payload);
    }

    private function value(string $resolution, string $time, string $timezone, mixed $temperature, mixed $precipitation): array
    {
        return ['resolution' => $resolution, 'valid_at' => (new DateTimeImmutable($time, new \DateTimeZone($timezone)))->format(DATE_ATOM), 'temperature_c' => $temperature, 'precipitation_mm' => $precipitation];
    }
}
