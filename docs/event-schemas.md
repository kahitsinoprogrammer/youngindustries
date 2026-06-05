# Event Schemas

This document complements `event-catalog.md` by describing the payload shape of the main runtime events and the persisted `action_logs` event record.

## 1. Schema conventions

| Column | Meaning |
| --- | --- |
| Field | Property or column name |
| Type | PHP/runtime type or storage-oriented type |
| Required | `Yes` means the producer always sets it in the code path documented here |
| Notes | Meaning, allowed values, or consumer expectations |

## 2. Runtime event schema index

| Event type | Primary payload shape | Main producers | Main consumers |
| --- | --- | --- | --- |
| `Illuminate\Auth\Events\Login` | `guard`, `user`, `remember` | Laravel login flow | `LogSuccessfulLogin` |
| `Illuminate\Auth\Events\Failed` | `guard`, `user`, `credentials` | Laravel failed-auth flow | `LogFailedLogin` |
| `App\Events\CheckoutableCheckedOut` | `checkoutable`, `checkedOutTo`, `checkedOutBy`, `note`, `originalValues` | checkout flows | `LogListener`, `CheckoutableListener` |
| `App\Events\CheckoutableCheckedIn` | `checkoutable`, `checkedOutTo`, `checkedInBy`, `note`, `action_date`, `originalValues` | checkin flows | `LogListener`, `CheckoutableListener` |
| `App\Events\CheckoutAccepted` | `acceptance` | acceptance flow | `LogListener` |
| `App\Events\CheckoutDeclined` | `acceptance` | acceptance flow | `LogListener` |
| `App\Events\UserMerged` | `merged_from`, `merged_to`, `admin` | user-merge flows | `LogListener` |

## 3. Runtime event payload tables

### 3.1 `Illuminate\Auth\Events\Login`

| Field | Type | Required | Notes |
| --- | --- | --- | --- |
| `guard` | `string` | Yes | Auth guard name |
| `user` | `Authenticatable` | Yes | In this project the listener expects a user with `username` |
| `remember` | `bool` | Yes | Whether remember-me was requested |

### 3.2 `Illuminate\Auth\Events\Failed`

| Field | Type | Required | Notes |
| --- | --- | --- | --- |
| `guard` | `string` | Yes | Auth guard name |
| `user` | `Authenticatable|null` | No | May be `null` on failed auth |
| `credentials` | `array` | Yes | Local listener expects `credentials['username']` |

### 3.3 `App\Events\CheckoutableCheckedOut`

| Field | Type | Required | Notes |
| --- | --- | --- | --- |
| `checkoutable` | `Asset|Accessory|Component|Consumable|LicenseSeat|mixed` | Yes | The item being issued or assigned |
| `checkedOutTo` | `User|Asset|Location|mixed` | Yes | Checkout target; type depends on flow |
| `checkedOutBy` | `User` | Yes | Actor performing the checkout |
| `note` | `string|null` | No | Free-text reason or note |
| `originalValues` | `array<string,mixed>` | No | Prior values used for change logging |

Consumer expectations:

| Consumer | Fields relied on |
| --- | --- |
| `LogListener@onCheckoutableCheckedOut` | `checkoutable`, `checkedOutTo`, `note`, `originalValues` |
| `CheckoutableListener@onCheckedOut` | `checkoutable`, `checkedOutTo`, `checkedOutBy`, `note` |

### 3.4 `App\Events\CheckoutableCheckedIn`

| Field | Type | Required | Notes |
| --- | --- | --- | --- |
| `checkoutable` | `Asset|Accessory|Component|LicenseSeat|mixed` | Yes | The item being returned |
| `checkedOutTo` | `User|Asset|Location|null|mixed` | No | Previous assignee or target |
| `checkedInBy` | `User` | Yes | Actor performing the checkin |
| `note` | `string|null` | No | Free-text note |
| `action_date` | `string` | Yes | Defaults to current timestamp if omitted |
| `originalValues` | `array<string,mixed>` | No | Prior values used for change logging |

Consumer expectations:

| Consumer | Fields relied on |
| --- | --- |
| `LogListener@onCheckoutableCheckedIn` | `checkoutable`, `checkedOutTo`, `note`, `action_date`, `originalValues` |
| `CheckoutableListener@onCheckedIn` | `checkoutable`, `checkedOutTo`, `checkedInBy`, `note` |

### 3.5 `App\Events\CheckoutAccepted`

Top-level schema:

| Field | Type | Required | Notes |
| --- | --- | --- | --- |
| `acceptance` | `CheckoutAcceptance` | Yes | Acceptance record carrying the nested state below |

Nested `CheckoutAcceptance` fields used by local consumers:

| Field | Type | Required | Notes |
| --- | --- | --- | --- |
| `checkoutable` | morph target | Yes | Accepted item |
| `assignedTo` | `User` | Yes | Assignee who accepted |
| `signature_filename` | `string|null` | No | Stored signature file name |
| `stored_eula_file` | `string|null` | No | Saved EULA PDF filename |
| `note` | `string|null` | No | Acceptance note |
| `accepted_at` | `datetime|null` | Yes | Used as the log action date on accept |

### 3.6 `App\Events\CheckoutDeclined`

Top-level schema:

| Field | Type | Required | Notes |
| --- | --- | --- | --- |
| `acceptance` | `CheckoutAcceptance` | Yes | Acceptance record carrying the nested state below |

Nested `CheckoutAcceptance` fields used by local consumers:

