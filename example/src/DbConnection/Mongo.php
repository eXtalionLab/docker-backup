<?php

declare(strict_types=1);

namespace Extalion\DockerBackup\DbConnection;

use Extalion\DockerBackup\DbConfig;
use Extalion\DockerBackup\DbConnection;
use MongoDB\Client as MongoDb;
use MongoDB\Driver\Exception\ConnectionTimeoutException;
use Psr\Log\LoggerInterface;

/**
 * @author Damian <damian@extalion.com>
 */
class Mongo implements DbConnection
{
    private ?MongoDb $mongo = null;

    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly DbConfig $config,
    ) {}

    public function connect(): void
    {
        $attemptsLeft = 15;
        $error = null;

        while ($attemptsLeft--) {
            try {
                $this->mongo = new MongoDb(
                    "mongodb://{$this->config->user()}:{$this->config->password()}@db"
                );
                $this->mongo->listDatabases();

                return;
            } catch (ConnectionTimeoutException $ex) {
                $error = $ex;
                $this->logger->info(
                    "Waiting for database. {$attemptsLeft} attempts left"
                );

                \sleep(1);
            }
        }

        throw new \RuntimeException(
            'Couldn\'t connect to the database: ' . $error->getMessage(),
            0,
            $ex
        );
    }

    public function createSchema(string $migrationsDir): void
    {
    }

    public function findAll(): array
    {
        return $this->mongo
            ->selectCollection($this->config->database(), 'local_files')
            ->find()
            ->toArray()
        ;
    }

    public function saveFile(string $name): array
    {
        $createdAt = new \Datetime();
        $newFile = [
            'name' => $name,
            'created_at' => $createdAt->format('Y-m-d H:i:s'),
        ];

        $this->mongo
            ->selectCollection($this->config->database(), 'local_files')
            ->insertOne($newFile)
        ;

        return $newFile;
    }
}
