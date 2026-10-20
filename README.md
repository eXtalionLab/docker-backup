# docker-backup

Make a backup of your `docker-compose` stack. It's simple, init
[BorgBackup](https://www.borgbackup.org/) repository, dump database, "build" a
command to create a files backup and do it.

---

- [Docker image (recommended)](#docker-image-recommended)
	- [Backup service](#backup-service)
	- [Configuration](#configuration)
	- [Commands and restore](#commands-and-restore)
	- [Borg server](#borg-server)
	- [Migrate from the host script](#migrate-from-the-host-script)
- [Host script (legacy)](#host-script-legacy)
- [Install](#install)
	- [Requirements](#requirements)
- [Backup](#backup)
	- [Init](#init)
	- [Run](#run)
	- [Restore](#restore)
- [Example](#example)

## Docker image (recommended)

The backup runs inside a `backup` service of your compose project. Files and
volumes are mounted into the container (read only) and read by borg directly,
so nothing is copied to the host before the backup. Database dump is streamed
to borg through a named pipe, so it doesn't take disk space either.

### Backup service

Create **compose.backup.yml** next to your **compose.yml** (see
[example/compose.backup.yml](example/compose.backup.yml)):

```yaml
services:
    backup:
        build: https://github.com/eXtalionLab/docker-backup.git
        restart: unless-stopped
        security_opt:
            - label:disable # SELinux hosts only
        environment:
            TZ: Europe/Warsaw
            BACKUP_SCHEDULE: '0 1 * * *'
            BORG_REPO: ssh://borg@backup.example.com:2222/repos/example.com
            DB_TYPE: mariadb
            DB_SERVICE: db
        secrets:
            - borg_passphrase
            - borg_ssh_key
        volumes:
            - /var/run/docker.sock:/var/run/docker.sock:ro
            - backup_borg:/borg
            - ./.env:/backup/.env:ro
            - ./wp-content:/backup/wp-content:ro
            - uploads:/backup/docker_volumes/example_uploads:ro

secrets:
    borg_passphrase:
        file: ./.borg/passphrase
    borg_ssh_key:
        file: ./.borg/ssh_key

volumes:
    backup_borg:
```

and start it with the stack:

```bash
docker compose -f compose.yml -f compose.backup.yml up -d
# or put COMPOSE_FILE=compose.yml:compose.backup.yml into .env
```

Everything mounted into `/backup` is archived with its path relative to
`/backup`, eg. `/backup/wp-content` → `wp-content`. Database dump is stored as
`initdb.d/dump.sql` (`initdb.d/dump.archive` for mongo). Don't mount your
`initdb.d` directory into the backup container.

Keep the `/borg` volume. It holds borg cache (without it every run has to
rebuild the cache from the remote repository), borg security data and ssh
`known_hosts`.

### Configuration

| Env | Default | Description |
|--|--|--|
| `BORG_REPO` | | Required. Local path or `ssh://` url. |
| `BORG_PASSPHRASE` | | Repository passphrase. Prefer `borg_passphrase` secret. |
| `BACKUP_SCHEDULE` | `0 1 * * *` | Cron expression. Spread projects in time when they share one borg server. |
| `TZ` | `UTC` | Timezone used by the schedule. |
| `BACKUP_PATHS` | everything in `/backup` | Space separated paths (relative to `/backup`) to backup. |
| `BACKUP_EXCLUDE` | | New line separated borg exclude patterns, eg. `data/*.log`. |
| `BACKUP_COMPRESSION` | `zstd,5` | Borg compression. |
| `BACKUP_LIST` | `0` | `1` prints every added/modified file. |
| `BACKUP_PRUNE` | `--keep-daily 7 --keep-weekly 4 --keep-monthly 6` | Borg prune options. Empty disables prune. |
| `BACKUP_COMPACT` | `1` | Run `borg compact` after prune (frees disk space on the server). |
| `DB_TYPE` | | `mariadb`, `mysql`, `postgresql`, `mongo`, `custom`. Empty = no dump. |
| `DB_SERVICE` | `db` | Compose service with the database. |
| `DB_DUMP_NAME` | `initdb.d/dump.sql` | Dump path inside the archive. |
| `DB_DUMP_COMMAND` | | Custom dump command, executed by `sh -c` inside db container, writes to stdout. |
| `DB_RESTORE_COMMAND` | | Custom restore command, executed by `sh -c` inside db container, reads stdin. |
| `COMPOSE_PROJECT_NAME` | auto | Compose project, detected from the backup container labels. |
| `CRONITOR_KEY` | | [Cronitor](https://cronitor.io) telemetry key. |
| `CRONITOR_MONITOR` | `docker-backup` | Cronitor monitor key. |

Secrets (files in `/run/secrets`):

- `borg_passphrase` - used when `BORG_PASSPHRASE`/`BORG_PASSCOMMAND` is not set,
- `borg_ssh_key` - private ssh key used when `BORG_RSH` is not set.

Database credentials are read from the db container environment
(`MARIADB_*`/`MYSQL_*`, `POSTGRES_*`, `MONGO_INITDB_ROOT_*`), so they don't have
to be passed to the backup service.

### Commands and restore

```bash
alias backup='docker compose -f compose.yml -f compose.backup.yml exec backup'

backup docker-backup init           # init a new repository (repokey-blake2)
backup docker-backup run            # backup now
backup docker-backup borg list      # any borg command
backup docker-backup restore_db     # restore db dump from the last archive
backup docker-backup restore_db 20250101_010000
```

Restore files with `borg extract` into a writable directory and move them where
they belong (stop the stack first):

```bash
docker compose -f compose.yml -f compose.backup.yml run --rm \
    -v ./restore:/restore -w /restore backup \
    borg extract ::20250101_010000
```

### Borg server

[server/](server/) contains an image with `sshd` + `borg serve` in the same
version as the client. Each client key gets access only to its own repository:

```bash
cd server/
cp authorized_keys.dist authorized_keys
# command="borg serve --restrict-to-repository /repos/example.com",restrict ssh-ed25519 AAAA... example.com
BORG_REPOS_DIR=/media/disk/borg BORG_UID=1001 BORG_GID=1001 docker compose up -d
```

`authorized_keys` is read on every login, no restart needed after a change.

### Migrate from the host script

Old archives keep working (borg 1.4 reads borg 1.2 repositories) and data is
deduplicated against them, as long as paths in `/backup` match the paths from
`.docker-backup` (`filesToBackup` → `/backup/<path>`, volumes →
`/backup/docker_volumes/<volume>`).

1. Check archives authentication with the new client:
   `docker-backup borg upgrade --check-tam` and
   `docker-backup borg list --format '{name} {tam}{NL}'` (all `verified`).
2. If the repository url changes (eg. new server port/path) and you reuse an
   old borg cache, run the first backup with
   `BORG_RELOCATED_REPO_ACCESS_IS_OK=yes`.
3. Remove the project from the host cron.

## Host script (legacy)

### Install

Just clone this repository

```bash
clone git@github.com:eXtalionLab/docker-backup.git .
```

and create a link to the script **docker-backup**

```bash
sudo ln -sr docker-backup/docker-backup /usr/local/bin/docker-backup
```

#### Requirements

**BorgBackup** has to be
[installed](https://borgbackup.readthedocs.io/en/stable/installation.html) on
local machine!

## Backup

### Init

Goto directory with the app that you want to backup and run:

```bash
docker-backup install
```

It will create a new file **.docker-backup.dist**:

```bash
###> config ###
backupDir='backups'
dockerDbServiceName='db'
# Use local image to skip download
dockerImgToBackupVolumes='alpine'
dockerVolumesDir='docker_volumes'
# Allow types: custom, mariadb, mongo, mysql, postgresql
dbType='mysql'
envFile='.env'
###< config ###

###> files/volumes to backup ###
filesToBackup=( \
  "${envFile}" \
)
filesToExclude=()
# Remember to prefix volumes with docker-compose project name
volumesToBackup=()
###< files/volumes to backup ###

###> borg ###
export BORG_REPO='backups/app'
export BORG_PASSPHRASE='Change_me!'
###< borg ###
```

| Config name | Type | Default value | Description |
|--|--|--|--|
| backupDir | `string` | `backups` | It's a directory where backup logs will be stored. |
| dockerDbServiceName | `string` | `db` | It's a name of your `docker-compose` database service. |
| dockerImgToBackupVolumes | `string` | `alpine` | You can put here your image name just to skip download new one. |
| dockerVolumesDir | `string` | `docker_volumes` | Where to temporary store a files from `docker` volumes. |
| dbType | `string` | `mysql` | What kind of database are you using? Allow types are: `custom`, `mariadb`, `mongo`, `mysql`, `postgresql`. |
| envFile | `string` | `.env` | File from where we will read data to connect to database. For `mariadb`/`mysql` we require `MYSQL_DATABASE`, `MYSQL_PASSWORD`, `MYSQL_USER`. For `mongo` we require `MONGO_DATABASE`, `MONGO_USER`, `MONGO_PASSWORD`. For `postgresql` we require `POSTGRES_DB`, `POSTGRES_USER`. |
| filesToBackup | `array` | `[ ${envFile} ]` | Files/directories to backup. |
| filesToExclue | `array` | `[]` | Files/directories to exclude from backup. Eg. `var`, `cache`, `*.log`. |
| volumesToBackup | `array` | `[]` | Volumes to backup. Remember to prefix them with project name |
| BORG_REPO | `string` | `backups/app` | Where to store a backup. It can be a local directory or remote path via `ssh`. |
| BORG_PASSPHRASE | `string` | `Change_me!` | Password to protect a backup. |

File **.docker-backup.dist** is good as a template. See example
[here](https://github.com/eXtalionLab/prestashop_docker/blob/master/.docker-backup.dist)
for Prestashop. After that in specific project you should create a
**.docker-backup** (`cp .docker-backup.dist .docker-backup`) file, "fix" an
admin directory name and `BORG_*` config.

Read more about `BORG_*` config
[here](https://borgbackup.readthedocs.io/en/stable/usage/general.html#environment-variables).

### Run

When you're done with **.docker-backup[.dist]** file(s) just run:

```bash
docker-backup run
```

and wait for a result.

### Restore

How to restore borg's backup you can read
[here](https://borgbackup.readthedocs.io/en/stable/quickstart.html#restoring-a-backup).

You should shutdown `docker-compose` stack before restore a backup:

```bash
docker compose down [-v]
```

The basic option is to run:

```bash
source .docker-backup[.dist]
borg list
# Choose which backup to restore
borg extract ::${backup_to_restore} -p
```

Then start up your stack and wait when database will be ready:

```bash
docker compose up [-d]
```

If you have any volumes, it's time to restore them:

```bash
docker-backup restore_volumes
```

## Example

Goto an [example/](example/README.md) directory to see how to use
`docker-backup`.
