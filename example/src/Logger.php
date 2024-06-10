<?php

declare(strict_types=1);

namespace Extalion\DockerBackup;

use Psr\Log\AbstractLogger;

/**
 * @author Damian <damian@extalion.com>
 */
class Logger extends AbstractLogger
{
    public function __construct(
        private readonly string $logFile = '',
    ) {}

    public function log(
        $level,
        string|\Stringable $message,
        array $context = [],
    ): void {
        $logMessage = "[{$level}] {$message}" . \PHP_EOL;
        echo $logMessage;

        if ($context) {
            \print_r($context);
        }

        if ($this->logFile) {
            \file_put_contents($this->logFile, $logMessage, \FILE_APPEND);
        }
    }
}
