# Cloud Demo Deployment Guide

This guide is for the case where Railway is unavailable, over quota, or no longer the cheapest way to get a public demo online.

## 1. Short answer

Use **Northflank** first if you want the closest replacement for Railway.

Use a **small VPS** if you want the lowest predictable cost and are okay managing one server.

Do **not** start with DigitalOcean App Platform for this repository. This project stores uploads and generated files on the filesystem, and App Platform does not provide persistent host storage or volumes.

## 2. Recommended option: Northflank

### 2.1 Why Northflank fits this repo

This repository already has what Northflank needs:

- a root `Dockerfile`
- a MySQL-compatible database requirement
- a persistent filesystem path at `/var/lib/snipeit`
- a web container that exposes port `80`

Northflank is also the closest workflow to Railway because it supports:

- Git-based deploys
- Dockerfile builds
- public HTTPS endpoints
- managed MySQL
- persistent volumes

### 2.2 Northflank pricing notes

Northflank currently shows:

- a `Sandbox` tier with `2x free services` and `1x free database`
- `nf-compute-50` at `0.5 shared vCPU / 1024 MB` for `$12/month`
- `nf-compute-100-2` at `1 dedicated vCPU / 2048 MB` for `$24/month`
- storage at `$0.15 / GB / month`

For this Laravel + Apache + MySQL demo, I would start with the free sandbox only to test first boot. If the app restarts or fails under load, move the app service to at least `nf-compute-50`. That sizing suggestion is a practical starting point, not an official minimum from the vendor.

### 2.3 Northflank deployment steps

1. Create a new Northflank project.
2. Create a `combined service`.
3. Connect your GitHub repository and choose the branch you want to demo.
4. For the build settings, choose `Dockerfile`.
5. Set the Dockerfile path to `/Dockerfile`.
6. Keep the build context at `/`.
7. Let Northflank detect the exposed port from the Dockerfile. If it does not, add a public `HTTP` port for `80`.
8. Create a MySQL addon in the same project.
9. Add a persistent volume to the app service and mount it at:

```text
/var/lib/snipeit
```

10. Use `Single Read/Write` access mode for the volume. That is fine for a one-instance professor demo.

### 2.4 Runtime variables for the app service

Set these runtime variables on the Northflank app service:

| Variable | Suggested value |
| --- | --- |
| `APP_ENV` | `production` |
| `APP_DEBUG` | `false` |
| `APP_KEY` | generate locally with `php artisan key:generate --show` |
| `APP_URL` | your Northflank public URL |
| `APP_TIMEZONE` | `UTC` |
| `APP_LOCALE` | `en-US` |
| `APP_TRUSTED_PROXIES` | `*` |
| `APP_FORCE_TLS` | `true` |
| `PRIVATE_FILESYSTEM_DISK` | `local` |
| `PUBLIC_FILESYSTEM_DISK` | `local_public` |
| `IMAGE_LIB` | `gd` |
| `CACHE_DRIVER` | `file` |
| `SESSION_DRIVER` | `file` |
| `QUEUE_DRIVER` | `sync` |
| `LOG_CHANNEL` | `stderr` |
| `SECURE_COOKIES` | `true` |
| `MAIL_MAILER` | `log` |
| `MAIL_FROM_ADDR` | `noreply@example.com` |
| `MAIL_FROM_NAME` | `Young Industries Snipe-IT` |
| `MAIL_REPLYTO_ADDR` | `noreply@example.com` |
| `MAIL_REPLYTO_NAME` | `Young Industries Snipe-IT` |
| `MAIL_TLS_VERIFY_PEER` | `true` |
| `DB_CONNECTION` | `mysql` |
| `DB_DUMP_SKIP_SSL` | `true` |
| `DB_CHARSET` | `utf8mb4` |
| `DB_COLLATION` | `utf8mb4_unicode_ci` |
| `DB_SSL` | `false` |

### 2.5 Map Northflank MySQL secrets to Laravel variables

Northflank can link MySQL addon connection details into a secret group. Map the addon values like this:

