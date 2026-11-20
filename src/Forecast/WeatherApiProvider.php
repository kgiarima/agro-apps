<?php
declare(strict_types=1);

namespace App\Forecast;

use App\Http\HttpClient;
use DateTimeImmutable;

final class WeatherApiProvider implements ForecastProvider
{
    public function __construct(private readonly HttpClient $http, private readonly ?string $apiKey) {}
    public function slug(): string { return 'weatherapi'; }
    public function isConfigured(): bool { return $this->apiKey !== null; }

    public function fetch(array $location): ProviderResult
    {
        if (!$this->isConfigured()) {
            throw new \RuntimeException('WEATHERAPI_KEY is not configured.');
        }
        $query = http_build_query(['key' => $this->apiKey, 'q' => $location['latitude'] . ',' . $location['longitude'], 'days' => 14, 'aqi' => 'no', 'alerts' => 'no']);
        $url = "https://api.weatherapi.com/v1/forecast.json?{$query}";
        $payload = $this->http->get($url);
        $timezone = $payload['location']['tz_id'] ?? $location['timezone'];
        $values = [];
        foreach (($payload['forecast']['forecastday'] ?? []) as $day) {
            $values[] = $this->value('daily', $day['date'] . ' 00:00:00', $timezone, $day['day']['avgtemp_c'] ?? null, $day['day']['totalprecip_mm'] ?? null);
            foreach (($day['hour'] ?? []) as $hour) {
                $values[] = $this->value('hourly', $hour['time'], $timezone, $hour['temp_c'] ?? null, $hour['precip_mm'] ?? null);
            }
        }
        $safeUrl = preg_replace('/([?&]key=)[^&]+/', '$1REDACTED', $url) ?? $url;
        return new ProviderResult($safeUrl, $values, $payload);
    }

    private function value(string $resolution, string $time, string $timezone, mixed $temperature, mixed $precipitation): array
    {
        return ['resolution' => $resolution, 'valid_at' => (new DateTimeImmutable($time, new \DateTimeZone($timezone)))->format(DATE_ATOM), 'temperature_c' => $temperature, 'precipitation_mm' => $precipitation];
    }
}
