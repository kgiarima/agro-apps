<?php
declare(strict_types=1);

use App\Database\Connection;
use App\Forecast\ForecastCollector;
use App\Forecast\OpenMeteoProvider;
use App\Forecast\WeatherApiProvider;
use App\Http\HttpClient;
use App\Repository\ForecastRepository;
use App\Repository\LocationRepository;

spl_autoload_register(static function (string $class): void {
    $prefix = 'App\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }
    $file = __DIR__ . '/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
    if (is_file($file)) {
        require $file;
    }
});

function env(string $key, ?string $default = null): ?string
{
    $value = getenv($key);
    return $value === false || $value === '' ? $default : $value;
}

function app(): array
{
    static $container;
    if ($container !== null) {
        return $container;
    }

    $pdo = Connection::create();
    $locations = new LocationRepository($pdo);
    $forecasts = new ForecastRepository($pdo);
    $http = new HttpClient();
    $providers = [
        new OpenMeteoProvider($http),
        new WeatherApiProvider($http, env('WEATHERAPI_KEY')),
    ];

    return $container = [
        'db' => $pdo,
        'locations' => $locations,
        'forecasts' => $forecasts,
        'collector' => new ForecastCollector($locations, $forecasts, $providers),
    ];
}
