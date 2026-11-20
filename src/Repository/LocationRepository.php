<?php
declare(strict_types=1);

namespace App\Repository;

use PDO;

final class LocationRepository
{
    public function __construct(private readonly PDO $db) {}

    /** @return list<array<string, mixed>> */
    public function all(): array
    {
        return $this->db->query('SELECT id, name, latitude, longitude, timezone, active, created_at FROM locations ORDER BY id')->fetchAll();
    }

    /** @return list<array<string, mixed>> */
    public function active(?int $id = null): array
    {
        $sql = 'SELECT id, name, latitude, longitude, timezone FROM locations WHERE active = TRUE';
        $parameters = [];
        if ($id !== null) { $sql .= ' AND id = :id'; $parameters['id'] = $id; }
        $statement = $this->db->prepare($sql . ' ORDER BY id');
        $statement->execute($parameters);
        return $statement->fetchAll();
    }

    /** @return array<string, mixed> */
    public function create(array $input): array
    {
        $statement = $this->db->prepare('INSERT INTO locations (name, latitude, longitude, timezone) VALUES (:name, :latitude, :longitude, :timezone) RETURNING id, name, latitude, longitude, timezone, active, created_at');
        $statement->execute($input);
        return $statement->fetch();
    }
}
