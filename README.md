# Aut.hair (Auth-Air)

Aut.hair is an OAuth2 server and client for authenticating multiple social accounts (multiple from a single platform, and multiple platforms) to a single user record.

## Features

### Authentication & Authorization
- **OAuth2 Server** via [Laravel Passport](https://laravel.com/docs/10.x/passport)
- **OAuth2 Client** via [Laravel Socialite](https://laravel.com/docs/10.x/socialite)
- **OpenID Connect Core 1.0** compliant implementation
  - Authorization Code flow with PKCE (S256 required)
  - Refresh token support
  - ID token signing with RS256
  - UserInfo endpoint
  - Token revocation (RFC 7009)
  - End session/logout support
  - Discovery endpoint (`/.well-known/openid-configuration`)
- **2FA** via [Laravel Jetstream](https://jetstream.laravel.com/features/two-factor-authentication.html)
- Toggleable user registration and login
- LDAP/Active Directory integration (WIP) via [LdapRecord](https://ldaprecord.com/)

### User Management
- User profile management
- User account linking (multiple social accounts per user)
- User account unlinking
- Teams & team invites
- Basic team permissions

### Additional Features
- Install and enable new social providers via web panel (recommended for exploration/initial setup only)
- Queue management via [Laravel Horizon](https://laravel.com/docs/10.x/horizon)
- WebSocket support via [Laravel Reverb](https://laravel.com/docs/10.x/reverb)
- High-performance application server support via [Laravel Octane](https://laravel.com/docs/10.x/octane)

## Tech Stack

- **Backend**: Laravel 10.x, PHP 8.1+
- **Frontend**: Vue 3, Inertia.js, Vite
- **Styling**: Tailwind CSS
- **Queue**: Laravel Horizon with Redis
- **WebSockets**: Laravel Reverb
- **Application Server**: Laravel Octane (RoadRunner)

## Prerequisites

Aut.hair is built with Laravel Sail and can be hosted on any server that meets the following requirements:

- PHP ^8.1
- Composer
- Node.js >= 16 (recommended: 18+)
- NPM
- MySQL >= 8.0 (or MariaDB equivalent; technically optional if configured for SQLite)
- Redis (optional, but recommended for queues and caching)
- LDAP/Active Directory server (optional, still WIP)

## Installation

### Via Laravel Sail (Recommended)

On a *nix system, you can use the following commands to install dependencies and set up Docker:

```bash
# Start the Docker containers (will auto-install composer dependencies if vendor is missing)
bin/sail up -d

# Install Node.js dependencies
npm install

# Build frontend assets
npm run build
```

The `bin/sail` command will detect if `vendor` is missing and will install composer dependencies. It will then forward the `up -d` command to docker compose to start the stack.

**Note**: Make sure to copy `.env.example` to `.env` and configure your environment variables before starting.

### Via Docker Container

If you don't want to use Sail, you can also start the Docker container with the following command:

```bash
docker run -d --name aut-hair -p 80:8000 -v /-/aut.hair/.env:/var/www/html/.env ghcr.io/austinkregel/aut.hair:latest php artisan serve --host=0.0.0.0 --port=8000
```

**Note**: You'll need to create an `.env` file with the necessary environment variables.

### Post-Installation

After installation, you may need to run:

```bash
# Generate application key (if not already set)
bin/sail artisan key:generate

# Run database migrations
bin/sail artisan migrate

# Install Passport (OAuth2 server)
bin/sail artisan passport:install
```

## Development

### Running the Development Server

```bash
# Start all services
bin/sail up -d

# Run frontend dev server with hot reload
npm run dev
```

### Running Tests

```bash
# Run all tests
PHPUNIT_DISABLE_RESULT_CACHE=1 bin/sail phpunit

# Run specific test
PHPUNIT_DISABLE_RESULT_CACHE=1 bin/sail phpunit --filter TestName
```

### Building for Production

```bash
npm run build
```

This will build both client and SSR assets.

## Configuration

Key configuration areas:

- **OAuth2/OpenID Connect**: Configure clients, scopes, and endpoints in the admin panel
- **Social Providers**: Install and configure social authentication providers via the web panel
- **LDAP**: Configure in `config/ldap.php` (WIP)
- **Queue**: Configure Horizon dashboard and workers
- **WebSockets**: Configure in `config/reverb.php`