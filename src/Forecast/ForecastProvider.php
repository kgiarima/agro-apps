<?php
declare(strict_types=1);

namespace App\Forecast;

interface ForecastProvider
{
    public function slug(): string;
    public function isConfigured(): bool;

    /** @param array<string, mixed> $location @return ProviderResult */
    public function fetch(array $location): ProviderResult;
}
