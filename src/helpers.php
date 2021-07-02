<?php

function get_db_config(): array
{
    $envs = \getenv();

    $dbDatabase = $envs['DB_DATABASE'] ?? null;
    $dbUser = $envs['DB_USER'] ?? null;
    $dbPassword = $envs['DB_PASSWORD'] ?? null;
    $dbType = $envs['DB_TYPE'] ?? null;

    if (!$dbDatabase || !$dbUser || !$dbPassword || !$dbType) {
        log_message('DB config is missing...');
        log_message('Env DB_DATABASE, DB_USER. DB_PASSWORD, DB_TYPE are required');

        exit(1);
    }

    return [
        'db' => $dbDatabase,
        'user' => $dbUser,
        'password' => $dbPassword,
        'driver' => $dbType,
    ];
}

function create_pdo(array $dbConfig): ?\PDO
{
    $attemptsLeft = 15;

    while ($attemptsLeft--) {
        try {
            return new \PDO(
                "{$dbConfig['driver']}:dbname={$dbConfig['db']};host=db",
                $dbConfig['user'],
                $dbConfig['password']
            );
        } catch (\PDOException $ex) {
            log_message("Waiting for database. {$attemptsLeft} attempts left");
            \sleep(1);
        }
    }

    log_message('Couldn\'t connect to the database');

    exit(1);
}

function run_migration($pdo, $migration): void
{
    try {
        $pdo->query('SELECT * FROM local_files');
    } catch (\PDOException $ex) {
        log_message('Run migration');
        $migration = \file_get_contents($migration);
        $pdo->query($migration);
    }
}

function is_file_in_db(array $dbFiles, $file): bool
{
    foreach ($dbFiles as $dbFile) {
        if ($dbFile['name'] === $file) {
            return true;
        }
    }

    return false;
}

function save_file_to_db(\PDO $pdo, string $name): array
{
    $createdAt = new \Datetime();
    $pdo->query(
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

function log_message(string $message): void
{
    echo "$message\n";
    \file_put_contents('/app_volume/app.log', "$message\n", \FILE_APPEND);
}