| Northflank secret | Laravel variable |
| --- | --- |
| `HOST` | `DB_HOST` |
| `PORT` | `DB_PORT` |
| `DATABASE` | `DB_DATABASE` |
| `USERNAME` | `DB_USERNAME` |
| `PASSWORD` | `DB_PASSWORD` |

Keep the database private for the first demo. The app service can reach it over Northflank's internal network.

### 2.6 What you can leave off for the first demo

Unless your professor explicitly needs them, leave these disabled on the first deployment:

- MongoDB asset request features
- Firebase / Firestore helpdesk features
- Jira integration
- Ollama integration
- LDAP / SAML
- S3-backed storage

If you later enable the asset chatbot, you can point it at a separate hosted Ollama-compatible endpoint instead of running Ollama inside the same demo environment.

### 2.7 First launch checklist

After the service finishes deploying:

1. Open the public Northflank URL.
2. Confirm the login or first-time setup page appears.
3. Complete setup if the database is empty.
4. Create the admin account for the demo.
5. Test login, assets, users, and one create or edit flow.

## 3. Cheapest fallback: single VPS

If you want the lowest predictable cost, use a single Ubuntu VPS and the repo's new [`docker-compose.demo.yml`](../docker-compose.demo.yml).

This is a good fit for:

- DigitalOcean
- Hetzner Cloud
- Vultr
- Linode / Akamai Cloud

### 3.1 Cost notes

DigitalOcean currently lists Basic Droplets at:

- `1 GB RAM / 1 vCPU` for `$6/month`
- `2 GB RAM / 1 vCPU` for `$12/month`

For this repo, I would choose `2 GB` if the same machine will run both the app and MariaDB. That is my recommendation based on the stack in this repository, not an official requirement from DigitalOcean.

### 3.2 What the VPS path includes

The provided demo Compose file:

- builds this repository's custom `Dockerfile`
- runs MariaDB beside it
- persists database files in a Docker volume
- persists uploads and generated files in a Docker volume mounted at `/var/lib/snipeit`

It is intended for a single-server class demo, not a high-availability production deployment.

### 3.3 VPS deployment steps

1. Create an Ubuntu `24.04` VPS.
2. Install Docker Engine and the Docker Compose plugin.
3. Clone this repository onto the server.
4. Copy `.env.demo.example` to `.env.demo`.
5. Generate an app key locally from the project root:

```bash
php artisan key:generate --show
```

6. Paste the generated `base64:...` value into `APP_KEY` in `.env.demo`.
7. Update at least these values in `.env.demo`:

- `APP_URL`
- `DB_PASSWORD`
- `MYSQL_ROOT_PASSWORD`
- `MAIL_FROM_ADDR`

8. Start the demo stack:

```bash
docker compose --env-file .env.demo -f docker-compose.demo.yml up -d --build
```

9. Open `http://YOUR_SERVER_IP:8000` or the URL you configured.

### 3.4 If you later add a real domain and HTTPS

Once you have a domain and reverse proxy in front of the container, change:

- `APP_URL` to `https://your-domain`
- `APP_FORCE_TLS=true`
- `SECURE_COOKIES=true`

## 4. Recommendation

If you want the fastest path with the least server work, use **Northflank**.

If you want the cheapest predictable monthly cost, use a **2 GB VPS** with `docker-compose.demo.yml`.

## 5. Official references

- Northflank pricing: <https://northflank.com/pricing>
- Northflank Dockerfile builds: <https://northflank.com/docs/v1/application/build/build-with-a-dockerfile>
- Northflank build-and-deploy flow: <https://northflank.com/docs/v1/application/getting-started/build-and-deploy-your-code>
- Northflank MySQL addon guide: <https://northflank.com/docs/v1/application/databases-and-persistence/deploy-databases-on-northflank/deploy-mysql-on-northflank>
- Northflank persistent volumes: <https://northflank.com/docs/v1/application/databases-and-persistence/add-a-volume>
- DigitalOcean Droplet pricing: <https://www.digitalocean.com/pricing/droplets>
- DigitalOcean App Platform storage limits: <https://docs.digitalocean.com/products/app-platform/how-to/store-data/>