| Field | Type | Required | Notes |
| --- | --- | --- | --- |
| `checkoutable` | morph target | Yes | Declined item |
| `assignedTo` | `User` | Yes | Assignee who declined |
| `signature_filename` | `string|null` | No | Stored signature file name |
| `note` | `string|null` | No | Decline note |
| `declined_at` | `datetime|null` | Yes | Used as the log action date on decline |

### 3.7 `App\Events\UserMerged`

| Field | Type | Required | Notes |
| --- | --- | --- | --- |
| `merged_from` | `User` | Yes | Source user being merged away |
| `merged_to` | `User` | Yes | Destination user |
| `admin` | `User|null` | No | Actor who initiated the merge; can be `null` in CLI flow |

## 4. Persisted action log event record schema

This is the most stable "event schema" in the project because many workflows ultimately end up as `action_logs` rows.

### 4.1 Base `action_logs` schema

| Field | Type | Required | Notes |
| --- | --- | --- | --- |
| `id` | `int` | Yes | Primary key |
| `item_type` | `string` | Yes | Morph class name of the subject item |
| `item_id` | `int` | Yes | Subject item ID |
| `action_type` | `string` | Yes | Event name such as `checkout`, `audit`, `uploaded` |
| `created_by` | `int|null` | No | User who caused the event |
| `target_type` | `string|null` | No | Morph class name of the target user/item/location |
| `target_id` | `int|null` | No | Target identifier |
| `company_id` | `int|null` | No | Derived during creation for tenant/company scoping |
| `location_id` | `int|null` | No | Relevant location for some actions |
| `note` | `string|null` | No | Human-authored note |
| `filename` | `string|null` | No | File attached to the event or upload record |
| `accept_signature` | `string|null` | No | Signature filename for acceptance-related events |
| `action_date` | `datetime|string` | Yes | Business timestamp of the event |
| `created_at` | `datetime` | Yes | Persistence timestamp |
| `updated_at` | `datetime|null` | No | Standard Eloquent timestamp |
| `deleted_at` | `datetime|null` | No | Soft-delete marker |
| `remote_ip` | `string|null` | No | Captured by `logaction()` |
| `user_agent` | `string|null` | No | Captured by `logaction()` |
| `action_source` | `string|null` | No | Usually `api`, `gui`, or `cli/unknown` |
| `log_meta` | `json|string|null` | No | Structured change details, especially for checkout/checkin/audit |

### 4.2 `action_type` value set used in code

| Value | Typical meaning |
| --- | --- |
| `create` | record created |
| `update` | record updated |
| `delete` | record deleted |
| `restore` | record restored |
| `checkout` | item checked out or assigned |
| `checkin from` | item checked in or returned |
| `requested` | request created |
| `request canceled` | request canceled |
| `accepted` | item acceptance confirmed |
| `declined` | item acceptance declined |
| `audit` | audit recorded |
| `note added` | note created |
| `2FA reset` | two-factor state reset |
| `merged` | users merged |
| `delete seats` | license seats removed |
| `add seats` | license seats added |
| `uploaded` | file uploaded |
| `upload deleted` | uploaded file removed |

### 4.3 Action-specific field matrix

| `action_type` | Fields that are usually important |
| --- | --- |
| `checkout` | `item_type`, `item_id`, `target_type`, `target_id`, `location_id`, `note`, `log_meta`, `action_date` |
| `checkin from` | `item_type`, `item_id`, `target_type`, `target_id`, `note`, `log_meta`, `action_date` |
| `requested` | `item_type`, `item_id`, `target_type`, `target_id`, `location_id`, `created_by`, `created_at` |
| `request canceled` | `item_type`, `item_id`, `target_type`, `target_id`, `location_id`, `created_by`, `created_at` |
| `accepted` | `item_type`, `item_id`, `target_type`, `target_id`, `accept_signature`, `filename`, `note`, `action_date` |
| `declined` | `item_type`, `item_id`, `target_type`, `target_id`, `accept_signature`, `note`, `action_date` |
| `audit` | `item_type`, `item_id`, `location_id`, `note`, `filename`, `log_meta`, `action_date` |
| `note added` | `item_type`, `item_id`, `created_by`, `note` |
| `uploaded` | `item_type`, `item_id`, `created_by`, `filename`, `note` |
| `upload deleted` | `item_type`, `item_id`, `created_by`, `filename` |
| `merged` | `item_type`, `item_id`, `target_type`, `target_id`, `note`, `created_by` |

## 5. Practical notes

| Observation | Detail |
| --- | --- |
| Runtime payloads are intentionally loose | The checkout events rely on model instances rather than strict DTO-style schemas |
| `originalValues` is intentionally open-ended | It is an associative map of prior values, not a fixed object contract |
| The durable event contract is the action log | If external reporting or auditing needs a stable schema, `action_logs` is the better long-term source |
| No dedicated `NoteAdded` event class is present | Notes are persisted directly to `action_logs` instead of being emitted as their own runtime event class |

## 6. Source of truth

- `app/Events/*`
- `app/Models/CheckoutAcceptance.php`
- `app/Models/Actionlog.php`
- `app/Models/Traits/Loggable.php`
- `app/Listeners/LogListener.php`
- `app/Listeners/CheckoutableListener.php`
- `app/Listeners/LogSuccessfulLogin.php`
- `app/Listeners/LogFailedLogin.php`
- `vendor/laravel/framework/src/Illuminate/Auth/Events/Login.php`
- `vendor/laravel/framework/src/Illuminate/Auth/Events/Failed.php`

