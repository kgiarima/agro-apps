<?php
declare(strict_types=1);

namespace App\Forecast;

use App\Repository\ForecastRepository;
use App\Repository\LocationRepository;

final class ForecastCollector
{
    /** @param list<ForecastProvider> $providers */
    public function __construct(private readonly LocationRepository $locations, private readonly ForecastRepository $forecasts, private readonly array $providers) {}

    /** @return array<string, int> */
    public function collect(?int $locationId = null): array
    {
        $counts = ['succeeded' => 0, 'failed' => 0, 'skipped' => 0];
        foreach ($this->locations->active($locationId) as $location) {
            foreach ($this->providers as $provider) {
                if (!$this->forecasts->providerEnabled($provider->slug())) {
                    $counts['skipped']++;
                    continue;
                }
                try {
                    $result = $provider->fetch($location);
                    $this->forecasts->storeSuccess((int) $location['id'], $provider->slug(), $result);
                    $counts['succeeded']++;
                } catch (\Throwable $exception) {
                    $this->forecasts->storeFailure((int) $location['id'], $provider->slug(), $exception->getMessage());
                    $counts['failed']++;
                    fwrite(STDERR, sprintf("%s for location %d failed: %s\n", $provider->slug(), $location['id'], $exception->getMessage()));
                }
            }
        }
        return $counts;
    }
}
