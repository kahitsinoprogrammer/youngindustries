# Selected Software Inventory

This document enumerates the main software explicitly selected in this repository for the Snipe-IT-based system and its project-specific extensions.

The entries below are based on the repo configuration files, especially `README.md`, `composer.json`, `package.json`, `.env.example`, `Dockerfile`, `docker-compose.yml`, and `webpack.mix.js`.

## 1. Core platform software

| Software | Version or selection in repo | Role in this project | Usage level | Evidence in repo |
| --- | --- | --- | --- | --- |
| Snipe-IT | `grokability/snipe-it` | Core asset-management application | Required | `README.md`, `composer.json` |
| PHP | `^8.2` application requirement; Docker image installs PHP `8.3` packages | Server-side runtime for the application | Required | `composer.json`, `Dockerfile` |
| Laravel | `^11.0` | Main backend framework | Required | `README.md`, `composer.json` |
| MySQL / MariaDB | Default `DB_CONNECTION=mysql`; Compose image `mariadb:11.4.7` | Primary relational database | Required | `.env.example`, `docker-compose.yml` |
| Apache HTTP Server | `apache2` in Docker image | Web server for containerized deployment | Required for Docker deployment | `Dockerfile` |
| Node.js | `.nvmrc` pins `v18.16.0` | Runtime for frontend asset builds | Required for local/frontend builds | `.nvmrc`, `package.json` |
| npm | Uses `package.json` scripts such as `development`, `watch`, and `production` | Package manager and script runner for frontend assets | Required for local/frontend builds | `package.json` |
| Laravel Mix | `^6.0.49` | Frontend asset compilation pipeline | Required for local/frontend builds | `package.json`, `webpack.mix.js` |
| Webpack | `^5.98.0` | Bundler behind the Mix pipeline | Required for local/frontend builds | `package.json` |
| Bootstrap | `3.4.1` | Base CSS/UI framework | Required by the current frontend | `package.json`, `webpack.mix.js` |
| AdminLTE | `^2.4.18` | Admin dashboard theme and layout layer | Required by the current frontend | `package.json`, `webpack.mix.js` |
| jQuery | `^3.7.1` | Frontend interaction layer and plugin dependency | Required by the current frontend | `package.json` |
| Livewire | `^3.5` | Server-driven interactive UI components | Used by project features | `composer.json` |
| Laravel Passport | `^12.0` | OAuth/token-based authentication support | Used for API and token flows | `composer.json`, `.env.example` |
| Docker / Docker Compose | Dockerfile and Compose files present | Containerized deployment option | Optional deployment path | `Dockerfile`, `docker-compose.yml`, `dev.docker-compose.yml` |

## 2. Optional integrations and extension software

| Software | Version or selection in repo | Role in this project | Usage level | Evidence in repo |
| --- | --- | --- | --- | --- |
| MongoDB | Environment variables provided for asset-request collections | Data store for the custom asset request extension | Optional, extension-specific | `.env.example` |
| Firebase / Firestore | Environment variables provided for helpdesk collections and service account credentials | Data store for the tech-support/helpdesk extension | Optional, extension-specific | `.env.example` |
| Ollama | `OLLAMA_ENABLED=false` and default model `llama3.2:latest` | Self-hosted or cloud-hosted AI/chatbot integration point | Optional, disabled by default | `.env.example` |
| Redis | `php8.3-redis` installed in Docker image; Redis env vars provided | Optional cache/session/queue backend | Optional | `Dockerfile`, `.env.example` |
| Memcached | `php-memcached` installed in Docker image; Memcached env vars provided | Optional cache backend | Optional | `Dockerfile`, `.env.example` |
| Amazon S3 | Public/private S3 environment variables plus Flysystem AWS package | Optional file storage backend | Optional | `.env.example`, `composer.json` |
| Socialite | `^5.6` | Social login support such as Google sign-in | Optional integration | `composer.json` |
| SAML | `onelogin/php-saml` | Federated authentication support | Optional integration | `composer.json` |
| LDAP | `ext-ldap` suggested; `php8.3-ldap` installed in Docker image | Directory-backed authentication support | Optional integration | `composer.json`, `Dockerfile` |

## 3. Notes

| Topic | Note |
| --- | --- |
| Version interpretation | Some versions are package constraints rather than exact deployed versions. For example, the app requires PHP `^8.2`, while the provided Docker image installs PHP `8.3`. |
| Database interpretation | The repo defaults to `mysql`, and the provided Compose stack uses MariaDB. This indicates a MySQL-compatible relational database is the primary selected database platform. |
| Build tool interpretation | The repo uses Laravel Mix and Webpack rather than Vite for asset compilation. |
| Optional services | MongoDB, Firebase, Ollama, Redis, Memcached, S3, LDAP, SAML, and Socialite-backed login are available as integrations, but they are not all required for a baseline deployment. |
