<?php
declare(strict_types=1);

namespace App\Forecast;

final class ProviderResult
{
    /** @param list<array<string, mixed>> $values @param array<string, mixed> $payload */
    public function __construct(
        public readonly string $requestUrl,
        public readonly array $values,
        public readonly array $payload,
    ) {}
}
