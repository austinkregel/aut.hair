# Aut.hair (Auth-Air)

Aut.hair is an Oauth2 server, and client for authenticating multiple social accounts (multiple from a single platform, and multiple platforms) to a single user record.

### Features
- Oauth2 server via [Laravel Passport](https://laravel.com/docs/9.x/passport)
- Oauth2 client via [Laravel Socialite](https://laravel.com/docs/9.x/socialite)
- 2FA via [Laravel Jetstream](https://jetstream.laravel.com/features/two-factor-authentication.html)
- Toggleable User registration and login
- User profile management
- User account linking
- User account unlinking
- Teams & Team invites
- Basic team permissions
- OpenID Connect implementation
- LDAP/Active Directory integration (WIP) (via [LdapRecord](https://ldaprecord.com/)
- Install and enable new social providers via our web panel; only recommended while exploring or initially setting up the app.

## Prerequisites

Aut.hair was build with Laravel Sail, and was originally hosted on Laravel Forge. But it can be hosted on any server that meets the following requirements:

- PHP >= 8.2
- Composer
- Node.js >= 14
- NPM
- MySQL >= 8.0 (or MariaDB equivalent) (technically optional if configured for Sqlite)
- Redis (optional, but recommended)
- LDAP/Active Directory server (optional, still a wip though)

## Installation (via sail)

On a *nix system, you can use the following command to install composer dependencies, and setup docker

```bash
bin/sail up -d
yarn
npm run build
```

The bin/sail command will detect if `vendor` is missing, and will install composer dependencies. It will then forward the `up -d` to docker compose to start the stack.

## Installation (via Docker container)

If you don't want to use sail, you can also start the docker container with the following command:

```bash
docker run -d --name aut-hair -p 80:8000 -v /-/aut.hair/.env:/var/www/html/.env ghcr.io/austinkregel/aut.hair:latest php artisan serve --host=0.0.0.0 --port=8000
```

Note: You'll want to create an .env file with the necessary environment variables.