<?php

declare(strict_types=1);

namespace Extalion\DockerBackup;

use Psr\Log\LoggerInterface;

/**
 * @author Damian <damian@extalion.com>
 */
class DbConfig
{
    public const MARIADB_TYPE = 'mariadb';

    private array $config = [];

    public function __construct(
        private readonly LoggerInterface $logger,
    ) {}

    public function loadConfigFromEnvs(): void
    {
        $requiredEnvs = [
            'DB_DATABASE' => 'database',
            'DB_USER' => 'user',
            'DB_PASSWORD' => 'password',
            'DB_DRIVER' => 'driver',
        ];
        $result = [];

        foreach ($requiredEnvs as $requiredEnv => $key) {
            $result[$key] = \getenv($requiredEnv);

            if ($result[$key] === false) {
                $this->logger->error('DB config is missing...');
                $this->logger->error(
                    'Envs ' . \implode(', ', $requiredEnvs) . ' are required'
                );

                exit(1);
            } elseif (
                $requiredEnv === 'DB_DRIVER'
                && $result[$key] === self::MARIADB_TYPE
            ) {
                $result[$key] = 'mysql';
            }
        }

        $this->config = $result;
    }

    public function get(string $key)
    {
        return $this->config[$key] ?? $this->throwException();
    }

    public function getAll(): array
    {
        return $this->config;
    }

    public function database(): string
    {
        return $this->get('database');
    }

    public function driver(): string
    {
        return $this->get('driver');
    }

    public function password(): string
    {
        return $this->get('password');
    }

    public function user(): string
    {
        return $this->get('user');
    }

    private function throwException(): void
    {
        throw new \RuntimeException('Run loadConfigFromEnvs first!');
    }
}
