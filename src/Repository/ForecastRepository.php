<?php
declare(strict_types=1);

namespace App\Repository;

use App\Forecast\ProviderResult;
use PDO;

final class ForecastRepository
{
    public function __construct(private readonly PDO $db) {}

    public function providerEnabled(string $slug): bool
    {
        $statement = $this->db->prepare('SELECT enabled FROM weather_providers WHERE slug = :slug');
        $statement->execute(['slug' => $slug]);
        return (bool) $statement->fetchColumn();
    }

    public function storeSuccess(int $locationId, string $providerSlug, ProviderResult $result): void
    {
        $this->db->beginTransaction();
        try {
            $run = $this->db->prepare('INSERT INTO forecast_runs (location_id, provider_id, status, request_url, response_payload) VALUES (:location_id, (SELECT id FROM weather_providers WHERE slug = :slug), :status, :request_url, CAST(:payload AS JSONB)) RETURNING id');
            $run->execute(['location_id' => $locationId, 'slug' => $providerSlug, 'status' => 'succeeded', 'request_url' => $result->requestUrl, 'payload' => json_encode($result->payload, JSON_THROW_ON_ERROR)]);
            $runId = (int) $run->fetchColumn();
            $value = $this->db->prepare('INSERT INTO forecast_values (forecast_run_id, resolution, valid_at, temperature_c, precipitation_mm) VALUES (:run_id, :resolution, :valid_at, :temperature_c, :precipitation_mm)');
            foreach ($result->values as $point) {
                $value->execute(['run_id' => $runId, ...$point]);
            }
            $this->db->commit();
        } catch (\Throwable $exception) {
            $this->db->rollBack();
            throw $exception;
        }
    }

    public function storeFailure(int $locationId, string $providerSlug, string $error): void
    {
        $statement = $this->db->prepare('INSERT INTO forecast_runs (location_id, provider_id, status, error_message) VALUES (:location_id, (SELECT id FROM weather_providers WHERE slug = :slug), :status, :error)');
        $statement->execute(['location_id' => $locationId, 'slug' => $providerSlug, 'status' => 'failed', 'error' => substr($error, 0, 2000)]);
    }

    /** @return list<array<string, mixed>> */
    public function values(array $filters): array
    {
        $sql = "SELECT fv.resolution, fv.valid_at, fv.temperature_c, fv.precipitation_mm, fr.fetched_at, wp.slug AS provider, l.id AS location_id\n            FROM forecast_values fv JOIN forecast_runs fr ON fr.id = fv.forecast_run_id JOIN weather_providers wp ON wp.id = fr.provider_id JOIN locations l ON l.id = fr.location_id\n            WHERE fr.status = 'succeeded' AND l.id = :location_id";
        $parameters = ['location_id' => $filters['location_id']];
        foreach (['provider' => 'wp.slug', 'resolution' => 'fv.resolution'] as $key => $column) {
            if (!empty($filters[$key])) { $sql .= " AND {$column} = :{$key}"; $parameters[$key] = $filters[$key]; }
        }
        foreach (['from' => '>=', 'to' => '<='] as $key => $operator) {
            if (!empty($filters[$key])) { $sql .= " AND fv.valid_at {$operator} :{$key}"; $parameters[$key] = $filters[$key]; }
        }
        if (($filters['latest'] ?? 'true') !== 'false') {
            $sql .= ' AND fr.fetched_at = (SELECT MAX(fr2.fetched_at) FROM forecast_runs fr2 WHERE fr2.location_id = fr.location_id AND fr2.provider_id = fr.provider_id AND fr2.status = \'succeeded\')';
        }
        $sql .= ' ORDER BY fv.valid_at, wp.slug LIMIT :limit';
        $parameters['limit'] = $filters['limit'] ?? 500;
        $statement = $this->db->prepare($sql);
        foreach ($parameters as $key => $value) {
            $statement->bindValue(':' . $key, $value, $key === 'limit' ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $statement->execute();
        return $statement->fetchAll();
    }

    /** @return list<array<string, mixed>> */
    public function runs(?int $locationId = null): array
    {
        $sql = 'SELECT fr.id, fr.fetched_at, fr.status, fr.error_message, wp.slug AS provider, l.id AS location_id FROM forecast_runs fr JOIN weather_providers wp ON wp.id = fr.provider_id JOIN locations l ON l.id = fr.location_id';
        if ($locationId !== null) { $sql .= ' WHERE l.id = :location_id'; }
        $statement = $this->db->prepare($sql . ' ORDER BY fr.fetched_at DESC LIMIT 100');
        $statement->execute($locationId === null ? [] : ['location_id' => $locationId]);
        return $statement->fetchAll();
    }
}
