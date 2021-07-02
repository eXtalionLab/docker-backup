<?php

require_once(__DIR__ . '/helpers.php');

log_message('App start!');

$dbConfig = get_db_config();
$pdo = create_pdo($dbConfig);
run_migration($pdo, __DIR__ . "/migration.{$dbConfig['driver']}.sql");

$dbFiles = $pdo->query('SELECT * FROM local_files')->fetchAll();

if (!$dbFiles) {
    log_message('No files in database');
} else {
    log_message('Files in database:');
}

foreach ($dbFiles as $file) {
    log_message("- {$file['name']} ({$file['created_at']}),");
}

log_message('Waiting for new files...');
log_message('Drop/create new file in "app_bind_data" directory');

$appStop = false;

\pcntl_async_signals(true);
\pcntl_signal(
    \SIGTERM,
    function (int $signal) use (&$appStop) {
        $appStop = true;
    }
);

$localFiles = [];
$directory = new \DirectoryIterator('/app_bind');

while (!$appStop) {
    \sleep(1);

    foreach ($directory as $file) {
        if ($file->isDot() || is_file_in_db($dbFiles, $file->getFilename())) {
            continue;
        }

        log_message("New file found \"{$file->getFilename()}\"");
        $dbFiles[] = save_file_to_db($pdo, $file->getFilename());

        if ($file->isFile()) {
            \copy($file->getPathname(), '/app_volume/' . $file->getFilename());
        }
    }
}
