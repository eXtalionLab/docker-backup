<?php

require __DIR__ . '/vendor/autoload.php';

use Extalion\DockerBackup\DbConfig;
use Extalion\DockerBackup\DbConnectionFactory;
use Extalion\DockerBackup\Logger;

$logger = new Logger('/app_volume/app.log');
$dbConfig = new DbConfig($logger);
$dbConnectionFactory = new DbConnectionFactory($logger);

$logger->info('App start!');

try {
    $dbConfig->loadConfigFromEnvs();
    $dbConnection = $dbConnectionFactory->create($dbConfig);
    $dbConnection->connect();
    $dbConnection->createSchema(__DIR__ . '/migrations');
    $dbFiles = $dbConnection->findAll();

    $logger->info($dbFiles ? 'Files in database:' : 'No files in database');

    foreach ($dbFiles as $file) {
        $logger->info("- {$file['name']} ({$file['created_at']}),");
    }

    $logger->info('Waiting for new files...');
    $logger->info('Drag&Drop or create new file in "app_bind_data" directory');

    $appStop = false;

    \pcntl_async_signals(true);
    \pcntl_signal(\SIGTERM, function (int $signal) use (&$appStop): void {
        $appStop = true;
    });

    $directory = new \DirectoryIterator('/app_bind');

    while (!$appStop) {
        \sleep(1);

        foreach ($directory as $file) {
            if (
                $file->isDot()
                || is_file_in_db($dbFiles, $file->getFilename())
            ) {
                continue;
            }

            $logger->info("New file found \"{$file->getFilename()}\"");
            $dbFiles[] = $dbConnection->saveFile($file->getFilename());

            if ($file->isFile()) {
                \copy(
                    $file->getPathname(),
                    '/app_volume/' . $file->getFilename()
                );
            }
        }
    }
} catch (\Exception $ex) {
    while ($ex) {
        $logger->error('---');
        $logger->error($ex->getMessage());
        $logger->error('');
        $logger->error("Trace:\n" . $ex->getTraceAsString());
        $logger->error('---');

        $ex = $ex->getPrevious();
    }

    exit(1);
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
