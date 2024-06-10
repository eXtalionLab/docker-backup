
# Example App

Run an example app and see how to use a `docker-backup`:

```bash
docker compose up
```

Create files into **app_bind_data/**:

```bash
touch app_bind_data/foo
touch app_bind_data/bar
```

In docker compose logs you should see messages:

```
[info] New file found "foo"
[info] New file found "bar"
```

You can drag&drop or create new file/directory into **app_bind_data/**
directory. Script will monitor this directory and each new file will copies into
**app_volume_data** directory (volumen) and save that information into database.

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

You should got a new backup in **backups/app/** directory. Create one more file:

```bash
touch app_bind_data/boo
```

Restart docker compose `[CTRL + c]` and run `docker compose up`. You should see
in dokcer compse logs messagse:

```
[info] Files in database:
[info] - foo (2024-06-10 18:40:48),
[info] - bar (2024-06-10 18:40:54),
[info] - boo (2024-06-10 18:41:12),
```

Now let's restore our backup! You can shutdown an app and remove all files:

```bash
docker compose down -v
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
docker compose up
# In new tab
docker-backup restore_volumes
```

In dokcer compose logs you should see:

```
[info] Files in database:
[info] - foo (2024-06-10 18:40:48),
[info] - bar (2024-06-10 18:40:54),
```

**Note!** When you're running a `mongo` database, first restore a database and
than run whole app:

```bash
docker compose up db
# In new tab
docker-backup restore_mongodb
docker compose stop db
```
