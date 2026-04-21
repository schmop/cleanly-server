# cleanly-server

This is the backend for the App [**Cleanly**](https://github.com/schmop/cleanly).

Cleanly is an organizational tool for shared spaces.
Create recurring tasks in households, assign them, track the status and get them done properly!

## Components

Cleanly's backend consists of
* a PostgreSQL Database,
* a Symfony Application and
* a Node-JS SSE Hub.

## Configuration

Create a copy of the `.env` file under the name `.env.local`.
Change the following Variables:
* `APP_ENV` to `dev` when developing, `prod` for production
* `APP_SECRET`, roll a new symfony secret
* `JWT_PASSPHRASE`, roll a new jwt pass
* `SSE_PUBLISH_SECRET`, roll a new hub secret
* `DB_PASS` the postgresql password and accordingly the DSN under `DATABASE_URL`
* `MAILER_DSN`, change to your mail-server for registration-mails
* `SECRETS_HOST_PATH`, host path to the directory containing your Firebase
  service-account JSON (and other credentials). Mounted read-only into the
  Symfony container at `/var/www/secrets`. Defaults to `./secrets`.
* `FIREBASE_CREDENTIALS`, container path to the Firebase service-account JSON,
  e.g. `/var/www/secrets/cleanly-ad0f1-864cfd0c1c39.json`. The filename should
  match the file you placed in `SECRETS_HOST_PATH`.

## Installation

The easiest way of hosting this, is by using [Docker Compose](https://docs.docker.com/compose/).

```
make up
```
Starts all 3 components and a [nginx](https://nginx.org/en/) for hosting.

The Makefile wraps `docker compose` so that both `.env` and (when present)
`.env.local` are passed via `--env-file`. This is required for variables like
`SECRETS_HOST_PATH` that are referenced from `docker-compose.yml` itself —
Compose does not consult `env_file:` directives for that kind of interpolation.

Other targets: `make down`, `make restart`, `make logs`, `make ps`.