<?php

declare(strict_types=1);

namespace Rahdana\Support;

use Paya\Core\Database;

/** Lazy connection: /health remains available without a database. */
final class DatabaseProvider
{
    private ?Database $instance = null;

    public function get(): Database
    {
        return $this->instance ??= new Database(require __DIR__ . '/../../config/database.php');
    }
}
