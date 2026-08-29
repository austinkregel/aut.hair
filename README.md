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

## Forward Auth (protecting containerized apps)

aut.hair can protect any containerized app behind a reverse proxy without the app
knowing anything about authentication — the Authentik "outpost" model. The proxy
(Traefik `forwardAuth`, nginx `auth_request`, Caddy `forward_auth`) calls aut.hair
on every request; aut.hair answers `200` (with `X-authentik-username/email/groups`
headers), `302` to login, or `403`. The endpoint and header names match Authentik's
nginx outpost, so an existing `forwardAuth` config only needs its address repointed.

Verify endpoint: `GET /outpost.goauthentik.io/auth/nginx`

### Deployment posture (read this first)

**Point the proxy's forward-auth address at aut.hair's _internal_ address, and do
NOT route `/outpost.goauthentik.io/` on aut.hair's _public_ ingress.** This is how
Authentik's own outpost is meant to be deployed: the auth endpoint is reachable only
from the proxy on the internal network, never from the internet.

The endpoint lives in the same Laravel app that serves the public UI/OIDC endpoints,
so if aut.hair is published at (say) `auth.example.com`, the path also exists at
`https://auth.example.com/outpost.goauthentik.io/auth/nginx`. Keep it off the public
router:

- **Traefik forward-auth address → internal service** (aut.hair listens on `8000`):

  ```
  # homelab-in-a-box: set the forward_auth_address Setting
  http://aut-hair:8000/outpost.goauthentik.io/auth/nginx
  ```

- **Block the path on aut.hair's own public router** so the internet can't reach it.
  Add a higher-priority router for the `/outpost.goauthentik.io/` prefix that is
  restricted to internal callers (external hits get 403), while the internal address
  above is unaffected:

  ```yaml
  # Traefik dynamic config for the aut.hair public entrypoint
  http:
    routers:
      authair-forward-auth-deny:
        rule: "Host(`auth.example.com`) && PathPrefix(`/outpost.goauthentik.io/`)"
        priority: 100            # higher than the main aut.hair router
        entryPoints: [websecure]
        service: authair
        middlewares: [internal-only]
        tls: { certResolver: letsencrypt }
    middlewares:
      internal-only:
        ipAllowList:
          sourceRange: ["10.0.0.0/8", "172.16.0.0/12", "192.168.0.0/16"]
  ```

If you cannot keep it off the public ingress, set a **shared secret**
(`FORWARD_AUTH_SHARED_SECRET`) and have the proxy inject it as `X-Forward-Auth-Secret`
via a `headers.customRequestHeaders` middleware (which overwrites any client-supplied
value). aut.hair rejects any request lacking the exact secret before any other logic.

Defense-in-depth already in the app: the discovery subnet gate and rate limit key off
the real socket peer (`REMOTE_ADDR`), not the spoofable `X-Forwarded-For`.

### SSO cookie

Set `SESSION_DOMAIN=.example.com` so one aut.hair login is visible on every protected
subdomain — the shared parent-domain cookie is the SSO mechanism, so no per-app
callback is needed. All protected apps must therefore be subdomains of the aut.hair
parent domain. Set `SESSION_SECURE_COOKIE=true`.

### Registering apps

Apps are matched by hostname (`ProxyApp`), and access is granted per-app to an owner
team plus any explicitly allowed teams. There are three ways an app gets registered:

- **Deploy-time push** — `POST /api/forward-auth/apps` (client-credentials token with
  the `forward-auth` scope) upserts an app by host. Intended for a trusted deploy
  pipeline (e.g. homelab-in-a-box).
- **First-contact discovery** — an unknown host seen at the verify endpoint is
  auto-registered as `pending` (from a trusted subnet only, rate-limited) and surfaced
  for approval. It fails closed until approved.
- **Approval** — an admin (`OnlyHost`) approves a pending app, assigning the owner and
  allow-list, at which point it goes live.

Only an `approved` + `enabled` app whose owner/allow-list intersects the user's teams
returns `200`. See `config/forward-auth.php` for all knobs.