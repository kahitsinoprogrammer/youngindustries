# Railway Deployment Guide

This guide is the recommended cloud deployment path for this repository when you want a public URL that a professor can use.

## 1. Why not Vercel?

Vercel is not a good fit for this project because this app is a full Laravel + PHP + MySQL application with persistent uploads and server-side storage needs.

This repository expects:

- a long-running PHP web server
- a MySQL-compatible database
- writable persistent storage for uploads and generated files
- Laravel migrations at startup

The current Docker setup in this repo is a much better match for a container host than for a serverless frontend platform.

## 2. Recommended deployment target

Use Railway for the first public deployment.

Why Railway fits this repo:

- it can build directly from the repo `Dockerfile`
- it supports a MySQL service
- it supports persistent volumes
- it gives you a public HTTPS domain quickly

## 3. Recommended professor-ready architecture

For the simplest demo deployment, use:

| Component | Recommended choice |
| --- | --- |
| App hosting | Railway web service built from this repo's `Dockerfile` |
| Database | Railway MySQL service |
| Persistent file storage | Railway Volume mounted at `/var/lib/snipeit` |
| Public URL | Railway-generated domain |

For the first deployment, keep optional integrations disabled unless your professor specifically needs them:

- MongoDB
- Firebase / Firestore
- Jira
- Ollama
- LDAP / SAML
- S3 storage

## 4. Before you start

Make sure you already have:

1. your GitHub repository pushed and up to date
2. a Railway account
3. access to the repo branch you want to deploy, usually `main`

## 5. Railway setup steps

### 5.1 Create a new Railway project

1. Log in to Railway.
2. Create a new project.
3. Choose `Deploy from GitHub repo`.
4. Select `kahitsinoprogrammer/youngindustries`.

### 5.2 Add a MySQL service

1. In the same Railway project, add a new service.
2. Choose the MySQL template.
3. Wait until the MySQL service finishes provisioning.

### 5.3 Deploy the app service from this repo

1. Add a new service from the GitHub repo if Railway did not already create one.
2. Let Railway build using the root `Dockerfile`.
3. Do not use `docker-compose.yml` for this deployment path.

### 5.4 Attach persistent storage

1. Add a Railway Volume to the app service.
2. Mount it at:

```text
/var/lib/snipeit
```

This matches the paths used by the existing container startup script for:

- uploads
- private uploads
- backups
- generated keys

### 5.5 Generate a public domain

1. Open the app service settings.
2. Go to `Networking`.
3. Enable `Public Networking`.
4. Generate a Railway domain.

After Railway gives you the domain, copy it because you will use it for `APP_URL`.

## 6. Environment variables for the app service

Set these variables on the Railway app service.

### 6.1 Required core variables

| Variable | Suggested value |
| --- | --- |
| `PORT` | `80` |
| `APP_ENV` | `production` |
| `APP_DEBUG` | `false` |
| `APP_KEY` | generate a fresh Laravel app key |
| `APP_URL` | your Railway public URL, for example `https://your-app.up.railway.app` |
| `APP_TIMEZONE` | `UTC` or your preferred timezone |
| `APP_LOCALE` | `en-US` |
| `APP_TRUSTED_PROXIES` | `*` |
| `APP_FORCE_TLS` | `true` |
| `PRIVATE_FILESYSTEM_DISK` | `local` |
| `PUBLIC_FILESYSTEM_DISK` | `local_public` |
| `IMAGE_LIB` | `gd` |

### 6.2 Database variables

Use Railway variable references from the MySQL service:

| Variable | Suggested value |
| --- | --- |
| `DB_CONNECTION` | `mysql` |
| `DB_HOST` | `${{MySQL.MYSQLHOST}}` |
| `DB_PORT` | `${{MySQL.MYSQLPORT}}` |
| `DB_DATABASE` | `${{MySQL.MYSQLDATABASE}}` |
| `DB_USERNAME` | `${{MySQL.MYSQLUSER}}` |
| `DB_PASSWORD` | `${{MySQL.MYSQLPASSWORD}}` |
| `DB_CHARSET` | `utf8mb4` |
| `DB_COLLATION` | `utf8mb4_unicode_ci` |
| `DB_DUMP_SKIP_SSL` | `true` |

### 6.3 Simplest mail settings for a demo deployment

If your professor does not need real email sending, use:

| Variable | Suggested value |
| --- | --- |
| `MAIL_MAILER` | `log` |
| `MAIL_FROM_ADDR` | `noreply@example.com` |
| `MAIL_FROM_NAME` | `Young Industries Snipe-IT` |
| `MAIL_REPLYTO_ADDR` | `noreply@example.com` |
| `MAIL_REPLYTO_NAME` | `Young Industries Snipe-IT` |
| `MAIL_TLS_VERIFY_PEER` | `true` |

### 6.4 Stable demo defaults

| Variable | Suggested value |
| --- | --- |
| `CACHE_DRIVER` | `file` |
| `SESSION_DRIVER` | `file` |
| `QUEUE_DRIVER` | `sync` |
| `LOG_CHANNEL` | `stderr` |
| `SECURE_COOKIES` | `true` |

## 7. How to generate `APP_KEY`

Generate the key locally from the project root:

```bash
php artisan key:generate --show
```

Copy the full `base64:...` output into the Railway `APP_KEY` variable.

## 8. What the container already does for you

This repo's existing Docker startup flow already:

- checks that `APP_KEY` exists
- creates the expected `/var/lib/snipeit` directories
- runs `php artisan migrate --force`
- clears and rebuilds config cache
- starts Apache
- runs Laravel's scheduler loop

That means you do not need to separately run migrations for the basic deployment path.

## 9. First launch checklist

After deployment succeeds:

1. Open the Railway public URL.
2. Confirm the app loads without a `500` error.
3. If the database is empty, complete the first-time Snipe-IT setup flow in the browser.
4. Create the admin account your professor will use.
5. Log in and test:
   - dashboard
   - asset list
   - user list
   - create or edit actions

## 10. If your project demo needs the custom extensions

Only do this if your professor must use those flows.

| Feature | Extra service needed |
| --- | --- |
| Asset request extension | MongoDB |
| Tech support ticket flow | Firebase / Firestore |
| Jira issue creation | Jira Cloud project + API credentials |
| Asset chatbot | Ollama server |

If your demo goal is only to show the main asset-management system, leave these integrations disabled for the first deployment.

## 11. Common failure points

| Problem | Most likely fix |
| --- | --- |
| App boot loop on deploy | set `APP_KEY` correctly |
| Database connection error | recheck `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` |
| CSS or JS looks broken | confirm the Docker image built successfully from this repo and not from the upstream Snipe-IT image |
| Uploads disappear after restart | confirm the Railway Volume is mounted at `/var/lib/snipeit` |
| HTTPS redirect or login cookie issues | confirm `APP_URL`, `APP_TRUSTED_PROXIES=*`, `APP_FORCE_TLS=true`, and `SECURE_COOKIES=true` |

## 12. Recommended first goal

For the fastest professor-ready deployment, aim for this scope first:

1. deploy the main app to Railway
2. connect Railway MySQL
3. attach `/var/lib/snipeit` volume
4. create an admin account
5. demonstrate the core inventory system

Once that is working, you can decide whether the custom MongoDB, Firebase, Jira, or Ollama flows are worth adding.
