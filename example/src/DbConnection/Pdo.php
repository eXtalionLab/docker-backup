<?php

declare(strict_types=1);

namespace Extalion\DockerBackup\DbConnection;

use Extalion\DockerBackup\DbConfig;
use Extalion\DockerBackup\DbConnection;
use Psr\Log\LoggerInterface;

/**
 * @author Damian <damian@extalion.com>
 */
class Pdo implements DbConnection
{
    private ?\PDO $pdo = null;

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
                $this->pdo = new \PDO(
                    "{$this->config->driver()}:dbname={$this->config->database()};host=db",
                    $this->config->user(),
                    $this->config->password()
                );

                return;
            } catch (\PDOException $ex) {
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
        try {
            $this->pdo->query('SELECT * FROM local_files');
        } catch (\PDOException $ex) {
            $migration = $migrationsDir . '/' . $this->config->driver() . '.sql';

            if (!\file_exists($migration)) {
                throw new \RuntimeException(
                    'Migration file "' . $migration . '" not found.'
                );
            }

            $this->logger->info('Run migration');
            $this->pdo->query(\file_get_contents($migration));
        }
    }

    public function findAll(): array
    {
        return $this->pdo->query('SELECT * FROM local_files')->fetchAll();
    }

    public function saveFile(string $name): array
    {
        $createdAt = new \Datetime();
        $this->pdo->query(
            <<<SQL
                INSERT INTO local_files (name, created_at)
                VALUES ('{$name}', '{$createdAt->format('Y-m-d H:i:s')}')
            SQL
        );

        return [
            'name' => $name,
            'created_at' => $createdAt->format('Y-m-d H:i:s'),
        ];
    }
}
