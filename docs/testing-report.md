# Testing Report

This document summarizes the current automated testing posture of this repository, including unit test coverage, integration-oriented test results, and known issues observed during local test execution.

The report is based on:

- `TESTING.md`
- `phpunit.xml`
- the test suite under `tests/`
- local test execution performed in this workspace on `2026-06-05`

## 1. Test suite overview

| Suite | Location | Current file count | Purpose |
| --- | --- | --- | --- |
| Unit | `tests/Unit` | `30` | Model logic, helpers, importers, listeners, mail, blade components, transformers, and low-level behavior |
| Feature | `tests/Feature` | `256` | API, UI, workflow, console, Livewire, notifications, imports, and project-specific request/support flows |

`phpunit.xml` defines two main suites: `Unit` and `Feature`.

## 2. Unit Test Coverage

### 2.1 Coverage scope

The unit suite currently covers these main areas:

| Covered area | Example test files |
| --- | --- |
| Core models | `tests/Unit/AssetTest.php`, `tests/Unit/AccessoryTest.php`, `tests/Unit/UserTest.php` |
| Company scoping and business rules | `tests/Unit/CompanyScopingTest.php`, `tests/Unit/Models/Company/GetIdForCurrentUserTest.php` |
| Helpers and translators | `tests/Unit/Helpers/HelperTest.php`, `tests/Unit/SnipeTranslatorTest.php` |
| Import and label logic | `tests/Unit/Importer/AssetImportTest.php`, `tests/Unit/Labels/LabelTest.php` |
| Blade and mail components | `tests/Unit/BladeComponents/IconComponentTest.php`, `tests/Unit/Mail/CheckoutAssetMailTest.php` |
| Notifications, listeners, and reports | `tests/Unit/NotificationTest.php`, `tests/Unit/Listeners/LogListenerTest.php`, `tests/Unit/Transformers/DepreciationReportTransformerTest.php` |
| LDAP and custom-field edge cases | `tests/Unit/LdapTest.php`, `tests/Unit/CustomFieldTest.php` |

### 2.2 Execution result

| Metric | Result |
| --- | --- |
| Command run | `php artisan test --testsuite=Unit` |
| Total tests | `159` |
| Passed | `147` |
| Failed | `12` |
| Assertions | `369` |
| Overall result | `Failed` |

### 2.3 Coverage note

| Topic | Note |
| --- | --- |
| Numeric line coverage | No line or branch coverage percentage was generated in this session |
| Repo support for coverage reports | `composer.json` includes `coverage:herd:clover` and `coverage:herd:html` scripts, but those were not run in this report |
| Practical interpretation | The repo has broad unit test presence, but this report should be read as suite coverage by scope plus execution results, not as a formal coverage-percentage baseline |

## 3. Integration Test Results

### 3.1 Execution scope

The full feature suite contains `256` files, but this report executed a targeted subset focused on the project-specific integration flows.

These tests are integration-oriented at the application boundary, but they mostly use mocked or faked external dependencies rather than hitting live Jira, Firebase, MongoDB, or Ollama services.

### 3.2 Executed integration-oriented tests

| Test file | Focus area | Result | Notes |
| --- | --- | --- | --- |
| `tests/Feature/Requests/Ui/TechSupportRequestTicketTest.php` | Firebase-backed ticket submission, Jira diagnostics, approval, and close flow | `15/15 passed` | Uses `Http::fake()` for Firebase and Jira requests |
| `tests/Feature/Requests/Ui/AssetChatbotTest.php` | Ollama-backed chatbot request flow | `3/3 passed` | Uses `Http::fake()` for Ollama chat responses |
| `tests/Feature/Requests/Ui/AssetRequestTechnicalSupportAccessTest.php` | Mongo-backed asset request technical-support workflow | `11/11 passed` | Uses injected service doubles for Mongo-related services |
| `tests/Feature/Requests/Ui/AssetRequestStatusPageTest.php` | Asset-request status workflow | `2/2 passed` | Verifies request status page rendering and status grouping |

### 3.3 Aggregate result

| Metric | Result |
| --- | --- |
| Commands run | `4` targeted feature test commands |
| Total tests executed | `31` |
| Passed | `31` |
| Failed | `0` |
| Assertions | `108` |
| Overall result | `Passed` |

### 3.4 Interpretation

| Topic | Interpretation |
| --- | --- |
| Jira and Firebase flow | The custom tech-support workflow passed locally under mocked external API conditions |
| Mongo-backed request flow | The technical-support asset-request flow passed locally with service substitutions |
| Ollama chatbot flow | The chatbot route and intent-to-query behavior passed locally with mocked Ollama responses |
| Live external systems | Not validated in this run; these tests verify application behavior against controlled fakes, not real SaaS connectivity |

## 4. Known Issues

### 4.1 Observed from local automated test execution

| Issue | Impact | Evidence |
| --- | --- | --- |
| `tests/Unit/CustomFieldTest.php` has `6` failing non-Latin slug tests | Unit suite is not clean; Unicode slug expectations are unstable in the current test data/runtime combination | Failures in Chinese, Japanese, Korean, non-Latin European, Turkish, and Arabic slug tests |
| `tests/Unit/LdapTest.php` has `6` failing LDAP tests | Unit suite is not clean in environments without full LDAP constant support | Failures include `Undefined constant "App\\Models\\LDAP_OPT_REFERRALS"` |

### 4.2 Likely root-cause notes

| Issue area | Likely cause |
| --- | --- |
| Custom field Unicode slug tests | The fixture strings in `tests/Unit/CustomFieldTest.php` appear mojibake-encoded, which makes transliteration assertions environment-sensitive |
| LDAP unit tests | LDAP tests are environment-sensitive and the file is explicitly marked with `#[Group('ldap')]`; `TESTING.md` also notes that LDAP-related tests may need to be run separately or excluded if the environment lacks the right support |

### 4.3 Documented integration risks in the codebase

| Risk | Operational effect | Source |
| --- | --- | --- |
| Jira create succeeds but Firebase writeback fails | A real Jira issue may exist while the local ticket record is not updated, requiring manual reconciliation | `docs/external-system-integrations-jira.md` |
| Missing Jira config or permissions | Approved tickets cannot complete the Jira handoff flow | `docs/external-system-integrations-jira.md` |
| Live external service readiness is not covered by mocked tests | A green local integration test run does not guarantee real environment credentials, permissions, or network connectivity are correct | current test design in `tests/Feature/Requests/Ui/*` |

## 5. Summary

| Area | Status |
| --- | --- |
| Unit suite health | `Partially passing` |
| Custom integration feature tests | `Passing` |
| Overall automated testing posture | `Strong breadth, but not fully green` |
| Main blockers to clean test status | Unicode slug test instability and LDAP environment-specific failures |
