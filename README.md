# docker-backup

Make a backup of your `docker-compose` stack. It's simple, init
[BorgBackup](https://www.borgbackup.org/) repository, dump database, "build" a
command to create a files backup and do it.

---

- [Install](#install)
	- [Requirements](#requirements)
- [Backup](#backup)
	- [Init](#init)
	- [Run](#run)
	- [Restore](#restore)
- [Example](#example)

## Install

Just clone this repository

```bash
clone git@github.com:eXtalionLab/docker-backup.git .
```

and create a link to script **bin/backup**

```bash
sudo ln -sr docker-backup/bin/backup /usr/local/bin/docker-backup
```

### Requirements

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
# Allow types: custom, mysql, postgresql
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
| dbType | `string` | `mysql` | What kind of database are you using? Allow types are: `custom`, `mysql`, `postgresql`. |
| envFile | `string` | `.env` | File from where we will read data to connect to database. For `mysql` we require `MYSQL_DATABASE`, `MYSQL_PASSWORD`, `MYSQL_USER`. For `postgresql` we require `POSTGRES_DB`, `POSTGRES_USER`.
| filesToBackup | `array` | `[ ${envFile} ]` | Files/directories to backup.
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

You should shutdown`docker-compose` stack before restore a backup:

```bash
docker-compose down [-v]
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
docker-compose up [-d]
```

If you have any volumes, it's time to restore them:

```bash
docker-backup restore_volumes
```

## Example

Run an example app:

```bash
docker-compose up
```

and drop or create any file/directory into **app_bind_data/** directory. Script
will monitor this directory and each new file will copies into **app_volume_data**
directory (volumen) and save that information into database.

In **.docker-backup.dist** you can see that we want to backup:

- `${envFile}` (when we restore that backup we will have correct configuration
to quick run an app),
- whole **app_bind_data/** directory (our apps data),
- volumen **app_volume_data** (our app data too. It's prefixed with
`docker-compose` "project name", by default it's a directory name).

Also we want to exclude some files (***.log**) from our volume directory.

Run:

```bash
docker-backup run
```

You should got a new backup in **backups/app/** directory.

Let's restore it! You can shutdown an app and remove any files:

```bash
docker-compose down -v
rm app_bind_data/ -rf
```

Search and restore a backup:

```bash
source .docker-backup.dist
borg list
borg extract ::${backup_to_restore} -p
```

Start an app and when database is ready restore a volumes:

```bash
docker-compose up
# In new tab
docker-backup restore_volumes
```
