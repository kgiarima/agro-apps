<?php
declare(strict_types=1);

namespace App\Database;

use PDO;

final class Connection
{
    public static function create(): PDO
    {
        $dsn = sprintf(
            'pgsql:host=%s;port=%s;dbname=%s',
            env('DB_HOST', 'db'),
            env('DB_PORT', '5432'),
            env('DB_NAME', 'weather')
        );
        return new PDO($dsn, env('DB_USER', 'weather'), env('DB_PASSWORD', 'weather'), [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
    }
}
