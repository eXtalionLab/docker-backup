<?php

declare(strict_types=1);

namespace Extalion\DockerBackup\Consts;

/**
 * @author Damian <damian@extalion.com>
 */
final class DbConnectionDriver
{
    public const MARIADB = 'mysql';
    public const MONGO = 'mongodb';
    public const MYSQL = 'mysql';
    public const POSTGRES = 'pgsql';

    private function __construct() {}
}
