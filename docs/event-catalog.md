# Event Catalog

This catalog documents the event types used by this project.

To keep it accurate to the codebase, it covers two layers:

1. Dispatched runtime events
2. Persisted `Actionlog` event types

That distinction matters in this repo because the custom Laravel event layer is intentionally small, while the action log acts as the richer operational event ledger.

## 1. Runtime event catalog

| Event type | Kind | Typical producers | Typical consumers | Main effect |
| --- | --- | --- | --- | --- |
| `Illuminate\Auth\Events\Login` | Framework auth event | Login flow through Laravel auth | `LogSuccessfulLogin` | Writes a successful login row to `login_attempts` |
| `Illuminate\Auth\Events\Failed` | Framework auth event | Failed login attempts | `LogFailedLogin` | Writes a failed login row to `login_attempts` |
| `App\Events\CheckoutableCheckedOut` | Custom domain event | `Asset::checkOut()`, checkout controllers, kit checkout service, API checkout flows | `LogListener`, `CheckoutableListener` | Creates checkout logs, acceptance records, and outbound notifications |
| `App\Events\CheckoutableCheckedIn` | Custom domain event | Checkin controllers, API checkin flows, importer-driven checkin, delete/update side effects | `LogListener`, `CheckoutableListener` | Creates checkin logs, clears pending acceptances, and sends notifications |
| `App\Events\CheckoutAccepted` | Custom domain event | `Account\AcceptanceController@store` | `LogListener` | Writes an `accepted` action log with signature and EULA metadata |
| `App\Events\CheckoutDeclined` | Custom domain event | `Account\AcceptanceController@store` | `LogListener` | Writes a `declined` action log with decline metadata |
| `App\Events\UserMerged` | Custom domain event | `BulkUsersController`, `MergeUsersByUsername` command | `LogListener` | Writes `merged` logs for both the source and destination users |

## 2. Persisted action event catalog

These are the `ActionType` values stored in `action_logs`. They behave like a durable event history for the application.

| Event type | Kind | Typical producers | Main meaning | Notable side effects or notes |
| --- | --- | --- | --- | --- |
| `create` | `Actionlog` type | `Loggable::logCreate()`, observers | A record was created | Used as part of entity history |
| `update` | `Actionlog` type | Observers such as `UserObserver` | A record was updated | Used for audit history on edits |
| `delete` | `Actionlog` type | Observers and destructive UI flows | A record was deleted | Appears in historical timelines |
| `restore` | `Actionlog` type | Restore controllers for assets, models, manufacturers, users, locations | A soft-deleted record was restored | Makes archived items traceable |
| `checkout` | `Actionlog` type | `LogListener` via `CheckoutableCheckedOut`, `Loggable::logCheckout()` | An item was assigned or issued | May include metadata about changed fields and target |
| `checkin from` | `Actionlog` type | `LogListener` via `CheckoutableCheckedIn`, `Loggable::logCheckin()` | An item was returned | May include changed field metadata and action date |
| `requested` | `Actionlog` type | `CreateCheckoutRequestAction`, `ViewAssetsController` | A request for an item was created | Can trigger request notifications |
| `request canceled` | `Actionlog` type | `CancelCheckoutRequestAction`, `ViewAssetsController` | A request was withdrawn | Can trigger cancellation notifications |
| `accepted` | `Actionlog` type | `LogListener` via `CheckoutAccepted` | A checked-out item was accepted by the assignee | Stores signature and stored EULA file data |
| `declined` | `Actionlog` type | `LogListener` via `CheckoutDeclined` | A checked-out item was declined by the assignee | Stores decline-related signature data |
| `audit` | `Actionlog` type | `Loggable::logAudit()` from asset audit flows | An audit was recorded | Also triggers `AuditNotification` delivery |
| `note added` | `Actionlog` type | `NotesController`, `Api\NotesController` | A manual note was attached to an asset | Used in user-visible history and note views |
| `2FA reset` | `Actionlog` type | `Api\UsersController` | A user's two-factor setup was reset | Security-relevant admin action |
| `merged` | `Actionlog` type | `LogListener` via `UserMerged` | One user record was merged into another | Writes paired history entries on both users |
| `delete seats` | `Actionlog` type | `License` model seat-removal logic | License seats were removed | Captures license-seat inventory changes |
| `add seats` | `Actionlog` type | `License` model seat-addition logic | License seats were added | Captures license-seat inventory changes |
| `uploaded` | `Actionlog` type | `Loggable::logUpload()`, uploaded-file controllers | A file was attached to an object | Powers the file attachment history and download UI |
| `upload deleted` | `Actionlog` type | `Actionlog::logUploadDelete()`, file delete flows | A previously uploaded file was removed | Preserves deletion history even after file removal |

## 3. Event source files

The main source of truth for this catalog is:

- `app/Providers/EventServiceProvider.php`
- `app/Events/CheckoutableCheckedOut.php`
- `app/Events/CheckoutableCheckedIn.php`
- `app/Events/CheckoutAccepted.php`
- `app/Events/CheckoutDeclined.php`
- `app/Events/UserMerged.php`
- `app/Listeners/CheckoutableListener.php`
- `app/Listeners/LogListener.php`
- `app/Listeners/LogSuccessfulLogin.php`
- `app/Listeners/LogFailedLogin.php`
- `app/Enums/ActionType.php`
- `app/Models/Actionlog.php`
- `app/Models/Traits/Loggable.php`

## 4. Notes

| Observation | Detail |
| --- | --- |
| Runtime event layer is intentionally small | Most domain-level runtime events are centered on checkout, checkin, acceptance, decline, and merge |
| Action log is the richer event ledger | If you want historical traceability, `action_logs` is the more complete catalog than the custom event classes alone |
| Notifications hang off key domain events | Checkout, checkin, request, cancellation, and audit actions can all trigger mail or webhook behavior |
| Source attribution is available | `Actionlog::determineActionSource()` classifies event origin as `api`, `gui`, or `cli/unknown` |

