<?php
declare(strict_types=1);

namespace App\Database;

use PDO;

final class Migrator
{
    public function __construct(private readonly PDO $db) {}

    public function migrate(): void
    {
        $files = glob(dirname(__DIR__, 2) . '/migrations/*.sql') ?: [];
        sort($files);
        foreach ($files as $file) {
            $version = basename($file);
            $exists = $this->db->prepare('SELECT 1 FROM schema_migrations WHERE version = :version');
            try {
                $exists->execute(['version' => $version]);
            } catch (\PDOException) {
                $this->db->exec(file_get_contents($file));
            }
            if ($exists->fetchColumn()) {
                continue;
            }
            $this->db->beginTransaction();
            try {
                $this->db->exec(file_get_contents($file));
                $insert = $this->db->prepare('INSERT INTO schema_migrations (version) VALUES (:version)');
                $insert->execute(['version' => $version]);
                $this->seed();
                $this->db->commit();
            } catch (\Throwable $exception) {
                $this->db->rollBack();
                throw $exception;
            }
        }
    }

    private function seed(): void
    {
        $providers = require dirname(__DIR__, 2) . '/config/providers.php';
        $statement = $this->db->prepare(
            'INSERT INTO weather_providers (slug, name, enabled) VALUES (:slug, :name, :enabled) ON CONFLICT (slug) DO NOTHING'
        );
        foreach ($providers as $slug => $provider) {
            $statement->execute(['slug' => $slug, 'name' => $provider['name'], 'enabled' => $provider['enabled']]);
        }
        $location = $this->db->prepare(
            'INSERT INTO locations (name, latitude, longitude, timezone) VALUES (:name, :latitude, :longitude, :timezone) ON CONFLICT (latitude, longitude) DO NOTHING'
        );
        $location->execute([
            'name' => env('SEED_LOCATION_NAME', 'Demo field'),
            'latitude' => env('SEED_LOCATION_LATITUDE', '37.9838'),
            'longitude' => env('SEED_LOCATION_LONGITUDE', '23.7275'),
            'timezone' => env('SEED_LOCATION_TIMEZONE', 'Europe/Athens'),
        ]);
    }
}
