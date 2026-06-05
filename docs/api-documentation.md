# API Documentation

This document summarizes where the API documentation for this project lives, how the Jira-linked API behavior is documented, and where the Postman collection for the project can be found.

## 1. API documentation sources

| Resource | Type | Purpose |
| --- | --- | --- |
| [system-apis.md](system-apis.md) | Internal API reference | Describes the main REST API under `/api/v1` and the SCIM API under `/scim/v2` |
| [process-apis.md](process-apis.md) | Workflow documentation | Explains how endpoints work together for request, checkout, checkin, audit, import, and admin flows |
| [experience-apis.md](experience-apis.md) | Experience-layer documentation | Describes user-facing routes and behavior, including custom extension flows |
| [rest-api-endpoint-design.md](rest-api-endpoint-design.md) | Design guide | Documents REST endpoint naming and structural patterns |
| [rest-api-http-methods-and-status-codes.md](rest-api-http-methods-and-status-codes.md) | Behavior guide | Documents HTTP method and status-code usage across the API |
| [rest-api-request-response-format.md](rest-api-request-response-format.md) | Payload guide | Documents request and response conventions |
| `routes/api.php` | Source of truth | Defines the Laravel REST API route surface under `/api/v1` |

## 2. Base API information

| Item | Value |
| --- | --- |
| Main REST API base path | `/api/v1` |
| SCIM API base path | `/scim/v2` |
| Primary API auth model | `auth:api` with Laravel Passport personal access tokens |
| Upstream Snipe-IT API reference mentioned in code | `https://snipe-it.readme.io/reference` |

## 3. Jira link

| Item | Value |
| --- | --- |
| Jira integration document | [external-system-integrations-jira.md](external-system-integrations-jira.md) |
| Local Jira diagnostics route | `GET /tech-support/jira-diagnostics` |
| Jira runtime base URL | Provided through `JIRA_BASE_URL` in `.env` |
| Jira project key | Provided through `JIRA_PROJECT_KEY` in `.env` |
| Jira credentials | Provided through `JIRA_EMAIL` and `JIRA_API_TOKEN` in `.env` |

### Jira note

The repository does not store one fixed live Jira project URL. The concrete Jira link depends on the deployment environment because it is configured through `JIRA_BASE_URL`.

## 4. Postman collection

| Item | Value |
| --- | --- |
| Collection file | [youngindustries-api.postman_collection.json](youngindustries-api.postman_collection.json) |
| Collection type | Starter Postman collection for the REST API |
| Auth model | Bearer token using a Laravel Passport personal access token |
| Variables included | `base_url`, `api_token`, `asset_tag` |
| Included request style | Safe starter requests focused on read operations |

### Included starter requests

| Folder | Request | Method | Path |
| --- | --- | --- | --- |
| Account | List personal access tokens | `GET` | `/api/v1/account/personal-access-tokens` |
| Account | List requestable hardware | `GET` | `/api/v1/account/requestable/hardware` |
| Inventory | List hardware | `GET` | `/api/v1/hardware` |
| Inventory | Find hardware by tag | `GET` | `/api/v1/hardware/bytag/{{asset_tag}}` |
| Inventory | List accessories | `GET` | `/api/v1/accessories` |
| Inventory | List licenses | `GET` | `/api/v1/licenses` |

## 5. Usage note

Use the repo docs for structure and behavior, use the Jira integration doc for the custom tech-support to Jira flow, and use the Postman collection as a quick starting point for authenticated API exploration.
