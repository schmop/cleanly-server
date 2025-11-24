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

## Installation

The easiest way of hosting this, is by using [Docker Compose](https://docs.docker.com/compose/).

```
docker-compose up
```
Starts all 3 components and a [nginx](https://nginx.org/en/) for hosting.