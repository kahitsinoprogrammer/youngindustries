<?php

namespace Tests\Feature\Requests\Ui;

use App\Models\User;
use App\Services\AssetRequestTechnicalSupportMongoService;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TechSupportRequestTicketTest extends TestCase
{
    protected function configureFirebase(): void
    {
        config([
            'services.firebase.project_id' => 'demo-project',
            'services.firebase.database' => '(default)',
            'services.firebase.helpdesk_collection' => 'helpdesk_tickets',
            'services.firebase.service_account_json' => json_encode([
                'type' => 'service_account',
                'project_id' => 'demo-project',
                'private_key_id' => 'test-key',
                'private_key' => $this->testPrivateKey(),
                'client_email' => 'firebase-adminsdk@example.iam.gserviceaccount.com',
                'client_id' => '1234567890',
            ]),
            'services.firebase.token_uri' => 'https://oauth2.googleapis.com/token',
            'services.firebase.firestore_base_url' => 'https://firestore.googleapis.com/v1',
        ]);
    }

    protected function fakeFirebaseRequests(): void
    {
        Http::fake([
            'https://oauth2.googleapis.com/token' => Http::response([
                'access_token' => 'firebase-access-token',
                'token_type' => 'Bearer',
                'expires_in' => 3600,
            ], 200),
            'https://firestore.googleapis.com/v1/projects/demo-project/databases/(default)/documents/helpdesk_tickets' => Http::response([
                'name' => 'projects/demo-project/databases/(default)/documents/helpdesk_tickets/doc-123',
            ], 200),
        ]);
    }

    protected function configureJira(): void
    {
        config([
            'services.jira.base_url' => 'https://example.atlassian.net',
            'services.jira.email' => 'jira-bot@example.com',
            'services.jira.api_token' => 'jira-api-token',
            'services.jira.project_key' => 'HELP',
            'services.jira.timeout' => 30,
            'services.jira.issue_types' => [
                'Task' => 'Task',
                'Bug' => 'Bug',
                'Incident' => 'Incident',
                'Service Request' => 'Service Request',
            ],
        ]);
    }

    public function test_authenticated_user_can_view_request_ticket_page()
    {
        $this->actingAs(User::factory()->create())
            ->get(route('tech-support.request-ticket'))
            ->assertOk()
            ->assertSeeText('Request Ticket')
            ->assertSeeText('Jira Issue Type');
    }

    public function test_authenticated_user_can_submit_request_ticket_form()
    {
        $user = User::factory()->create(['email' => 'requester@example.com']);
        $this->configureFirebase();
        $this->fakeFirebaseRequests();

        $this->actingAs($user)
            ->followingRedirects()
            ->post(route('tech-support.request-ticket.store'), [
                'requester_name' => $user->display_name ?: $user->username,
                'requester_email' => 'requester@example.com',
                'department' => 'IT',
                'contact_number' => '123456789',
                'item_name' => 'Dell Latitude 5440',
                'asset_tag' => 'AST-1001',
                'serial_number' => 'SN-ABC-123',
                'location' => 'HQ 4th Floor',
                'issue_category' => 'repair',
                'jira_issue_type' => 'Service Request',
                'priority' => 'High',
                'summary' => 'Laptop repair request for Dell Latitude 5440',
                'description' => 'The device does not power on and needs hardware diagnostics.',
            ])
            ->assertOk()
            ->assertSeeText('Ticket request saved to Firebase successfully.')
            ->assertSeeText('Firebase Save Preview')
            ->assertSeeText('doc-123')
            ->assertSeeText('For Approval')
            ->assertSeeText('Laptop repair request for Dell Latitude 5440')
            ->assertSeeText('Service Request');

        Http::assertSent(function (Request $request) {
            return $request->url() === 'https://firestore.googleapis.com/v1/projects/demo-project/databases/(default)/documents/helpdesk_tickets'
                && data_get($request->data(), 'fields.status.stringValue') === 'for approval'
                && data_get($request->data(), 'fields.ticket.mapValue.fields.summary.stringValue') === 'Laptop repair request for Dell Latitude 5440';
        });
    }

    public function test_request_ticket_form_validates_required_fields()
    {
        $this->actingAs(User::factory()->create())
            ->post(route('tech-support.request-ticket.store'), [])
            ->assertSessionHasErrors([
                'requester_name',
                'requester_email',
                'item_name',
                'issue_category',
                'jira_issue_type',
                'priority',
                'summary',
                'description',
            ]);
    }

    public function test_authenticated_user_can_view_ticket_status_page()
    {
        $this->configureFirebase();

        Http::fake([
            'https://oauth2.googleapis.com/token' => Http::response([
                'access_token' => 'firebase-access-token',
                'token_type' => 'Bearer',
                'expires_in' => 3600,
            ], 200),
            'https://firestore.googleapis.com/v1/projects/demo-project/databases/(default)/documents/helpdesk_tickets*' => Http::response([
                'documents' => [
                    $this->ticketDocument('doc-123', 'for approval'),
                    $this->ticketDocument('doc-456', 'closed', [
                        'closed_at' => '2026-05-06T16:00:00+08:00',
                        'closed_by_name' => 'Terry Support',
                        'resolution' => 'Power adapter replaced and device is working normally.',
                    ]),
                ],
            ], 200),
        ]);

        $this->actingAs(User::factory()->create())
            ->get(route('tech-support.ticket-status'))
            ->assertOk()
            ->assertSeeText('Ticket Status')
            ->assertSeeText('Laptop repair request for Dell Latitude 5440')
            ->assertSeeText('Power adapter replaced and device is working normally.');
    }

    public function test_ticket_status_page_supports_search_and_status_filter()
    {
        $this->configureFirebase();

        Http::fake([
            'https://oauth2.googleapis.com/token' => Http::response([
                'access_token' => 'firebase-access-token',
                'token_type' => 'Bearer',
                'expires_in' => 3600,
            ], 200),
            'https://firestore.googleapis.com/v1/projects/demo-project/databases/(default)/documents/helpdesk_tickets*' => Http::response([
                'documents' => [
                    $this->ticketDocument('doc-123', 'for approval'),
                    $this->ticketDocument('doc-456', 'closed', [
                        'ticket' => [
                            'issue_category' => 'network',
                            'jira_issue_type' => 'Incident',
                            'priority' => 'Medium',
                            'summary' => 'Closed network outage ticket',
                            'description' => 'Branch network connectivity was restored.',
                        ],
                        'closed_at' => '2026-05-06T16:00:00+08:00',
                        'closed_by_name' => 'Terry Support',
                        'resolution' => 'Router was rebooted and uplink settings were corrected.',
                    ]),
                ],
            ], 200),
        ]);

        $this->actingAs(User::factory()->create())
            ->get(route('tech-support.ticket-status', [
                'search' => 'router',
                'status' => 'closed',
            ]))
            ->assertOk()
            ->assertSeeText('Closed network outage ticket')
            ->assertSeeText('Router was rebooted and uplink settings were corrected.')
            ->assertDontSeeText('Laptop repair request for Dell Latitude 5440');
    }

    public function test_technical_support_user_can_view_approve_ticket_page()
    {
        $this->mockTechnicalSupportAccess(true);
        $this->configureFirebase();

        Http::fake([
            'https://oauth2.googleapis.com/token' => Http::response([
                'access_token' => 'firebase-access-token',
                'token_type' => 'Bearer',
                'expires_in' => 3600,
            ], 200),
            'https://firestore.googleapis.com/v1/projects/demo-project/databases/(default)/documents/helpdesk_tickets*' => Http::response([
                'documents' => [
                    $this->ticketDocument('doc-123', 'for approval'),
                    $this->ticketDocument('doc-456', 'approved', [
                        'ticket' => [
                            'issue_category' => 'hardware',
                            'jira_issue_type' => 'Task',
                            'priority' => 'Medium',
                            'summary' => 'Approved ticket already moved to resolve queue',
                            'description' => 'Approved items should not stay on the approval page.',
                        ],
                        'reviewed_at' => '2026-05-06T11:00:00+08:00',
                        'reviewed_by_name' => 'Terry Support',
                        'approver_remarks' => 'Approved for processing.',
                    ]),
                ],
            ], 200),
        ]);

        $this->actingAs(User::factory()->create())
            ->get(route('tech-support.approve-ticket'))
            ->assertOk()
            ->assertSeeText('Approve Ticket')
            ->assertSeeText('Laptop repair request for Dell Latitude 5440')
            ->assertSeeText('Pending')
            ->assertDontSeeText('Approved ticket already moved to resolve queue');
    }

    public function test_technical_support_user_can_view_jira_diagnostics_page()
    {
        $this->mockTechnicalSupportAccess(true);
        $this->configureJira();

        Http::fake(function (Request $request) {
            if ($request->method() === 'GET' && $request->url() === 'https://example.atlassian.net/rest/api/3/myself') {
                return Http::response([
                    'accountId' => 'abc-123',
                    'displayName' => 'Jira Bot',
                    'emailAddress' => 'jira-bot@example.com',
                    'active' => true,
                ], 200);
            }

            if ($request->method() === 'GET' && $request->url() === 'https://example.atlassian.net/rest/api/3/project/HELP') {
                return Http::response([
                    'id' => '10000',
                    'key' => 'HELP',
                    'name' => 'Help Desk',
                    'projectTypeKey' => 'service_desk',
                    'style' => 'next-gen',
                ], 200);
            }

            if ($request->method() === 'GET' && $request->url() === 'https://example.atlassian.net/rest/api/3/mypermissions?projectKey=HELP&permissions=BROWSE_PROJECTS,CREATE_ISSUES') {
                return Http::response([
                    'permissions' => [
                        'BROWSE_PROJECTS' => [
                            'havePermission' => true,
                        ],
                        'CREATE_ISSUES' => [
                            'havePermission' => true,
                        ],
                    ],
                ], 200);
            }

            if ($request->method() === 'GET' && $request->url() === 'https://example.atlassian.net/rest/api/3/issuetype/project?projectId=10000') {
                return Http::response([
                    ['name' => 'Task'],
                    ['name' => 'Bug'],
                    ['name' => 'Incident'],
                    ['name' => 'Service Request'],
                ], 200);
            }

            return Http::response([], 404);
        });

        $this->actingAs(User::factory()->create())
            ->get(route('tech-support.jira-diagnostics'))
            ->assertOk()
            ->assertSeeText('Jira Diagnostics')
            ->assertSeeText('Jira Bot')
            ->assertSeeText('Help Desk')
            ->assertSeeText('Browse Projects')
            ->assertSeeText('Create Issues')
            ->assertSeeText('Service Request');
    }

    public function test_regular_user_cannot_view_jira_diagnostics_page()
    {
        $this->mockTechnicalSupportAccess(false);

        $this->actingAs(User::factory()->create())
            ->get(route('tech-support.jira-diagnostics'))
            ->assertForbidden();
    }

    public function test_regular_user_cannot_view_approve_ticket_page()
    {
        $this->mockTechnicalSupportAccess(false);

        $this->actingAs(User::factory()->create())
            ->get(route('tech-support.approve-ticket'))
            ->assertForbidden();
    }

    public function test_technical_support_user_can_approve_ticket_request()
    {
        $this->mockTechnicalSupportAccess(true);
        $this->configureFirebase();
        $this->configureJira();

        Http::fake(function (Request $request) {
            if ($request->url() === 'https://oauth2.googleapis.com/token') {
                return Http::response([
                    'access_token' => 'firebase-access-token',
                    'token_type' => 'Bearer',
                    'expires_in' => 3600,
                ], 200);
            }

            if ($request->method() === 'GET' && $request->url() === 'https://firestore.googleapis.com/v1/projects/demo-project/databases/(default)/documents/helpdesk_tickets/doc-123') {
                return Http::response($this->ticketDocument('doc-123', 'for approval'), 200);
            }

            if ($request->method() === 'POST' && $request->url() === 'https://example.atlassian.net/rest/api/3/issue') {
                return Http::response([
                    'id' => '10001',
                    'key' => 'HELP-123',
                    'self' => 'https://example.atlassian.net/rest/api/3/issue/10001',
                ], 201);
            }

            if ($request->method() === 'PATCH' && str_starts_with($request->url(), 'https://firestore.googleapis.com/v1/projects/demo-project/databases/(default)/documents/helpdesk_tickets/doc-123?')) {
                return Http::response([
                    'name' => 'projects/demo-project/databases/(default)/documents/helpdesk_tickets/doc-123',
                ], 200);
            }

            return Http::response([], 404);
        });

        $reviewer = User::factory()->create(['first_name' => 'Terry', 'last_name' => 'Support']);

        $this->actingAs($reviewer)
            ->post(route('tech-support.approve-ticket.review', 'doc-123'), [
                'ticket_id' => 'doc-123',
                'approval_status' => 'approved',
                'approver_remarks' => '',
            ])
            ->assertRedirect(route('tech-support.approve-ticket'))
            ->assertSessionHas('success', 'Ticket request approved successfully and Jira issue HELP-123 was created.');

        Http::assertSent(function (Request $request) {
            return $request->method() === 'PATCH'
                && str_starts_with($request->url(), 'https://firestore.googleapis.com/v1/projects/demo-project/databases/(default)/documents/helpdesk_tickets/doc-123?')
                && data_get($request->data(), 'fields.status.stringValue') === 'approved'
                && data_get($request->data(), 'fields.reviewed_by_name.stringValue') === 'Terry Support'
                && data_get($request->data(), 'fields.jira.mapValue.fields.issue_key.stringValue') === 'HELP-123';
        });

        Http::assertSent(function (Request $request) {
            return $request->method() === 'POST'
                && $request->url() === 'https://example.atlassian.net/rest/api/3/issue'
                && data_get($request->data(), 'fields.project.key') === 'HELP'
                && data_get($request->data(), 'fields.issuetype.name') === 'Service Request'
                && data_get($request->data(), 'fields.summary') === 'Laptop repair request for Dell Latitude 5440';
        });
    }

    public function test_technical_support_user_can_view_resolve_ticket_page()
    {
        $this->mockTechnicalSupportAccess(true);
        $this->configureFirebase();

        Http::fake([
            'https://oauth2.googleapis.com/token' => Http::response([
                'access_token' => 'firebase-access-token',
                'token_type' => 'Bearer',
                'expires_in' => 3600,
            ], 200),
            'https://firestore.googleapis.com/v1/projects/demo-project/databases/(default)/documents/helpdesk_tickets*' => Http::response([
                'documents' => [
                    $this->ticketDocument('doc-123', 'for approval'),
                    $this->ticketDocument('doc-456', 'approved', [
                        'ticket' => [
                            'issue_category' => 'hardware',
                            'jira_issue_type' => 'Task',
                            'priority' => 'Medium',
                            'summary' => 'Approved laptop ready for repair resolution',
                            'description' => 'This approved ticket should be resolved by tech support.',
                        ],
                        'reviewed_at' => '2026-05-06T11:00:00+08:00',
                        'reviewed_by_name' => 'Terry Support',
                        'approver_remarks' => 'Approved for processing.',
                    ]),
                ],
            ], 200),
        ]);

        $this->actingAs(User::factory()->create())
            ->get(route('tech-support.resolve-ticket'))
            ->assertOk()
            ->assertSeeText('Resolve Ticket')
            ->assertSeeText('Approved laptop ready for repair resolution')
            ->assertDontSeeText('Laptop repair request for Dell Latitude 5440')
            ->assertSeeText('Close Ticket');
    }

    public function test_regular_user_cannot_view_resolve_ticket_page()
    {
        $this->mockTechnicalSupportAccess(false);

        $this->actingAs(User::factory()->create())
            ->get(route('tech-support.resolve-ticket'))
            ->assertForbidden();
    }

    public function test_technical_support_user_can_close_approved_ticket_request()
    {
        $this->mockTechnicalSupportAccess(true);
        $this->configureFirebase();

        Http::fake(function (Request $request) {
            if ($request->url() === 'https://oauth2.googleapis.com/token') {
                return Http::response([
                    'access_token' => 'firebase-access-token',
                    'token_type' => 'Bearer',
                    'expires_in' => 3600,
                ], 200);
            }

            if ($request->method() === 'GET' && $request->url() === 'https://firestore.googleapis.com/v1/projects/demo-project/databases/(default)/documents/helpdesk_tickets/doc-456') {
                return Http::response($this->ticketDocument('doc-456', 'approved', [
                    'reviewed_at' => '2026-05-06T11:00:00+08:00',
                    'reviewed_by_name' => 'Terry Support',
                ]), 200);
            }

            if ($request->method() === 'PATCH' && str_starts_with($request->url(), 'https://firestore.googleapis.com/v1/projects/demo-project/databases/(default)/documents/helpdesk_tickets/doc-456?')) {
                return Http::response([
                    'name' => 'projects/demo-project/databases/(default)/documents/helpdesk_tickets/doc-456',
                ], 200);
            }

            return Http::response([], 404);
        });

        $resolver = User::factory()->create(['first_name' => 'Riley', 'last_name' => 'Resolver']);

        $this->actingAs($resolver)
            ->post(route('tech-support.resolve-ticket.close', 'doc-456'), [
                'ticket_id' => 'doc-456',
                'resolution' => 'Replaced the charger and confirmed the laptop now powers on normally.',
            ])
            ->assertRedirect(route('tech-support.resolve-ticket'))
            ->assertSessionHas('success', 'Ticket request closed successfully.');

        Http::assertSent(function (Request $request) {
            return $request->method() === 'PATCH'
                && str_starts_with($request->url(), 'https://firestore.googleapis.com/v1/projects/demo-project/databases/(default)/documents/helpdesk_tickets/doc-456?')
                && data_get($request->data(), 'fields.status.stringValue') === 'closed'
                && data_get($request->data(), 'fields.closed_by_name.stringValue') === 'Riley Resolver'
                && data_get($request->data(), 'fields.resolution.stringValue') === 'Replaced the charger and confirmed the laptop now powers on normally.';
        });
    }

    public function test_closing_ticket_request_requires_resolution()
    {
        $this->mockTechnicalSupportAccess(true);

        $this->actingAs(User::factory()->create())
            ->post(route('tech-support.resolve-ticket.close', 'doc-456'), [
                'ticket_id' => 'doc-456',
                'resolution' => '',
            ])
            ->assertSessionHasErrors(['resolution']);
    }

    public function test_rejecting_ticket_request_requires_remarks()
    {
        $this->mockTechnicalSupportAccess(true);

        $this->actingAs(User::factory()->create())
            ->post(route('tech-support.approve-ticket.review', 'doc-123'), [
                'ticket_id' => 'doc-123',
                'approval_status' => 'rejected',
                'approver_remarks' => '',
            ])
            ->assertSessionHasErrors(['approver_remarks']);
    }

    protected function testPrivateKey(): string
    {
        return <<<'KEY'
-----BEGIN PRIVATE KEY-----
MIIEvgIBADANBgkqhkiG9w0BAQEFAASCBKgwggSkAgEAAoIBAQDBKexb4qrXZ1aP
mIP80hQvKPFEmq6g1eAW6+7pP8bNolwdAh1+CYyHlS+QQbh+SMXimcYHhgtYWXU6
eNB2dX3Re2diH7k3QF6Yj6n9BIwaFEIz/u+/JCifAxRhLfoS0CW1bYj36BXQGaoL
3D77N58XQWbdt03k9JExEp2Dd0qsP7Lxdse0lAYU18g2m2H9MSMVrXQJzHwt4YHp
+x5CVM7+SgQ3KMMd0/YxFOm7y4RyovK3cWbGeYK0/vpm6d4N70tx+lnAbM31N6g5
X/S+N4leTZNeL77T23VSH3nC2sRZE/Q+4jRp4KzS2isN5dvyo6AAiuiDNuR0ZHWm
ZES3pXS5AgMBAAECggEAEShXe8nN4cRwtc9nT4DhzjLA1xX7AxXWPqIXGQ60T5Ax
dL3VKOqCFt8w2soPdnRp6X+L4P7On3tLsQEuCnN5v3PDH5bgJiN4vCgqH3b2y0mW
Fsqr3NyQVQY7FY/5wKU/6gCVe5Cp1mH3Ji2PDBhdhVkveEI4ldO5mPE0At8SEAr6
VCBfb/nNxKHU2i4B/55x68VgEYY2cxOa+wpzWkIx3YSHmOo2ndZqv3GDN4doW5Jf
2YCaCbZuuhJG4U+aqGw33Qn56a+yBMV4vWwJByss4Xo/FMJorWdEE4Kq0sXK6V5y
fvNNF+WwPe4P7qA/Fjlwmc6qJYBuVLGQvHkRHa5+cQKBgQDwVx4M0x4AiEqwfV1n
3+wG0WbzseA7HlK6ljvZ4gLY9ZciE6YvDjgkmck5tQxGZVtIMkUj2TeR94iY6zs+
3z5SSNG3jR9s+U/mS6SgBFXvP63QJvO50F8ClfI6R8drAkNKu5n5h0IdQ7dmbzz3
etBOP71mGN5+6wYBD7P0LPqKGwKBgQDMiXmWf/Fm7dquYEl6v+71dVl61gaP4RkP
yCGcZDz5cIab9RLvJtYJRn5mWgL9jODu4cbg5Qv2qVpDMkTs0x2CHgPCDl4iTQl5
SsVf3FYR8x5m5mJ7ETSH5H2C81WNqaNpck2b5vPdq8k3LCfCf9Jm2CRMuY4E+J4T
lxvjlxW+gQKBgQDY8M9E8Oyx90NREXLbsvXVVBd2ShNl+UBKTKPWuStiXBd+DL8h
zOih1uBtToPFM/86KK45tUxQkY+TFUaQ7dkIy3VoZrsKh1cE2WEbNWzfiLw6YvTM
u7a8Y4Tlo0YCGyigW/fnxqQz4d8ErV+0h5AN6QJ4GIRxRrzt5HwFHmx+GwKBgQC/
Qh0izRJdhMFlvZq1C5OAKirFbfhI+eFwDQE0lJwSK/BaoM4YcLEv98KPXXvWN6Sc
biEiWkL9UAwVrKxpcVgfUGYpQlnXpgEZTih4X7cJmN5Y7D6i1KniCp3FAkAep65a
cvv2jujNW2c7ohjQHW+ztBVbXw0V+vrAf+G5JjI0gQKBgGczmM0Pi1vnBhwbv1r/
4j5MZ9C9O4d4QbQAD22uO9fKQ8b9M9NaVh1j7AMMqIYNPA3oG1t3v6r1tqghn7UD
sG9qJl4nhbVnmgN5WHp4SJ8muAxdv8M0fm6WZVXZ8Cy6vv5JxTKx7cR3Ru+BlXyM
ZqMT0vVrJQOoD1r3LkHYySoK
-----END PRIVATE KEY-----
KEY;
    }

    protected function mockTechnicalSupportAccess(bool $isTechnicalSupport): void
    {
        $this->app->instance(AssetRequestTechnicalSupportMongoService::class, new class($isTechnicalSupport) extends AssetRequestTechnicalSupportMongoService
        {
            public function __construct(private readonly bool $isTechnicalSupport)
            {
            }

            public function isTechnicalSupport(int $userId): bool
            {
                return $this->isTechnicalSupport;
            }
        });
    }

    /**
     * @param  array<string, string>  $overrides
     * @return array<string, mixed>
     */
    protected function ticketDocument(string $documentId, string $status, array $overrides = []): array
    {
        $payload = array_merge([
            'module' => 'tech_support',
            'request_type' => 'repair_ticket',
            'status' => $status,
            'submitted_by' => 101,
            'submitted_at' => '2026-05-06T09:00:00+08:00',
            'updated_at' => '2026-05-06T09:00:00+08:00',
            'reviewed_at' => '',
            'reviewed_by' => 0,
            'reviewed_by_name' => '',
            'closed_at' => '',
            'closed_by' => 0,
            'closed_by_name' => '',
            'approver_remarks' => '',
            'resolution' => '',
            'jira' => [
                'issue_id' => '',
                'issue_key' => '',
                'issue_url' => '',
                'created_at' => '',
            ],
            'requester' => [
                'employee_number' => 101,
                'name' => 'Jane Requester',
                'email' => 'jane@example.com',
                'department' => 'IT',
                'contact_number' => '123456789',
            ],
            'asset' => [
                'item_name' => 'Dell Latitude 5440',
                'asset_tag' => 'AST-1001',
                'serial_number' => 'SN-ABC-123',
                'location' => 'HQ 4th Floor',
            ],
            'ticket' => [
                'issue_category' => 'repair',
                'jira_issue_type' => 'Service Request',
                'priority' => 'High',
                'summary' => 'Laptop repair request for Dell Latitude 5440',
                'description' => 'The device does not power on and needs hardware diagnostics.',
            ],
        ], $overrides);

        return [
            'name' => 'projects/demo-project/databases/(default)/documents/helpdesk_tickets/'.$documentId,
            'createTime' => '2026-05-06T09:00:00.000000Z',
            'updateTime' => '2026-05-06T09:00:00.000000Z',
            'fields' => $this->toFirestoreFields($payload),
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    protected function toFirestoreFields(array $payload): array
    {
        $fields = [];

        foreach ($payload as $key => $value) {
            $fields[$key] = $this->toFirestoreValue($value);
        }

        return $fields;
    }

    /**
     * @return array<string, mixed>
     */
    protected function toFirestoreValue(mixed $value): array
    {
        if (is_string($value)) {
            return ['stringValue' => $value];
        }

        if (is_int($value)) {
            return ['integerValue' => (string) $value];
        }

        if (is_bool($value)) {
            return ['booleanValue' => $value];
        }

        if (is_array($value)) {
            if (array_is_list($value)) {
                return [
                    'arrayValue' => [
                        'values' => array_map(fn (mixed $item): array => $this->toFirestoreValue($item), $value),
                    ],
                ];
            }

            return [
                'mapValue' => [
                    'fields' => $this->toFirestoreFields($value),
                ],
            ];
        }

        return ['nullValue' => null];
    }
}
