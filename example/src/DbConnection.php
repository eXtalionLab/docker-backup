<?php

declare(strict_types=1);

namespace Extalion\DockerBackup;

/**
 * @author Damian <damian@extalion.com>
 */
interface DbConnection
{
    public function connect(): void;
    public function createSchema(string $migrationsDir): void;
    public function findAll(): array;
    public function saveFile(string $fileName): array;
}
