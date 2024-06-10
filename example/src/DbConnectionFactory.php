<?php

declare(strict_types=1);

namespace Extalion\DockerBackup;

use Extalion\DockerBackup\Consts\DbConnectionDriver;
use Extalion\DockerBackup\DbConfig;
use Extalion\DockerBackup\DbConnection;
use Psr\Log\LoggerInterface;

/**
 * @author Damian <damian@extalion.com>
 */
class DbConnectionFactory
{
    public function __construct(
        private readonly LoggerInterface $logger,
    ) {}

    public function create(DbConfig $config): DbConnection
    {
        if ($config->driver() === DbConnectionDriver::MONGO) {
            return new DbConnection\Mongo($this->logger, $config);
        } else {
            return new DbConnection\Pdo($this->logger, $config);
        }
    }
}
