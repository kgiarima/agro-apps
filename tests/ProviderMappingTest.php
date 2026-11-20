<?php
declare(strict_types=1);

require dirname(__DIR__) . '/src/bootstrap.php';

use App\Forecast\OpenMeteoProvider;
use App\Forecast\WeatherApiProvider;
use App\Http\HttpClient;

final class FakeHttpClient extends HttpClient
{
    /** @param array<string, mixed> $payload */
    public function __construct(private readonly array $payload) {}
    public function get(string $url): array { return $this->payload; }
}

function assertSameValue(mixed $expected, mixed $actual, string $message): void { if ($expected !== $actual) { throw new RuntimeException("{$message}: expected " . var_export($expected, true) . ', got ' . var_export($actual, true)); } }
assertSameValue('open-meteo', (new OpenMeteoProvider(new HttpClient()))->slug(), 'Open-Meteo slug');
assertSameValue(false, (new WeatherApiProvider(new HttpClient(), null))->isConfigured(), 'WeatherAPI key validation');
assertSameValue(true, (new WeatherApiProvider(new HttpClient(), 'key'))->isConfigured(), 'WeatherAPI configured');

$location = ['latitude' => '37.9838', 'longitude' => '23.7275', 'timezone' => 'Europe/Athens'];
$openMeteo = new OpenMeteoProvider(new FakeHttpClient(['hourly' => ['time' => ['2026-11-20T01:00'], 'temperature_2m' => [8.5], 'precipitation' => [0.3]], 'daily' => ['time' => ['2026-11-20'], 'temperature_2m_mean' => [10.2], 'precipitation_sum' => [1.4]]]));
$openMeteoResult = $openMeteo->fetch($location);
assertSameValue(2, count($openMeteoResult->values), 'Open-Meteo values count');
assertSameValue('hourly', $openMeteoResult->values[0]['resolution'], 'Open-Meteo hourly resolution');
assertSameValue(8.5, $openMeteoResult->values[0]['temperature_c'], 'Open-Meteo hourly temperature');

$weatherApi = new WeatherApiProvider(new FakeHttpClient(['location' => ['tz_id' => 'Europe/Athens'], 'forecast' => ['forecastday' => [['date' => '2026-11-20', 'day' => ['avgtemp_c' => 10.2, 'totalprecip_mm' => 1.4], 'hour' => [['time' => '2026-11-20 01:00', 'temp_c' => 8.5, 'precip_mm' => 0.3]]]]]]), 'secret-key');
$weatherApiResult = $weatherApi->fetch($location);
assertSameValue(2, count($weatherApiResult->values), 'WeatherAPI values count');
assertSameValue('daily', $weatherApiResult->values[0]['resolution'], 'WeatherAPI daily resolution');
assertSameValue(1.4, $weatherApiResult->values[0]['precipitation_mm'], 'WeatherAPI daily precipitation');
assertSameValue(false, str_contains($weatherApiResult->requestUrl, 'secret-key'), 'WeatherAPI URL redaction');
echo "Provider mapping checks passed.\n";
