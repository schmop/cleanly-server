# cleanly-server

This is the backend for the App [**Cleanly**](https://github.com/schmop/cleanly).

Cleanly is a tool to organize recurring tasks in households, assign them, track the status and get them done properly!


## Setup

### Requirements

You need php8.0 or higher installed with following extensions:
```
sudo apt install php-xml php-curl php-common php-pgsql
```
Also you need postgresql
```
sudo apt install postgresql
```
With a Database called `cleanly` and a User with privileges:
```
CREATE DATABASE cleanly;
GRANT ALL PRIVILEGES ON cleanly TO username;
```

Also you need composer installed:
https://getcomposer.org/download/


### Installation

Create a copy of the `.env` file and call it `.env.local`.
* Change the environment to prod
* Replace the APP_SECRET and JWT_PASSPHRASE with own secrets
* Update the DATABASE_URL to fit your credentials

Install dependencies:
```
composer install
```
Create a JWT-Keypair:
```
./bin/console lexik:jwt:generate-keypair
```
And create the tables needed for doctine:
```
./bin/console doctrine:migrations:migrate
```
Finally let your webserver serve the `public` folder, and you're good to go!