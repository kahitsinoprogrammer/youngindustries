<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Services\FirebaseHelpdeskService;
use App\Services\JiraIssueService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use RuntimeException;
use Throwable;

class TechSupportController extends Controller
{
    public function __construct(
        private readonly FirebaseHelpdeskService $firebaseHelpdeskService,
        private readonly JiraIssueService $jiraIssueService
    ) {
    }

    public function requestTicket(Request $request): View
    {
        return view('account.tech-support.request-ticket', [
            'user' => $request->user(),
            'ticketRequestPreview' => session('ticket_request_preview'),
        ]);
    }

    public function storeRequestTicket(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'requester_name' => ['required', 'string', 'max:191'],
            'requester_email' => ['required', 'email', 'max:191'],
            'department' => ['nullable', 'string', 'max:191'],
            'contact_number' => ['nullable', 'string', 'max:50'],
            'item_name' => ['required', 'string', 'max:191'],
            'asset_tag' => ['nullable', 'string', 'max:100'],
            'serial_number' => ['nullable', 'string', 'max:100'],
            'location' => ['nullable', 'string', 'max:191'],
            'issue_category' => ['required', 'string', 'in:repair,hardware,software,network,account,other'],
            'jira_issue_type' => ['required', 'string', 'in:Task,Bug,Incident,Service Request'],
            'priority' => ['required', 'string', 'in:Low,Medium,High,Highest'],
            'summary' => ['required', 'string', 'max:191'],
            'description' => ['required', 'string', 'max:3000'],
        ]);

        $timestamp = now()->toIso8601String();
        $ticketRequestPayload = [
            'module' => 'tech_support',
            'request_type' => 'repair_ticket',
            'status' => 'for approval',
            'submitted_by' => (int) $request->user()->id,
            'submitted_at' => $timestamp,
            'updated_at' => $timestamp,
            'requester' => [
                'employee_number' => (int) $request->user()->id,
                'name' => $validated['requester_name'],
                'email' => $validated['requester_email'],
                'department' => $validated['department'] ?? '',
                'contact_number' => $validated['contact_number'] ?? '',
            ],
            'asset' => [
                'item_name' => $validated['item_name'],
                'asset_tag' => $validated['asset_tag'] ?? '',
                'serial_number' => $validated['serial_number'] ?? '',
                'location' => $validated['location'] ?? '',
            ],
            'ticket' => [
                'issue_category' => $validated['issue_category'],
                'jira_issue_type' => $validated['jira_issue_type'],
                'priority' => $validated['priority'],
                'summary' => $validated['summary'],
                'description' => $validated['description'],
            ],
            'jira' => [
                'issue_key' => '',
                'issue_url' => '',
                'created_at' => '',
            ],
        ];

        try {
            $firebaseDocument = $this->firebaseHelpdeskService->createRepairTicket($ticketRequestPayload);
        } catch (Throwable $exception) {
            report($exception);

            return redirect()
                ->route('tech-support.request-ticket')
                ->withInput()
                ->with('error', 'Ticket request could not be saved to Firebase. Please verify the Firebase configuration.');
        }

        $ticketRequestPreview = [
            'firebase_document_name' => $firebaseDocument['document_name'],
            'firebase_document_id' => $firebaseDocument['document_id'],
            'requester_name' => $ticketRequestPayload['requester']['name'],
            'requester_email' => $ticketRequestPayload['requester']['email'],
            'item_name' => $ticketRequestPayload['asset']['item_name'],
            'issue_type' => $ticketRequestPayload['ticket']['jira_issue_type'],
            'priority' => $ticketRequestPayload['ticket']['priority'],
            'summary' => $ticketRequestPayload['ticket']['summary'],
            'description' => $ticketRequestPayload['ticket']['description'],
            'status' => 'For Approval',
        ];

        return redirect()
            ->route('tech-support.request-ticket')
            ->with('success', 'Ticket request saved to Firebase successfully.')
            ->with('ticket_request_preview', $ticketRequestPreview);
    }

    public function approveTicket(): View
    {
        $firebaseError = null;
        $requests = collect();

        try {
            $requests = collect($this->firebaseHelpdeskService->listRepairTickets())
                ->map(fn (array $ticket): array => $this->mapTicketRequestForView($ticket))
                ->filter(fn (array $ticket): bool => $ticket['status'] === 'pending')
                ->sort(function (array $left, array $right): int {
                    return strcmp((string) ($right['submitted_at'] ?? ''), (string) ($left['submitted_at'] ?? ''));
                })
                ->values();
        } catch (Throwable $exception) {
            report($exception);
            $firebaseError = 'Ticket requests could not be loaded from Firebase. Please verify the Firebase configuration.';
        }

        return view('account.tech-support.approve-ticket', compact('requests', 'firebaseError'));
    }

    public function jiraDiagnostics(): View
    {
        $diagnostics = $this->jiraIssueService->diagnostics();

        return view('account.tech-support.jira-diagnostics', compact('diagnostics'));
    }

    public function ticketStatus(Request $request): View
    {
        $firebaseError = null;
        $requests = collect();
        $search = trim((string) $request->query('search', ''));
        $statusOptions = $this->ticketStatusOptions();
        $statusFilter = strtolower(trim((string) $request->query('status', '')));

        if (($statusFilter !== '') && (! array_key_exists($statusFilter, $statusOptions))) {
            $statusFilter = '';
        }

        try {
            $requests = collect($this->firebaseHelpdeskService->listRepairTickets())
                ->map(fn (array $ticket): array => $this->mapTicketRequestForView($ticket))
                ->filter(fn (array $ticket): bool => $this->matchesTicketStatusFilters($ticket, $search, $statusFilter))
                ->sort(function (array $left, array $right): int {
                    return strcmp((string) ($right['updated_at'] ?? $right['submitted_at'] ?? ''), (string) ($left['updated_at'] ?? $left['submitted_at'] ?? ''));
                })
                ->values();
        } catch (Throwable $exception) {
            report($exception);
            $firebaseError = 'Ticket status records could not be loaded from Firebase. Please verify the Firebase configuration.';
        }

        return view('account.tech-support.ticket-status', compact('requests', 'firebaseError', 'search', 'statusFilter', 'statusOptions'));
    }

    public function resolveTicket(): View
    {
        $firebaseError = null;
        $requests = collect();

        try {
            $requests = collect($this->firebaseHelpdeskService->listRepairTickets())
                ->map(fn (array $ticket): array => $this->mapTicketRequestForView($ticket))
                ->filter(fn (array $ticket): bool => $ticket['status'] === 'approved')
                ->sort(function (array $left, array $right): int {
                    return strcmp((string) ($right['reviewed_at'] ?? $right['submitted_at'] ?? ''), (string) ($left['reviewed_at'] ?? $left['submitted_at'] ?? ''));
                })
                ->values();
        } catch (Throwable $exception) {
            report($exception);
            $firebaseError = 'Approved ticket requests could not be loaded from Firebase. Please verify the Firebase configuration.';
        }

        return view('account.tech-support.resolve-ticket', compact('requests', 'firebaseError'));
    }

    public function reviewTicket(Request $request, string $ticketId): RedirectResponse
    {
        $validated = $request->validate([
            'approval_status' => ['required', 'in:approved,rejected'],
            'approver_remarks' => ['nullable', 'string', 'max:2000', 'required_if:approval_status,rejected'],
            'ticket_id' => ['required', 'string'],
        ]);
        $approvalStatus = $validated['approval_status'];
        $successMessage = $approvalStatus === 'approved'
            ? 'Ticket request approved successfully.'
            : 'Ticket request rejected successfully.';
        $additionalFields = [];

        if ($validated['ticket_id'] !== $ticketId) {
            return redirect()
                ->route('tech-support.approve-ticket')
                ->with('error', 'The selected ticket request could not be matched.');
        }

        try {
            if ($approvalStatus === 'approved') {
                $ticket = $this->firebaseHelpdeskService->findRepairTicket($ticketId);

                if (! is_array($ticket)) {
                    throw new RuntimeException('The selected ticket request could not be found.');
                }

                $jiraIssue = $this->jiraIssueService->createIssueFromRepairTicket($this->mapTicketRequestForView($ticket));
                $additionalFields = [
                    'jira' => [
                        'issue_id' => $jiraIssue['issue_id'] ?? '',
                        'issue_key' => $jiraIssue['issue_key'] ?? '',
                        'issue_url' => $jiraIssue['issue_url'] ?? '',
                        'created_at' => $jiraIssue['created_at'] ?? '',
                    ],
                ];
                $successMessage = sprintf(
                    'Ticket request approved successfully and Jira issue %s was created.',
                    $jiraIssue['issue_key'] ?? 'unknown'
                );
            }

            $this->firebaseHelpdeskService->reviewRepairTicket(
                $ticketId,
                $approvalStatus,
                (int) $request->user()->id,
                (string) ($request->user()->display_name ?: $request->user()->username),
                trim((string) ($validated['approver_remarks'] ?? '')),
                $additionalFields
            );
        } catch (Throwable $exception) {
            report($exception);
            $errorMessage = $exception instanceof RuntimeException
                ? $exception->getMessage()
                : 'Ticket request could not be updated in Firebase. Please verify the Firebase configuration.';

            if (($approvalStatus === 'approved') && ($additionalFields !== []) && (! ($exception instanceof RuntimeException))) {
                $errorMessage = 'The Jira issue was created, but the ticket request could not be updated in Firebase. Please reconcile the Jira ticket manually before retrying.';
            }

            return redirect()
                ->route('tech-support.approve-ticket')
                ->withInput()
                ->with('error', $errorMessage);
        }

        return redirect()
            ->route('tech-support.approve-ticket')
            ->with('success', $successMessage);
    }

    public function closeTicket(Request $request, string $ticketId): RedirectResponse
    {
        $validated = $request->validate([
            'resolution' => ['required', 'string', 'max:2000'],
            'ticket_id' => ['required', 'string'],
        ]);

        if ($validated['ticket_id'] !== $ticketId) {
            return redirect()
                ->route('tech-support.resolve-ticket')
                ->with('error', 'The selected ticket request could not be matched.');
        }

        try {
            $this->firebaseHelpdeskService->closeRepairTicket(
                $ticketId,
                trim((string) $validated['resolution']),
                (int) $request->user()->id,
                (string) ($request->user()->display_name ?: $request->user()->username)
            );
        } catch (Throwable $exception) {
            report($exception);

            return redirect()
                ->route('tech-support.resolve-ticket')
                ->withInput()
                ->with('error', $exception instanceof RuntimeException
                    ? $exception->getMessage()
                    : 'Ticket request could not be closed in Firebase. Please verify the Firebase configuration.');
        }

        return redirect()
            ->route('tech-support.resolve-ticket')
            ->with('success', 'Ticket request closed successfully.');
    }

    /**
     * @param  array<string, mixed>  $ticket
     * @return array<string, mixed>
     */
    protected function mapTicketRequestForView(array $ticket): array
    {
        return [
            'id' => (string) ($ticket['document_id'] ?? ''),
            'document_name' => (string) ($ticket['document_name'] ?? ''),
            'status' => $this->normalizeTicketStatus((string) ($ticket['status'] ?? '')),
            'submitted_at' => (string) ($ticket['submitted_at'] ?? ''),
            'updated_at' => (string) ($ticket['updated_at'] ?? ''),
            'reviewed_at' => (string) ($ticket['reviewed_at'] ?? ''),
            'reviewed_by' => (int) ($ticket['reviewed_by'] ?? 0),
            'reviewed_by_name' => (string) ($ticket['reviewed_by_name'] ?? ''),
            'closed_at' => (string) ($ticket['closed_at'] ?? ''),
            'closed_by' => (int) ($ticket['closed_by'] ?? 0),
            'closed_by_name' => (string) ($ticket['closed_by_name'] ?? ''),
            'approver_remarks' => (string) ($ticket['approver_remarks'] ?? ''),
            'resolution' => (string) ($ticket['resolution'] ?? ''),
            'jira' => [
                'issue_id' => (string) data_get($ticket, 'jira.issue_id', ''),
                'issue_key' => (string) data_get($ticket, 'jira.issue_key', ''),
                'issue_url' => (string) data_get($ticket, 'jira.issue_url', ''),
                'created_at' => (string) data_get($ticket, 'jira.created_at', ''),
            ],
            'requester' => [
                'employee_number' => (string) data_get($ticket, 'requester.employee_number', ''),
                'name' => (string) data_get($ticket, 'requester.name', ''),
                'email' => (string) data_get($ticket, 'requester.email', ''),
                'department' => (string) data_get($ticket, 'requester.department', ''),
                'contact_number' => (string) data_get($ticket, 'requester.contact_number', ''),
            ],
            'asset' => [
                'item_name' => (string) data_get($ticket, 'asset.item_name', ''),
                'asset_tag' => (string) data_get($ticket, 'asset.asset_tag', ''),
                'serial_number' => (string) data_get($ticket, 'asset.serial_number', ''),
                'location' => (string) data_get($ticket, 'asset.location', ''),
            ],
            'ticket' => [
                'issue_category' => (string) data_get($ticket, 'ticket.issue_category', ''),
                'jira_issue_type' => (string) data_get($ticket, 'ticket.jira_issue_type', ''),
                'priority' => (string) data_get($ticket, 'ticket.priority', ''),
                'summary' => (string) data_get($ticket, 'ticket.summary', ''),
                'description' => (string) data_get($ticket, 'ticket.description', ''),
            ],
        ];
    }

    protected function normalizeTicketStatus(string $status): string
    {
        return match (strtolower(trim($status))) {
            '', 'for approval', 'pending' => 'pending',
            'approved' => 'approved',
            'rejected' => 'rejected',
            'closed' => 'closed',
            default => strtolower(trim($status)),
        };
    }

    /**
     * @return array<string, string>
     */
    protected function ticketStatusOptions(): array
    {
        return [
            'pending' => 'Pending',
            'approved' => 'Approved',
            'rejected' => 'Rejected',
            'closed' => 'Closed',
        ];
    }

    protected function matchesTicketStatusFilters(array $ticket, string $search, string $statusFilter): bool
    {
        if (($statusFilter !== '') && (($ticket['status'] ?? '') !== $statusFilter)) {
            return false;
        }

        if ($search === '') {
            return true;
        }

        $needle = strtolower($search);
        $haystack = strtolower(implode(' ', array_filter([
            (string) ($ticket['id'] ?? ''),
            (string) ($ticket['status'] ?? ''),
            (string) ($ticket['requester']['name'] ?? ''),
            (string) ($ticket['requester']['email'] ?? ''),
            (string) ($ticket['requester']['department'] ?? ''),
            (string) ($ticket['asset']['item_name'] ?? ''),
            (string) ($ticket['asset']['asset_tag'] ?? ''),
            (string) ($ticket['asset']['serial_number'] ?? ''),
            (string) ($ticket['asset']['location'] ?? ''),
            (string) ($ticket['ticket']['summary'] ?? ''),
            (string) ($ticket['ticket']['description'] ?? ''),
            (string) ($ticket['ticket']['jira_issue_type'] ?? ''),
            (string) ($ticket['ticket']['issue_category'] ?? ''),
            (string) ($ticket['ticket']['priority'] ?? ''),
            (string) ($ticket['jira']['issue_key'] ?? ''),
            (string) ($ticket['jira']['issue_url'] ?? ''),
            (string) ($ticket['approver_remarks'] ?? ''),
            (string) ($ticket['resolution'] ?? ''),
            (string) ($ticket['reviewed_by_name'] ?? ''),
            (string) ($ticket['closed_by_name'] ?? ''),
        ])));

        return str_contains($haystack, $needle);
    }
}
