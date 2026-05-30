<?php

namespace App\Services;

use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class JiraIssueService
{
    /**
     * @return array<string, mixed>
     */
    public function diagnostics(): array
    {
        $configuredIssueTypes = $this->configuredIssueTypes();
        $diagnostics = [
            'config' => [
                'base_url' => $this->safeConfigValue('services.jira.base_url'),
                'email' => $this->safeConfigValue('services.jira.email'),
                'project_key' => $this->safeConfigValue('services.jira.project_key'),
                'timeout' => $this->timeout(),
                'issue_types' => $configuredIssueTypes,
            ],
            'checks' => [
                'config' => [
                    'ok' => true,
                    'message' => 'Jira configuration values are present.',
                ],
                'current_user' => [
                    'ok' => false,
                    'message' => 'Not checked yet.',
                    'data' => [],
                ],
                'project' => [
                    'ok' => false,
                    'message' => 'Not checked yet.',
                    'data' => [],
                ],
                'permissions' => [
                    'ok' => false,
                    'message' => 'Not checked yet.',
                    'data' => [],
                ],
                'issue_types' => [
                    'ok' => false,
                    'message' => 'Not checked yet.',
                    'data' => [],
                ],
            ],
            'errors' => [],
        ];

        try {
            $baseUrl = $this->baseUrl();
            $email = $this->email();
            $apiToken = $this->apiToken();
            $projectKey = $this->projectKey();
        } catch (RuntimeException $exception) {
            $diagnostics['checks']['config'] = [
                'ok' => false,
                'message' => $exception->getMessage(),
            ];
            $diagnostics['errors'][] = $exception->getMessage();

            return $diagnostics;
        }

        $currentUser = $this->performDiagnosticGet($baseUrl.'/rest/api/3/myself', $email, $apiToken);
        if ($currentUser['ok']) {
            $diagnostics['checks']['current_user'] = [
                'ok' => true,
                'message' => 'Jira accepted the credentials and returned the current user.',
                'data' => [
                    'account_id' => (string) data_get($currentUser, 'data.accountId', ''),
                    'display_name' => (string) data_get($currentUser, 'data.displayName', ''),
                    'email_address' => (string) data_get($currentUser, 'data.emailAddress', ''),
                    'active' => (bool) data_get($currentUser, 'data.active', false),
                    'self' => (string) data_get($currentUser, 'data.self', ''),
                ],
            ];
        } else {
            $diagnostics['checks']['current_user'] = [
                'ok' => false,
                'message' => $currentUser['message'],
                'data' => [],
            ];
            $diagnostics['errors'][] = $currentUser['message'];

            return $diagnostics;
        }

        $project = $this->performDiagnosticGet($baseUrl.'/rest/api/3/project/'.rawurlencode($projectKey), $email, $apiToken);
        if ($project['ok']) {
            $projectData = is_array($project['data']) ? $project['data'] : [];
            $diagnostics['checks']['project'] = [
                'ok' => true,
                'message' => 'Jira returned the configured project.',
                'data' => [
                    'id' => (string) ($projectData['id'] ?? ''),
                    'key' => (string) ($projectData['key'] ?? ''),
                    'name' => (string) ($projectData['name'] ?? ''),
                    'project_type_key' => (string) ($projectData['projectTypeKey'] ?? ''),
                    'style' => (string) ($projectData['style'] ?? ''),
                ],
            ];
        } else {
            $diagnostics['checks']['project'] = [
                'ok' => false,
                'message' => $project['message'],
                'data' => [],
            ];
            $diagnostics['errors'][] = $project['message'];

            return $diagnostics;
        }

        $permissions = $this->performDiagnosticGet(
            $baseUrl.'/rest/api/3/mypermissions?projectKey='.rawurlencode($projectKey).'&permissions=BROWSE_PROJECTS,CREATE_ISSUES',
            $email,
            $apiToken
        );
        if ($permissions['ok']) {
            $permissionPayload = is_array($permissions['data']) ? $permissions['data'] : [];
            $browseProjects = (bool) data_get($permissionPayload, 'permissions.BROWSE_PROJECTS.havePermission', false);
            $createIssues = (bool) data_get($permissionPayload, 'permissions.CREATE_ISSUES.havePermission', false);
            $diagnostics['checks']['permissions'] = [
                'ok' => $browseProjects && $createIssues,
                'message' => ($browseProjects && $createIssues)
                    ? 'The Jira user can browse the project and create issues in it.'
                    : 'The Jira user is missing one or more required project permissions.',
                'data' => [
                    'browse_projects' => $browseProjects,
                    'create_issues' => $createIssues,
                ],
            ];
        } else {
            $diagnostics['checks']['permissions'] = [
                'ok' => false,
                'message' => $permissions['message'],
                'data' => [],
            ];
            $diagnostics['errors'][] = $permissions['message'];
        }

        $projectId = (string) data_get($diagnostics, 'checks.project.data.id', '');
        if ($projectId !== '') {
            $issueTypes = $this->performDiagnosticGet(
                $baseUrl.'/rest/api/3/issuetype/project?projectId='.rawurlencode($projectId),
                $email,
                $apiToken
            );

            if ($issueTypes['ok']) {
                $availableIssueTypes = collect(is_array($issueTypes['data']) ? $issueTypes['data'] : [])
                    ->map(fn (mixed $issueType): string => is_array($issueType) ? trim((string) ($issueType['name'] ?? '')) : '')
                    ->filter()
                    ->values()
                    ->all();
                $missingIssueTypes = array_values(array_diff(array_values($configuredIssueTypes), $availableIssueTypes));
                $diagnostics['checks']['issue_types'] = [
                    'ok' => $missingIssueTypes === [],
                    'message' => $missingIssueTypes === []
                        ? 'All configured Jira issue types are available in the configured project.'
                        : 'One or more configured Jira issue types are not available in the configured project.',
                    'data' => [
                        'available' => $availableIssueTypes,
                        'configured' => array_values($configuredIssueTypes),
                        'missing' => $missingIssueTypes,
                    ],
                ];
            } else {
                $diagnostics['checks']['issue_types'] = [
                    'ok' => false,
                    'message' => $issueTypes['message'],
                    'data' => [
                        'available' => [],
                        'configured' => array_values($configuredIssueTypes),
                        'missing' => array_values($configuredIssueTypes),
                    ],
                ];
                $diagnostics['errors'][] = $issueTypes['message'];
            }
        }

        return $diagnostics;
    }

    /**
     * @param  array<string, mixed>  $ticket
     * @return array<string, string>
     */
    public function createIssueFromRepairTicket(array $ticket): array
    {
        $baseUrl = $this->baseUrl();
        $email = $this->email();
        $apiToken = $this->apiToken();
        $projectKey = $this->projectKey();
        $issueType = $this->issueTypeName((string) data_get($ticket, 'ticket.jira_issue_type', ''));
        $summary = trim((string) data_get($ticket, 'ticket.summary', ''));

        if ($summary === '') {
            throw new RuntimeException('Ticket summary is required before a Jira issue can be created.');
        }

        $payload = [
            'fields' => [
                'project' => [
                    'key' => $projectKey,
                ],
                'summary' => $summary,
                'issuetype' => [
                    'name' => $issueType,
                ],
                'description' => $this->buildDescriptionDocument($ticket),
            ],
        ];

        try {
            $response = Http::withBasicAuth($email, $apiToken)
                ->acceptJson()
                ->timeout($this->timeout())
                ->post($baseUrl.'/rest/api/3/issue', $payload)
                ->throw()
                ->json();
        } catch (RequestException $exception) {
            throw new RuntimeException($this->formatCreateIssueError($exception), previous: $exception);
        }

        if (! is_array($response) || empty($response['key'])) {
            throw new RuntimeException('Jira did not return an issue key after the ticket was approved.');
        }

        $issueKey = (string) $response['key'];

        return [
            'issue_id' => (string) ($response['id'] ?? ''),
            'issue_key' => $issueKey,
            'issue_url' => $baseUrl.'/browse/'.$issueKey,
            'created_at' => now()->toIso8601String(),
        ];
    }

    protected function baseUrl(): string
    {
        $baseUrl = rtrim((string) config('services.jira.base_url'), '/');

        if ($baseUrl === '') {
            throw new RuntimeException('Jira integration is not configured. Set JIRA_BASE_URL in your .env file.');
        }

        return $baseUrl;
    }

    protected function email(): string
    {
        $email = trim((string) config('services.jira.email'));

        if ($email === '') {
            throw new RuntimeException('Jira integration is not configured. Set JIRA_EMAIL in your .env file.');
        }

        return $email;
    }

    protected function apiToken(): string
    {
        $apiToken = trim((string) config('services.jira.api_token'));

        if ($apiToken === '') {
            throw new RuntimeException('Jira integration is not configured. Set JIRA_API_TOKEN in your .env file.');
        }

        return $apiToken;
    }

    protected function projectKey(): string
    {
        $projectKey = trim((string) config('services.jira.project_key'));

        if ($projectKey === '') {
            throw new RuntimeException('Jira integration is not configured. Set JIRA_PROJECT_KEY in your .env file.');
        }

        return $projectKey;
    }

    protected function timeout(): int
    {
        return max(1, (int) config('services.jira.timeout', 30));
    }

    /**
     * @return array<string, string>
     */
    protected function configuredIssueTypes(): array
    {
        $issueTypes = config('services.jira.issue_types', []);

        if (! is_array($issueTypes)) {
            return [];
        }

        return collect($issueTypes)
            ->map(fn (mixed $issueType): string => trim((string) $issueType))
            ->filter()
            ->all();
    }

    protected function issueTypeName(string $requestedIssueType): string
    {
        $issueTypes = $this->configuredIssueTypes();
        $configuredIssueType = is_array($issueTypes)
            ? trim((string) ($issueTypes[$requestedIssueType] ?? ''))
            : '';

        if ($configuredIssueType !== '') {
            return $configuredIssueType;
        }

        if (trim($requestedIssueType) !== '') {
            return trim($requestedIssueType);
        }

        throw new RuntimeException('A Jira issue type is required before the ticket can be approved.');
    }

    /**
     * @param  array<string, mixed>  $ticket
     * @return array<string, mixed>
     */
    protected function buildDescriptionDocument(array $ticket): array
    {
        $descriptionLines = preg_split('/\r\n|\r|\n/', (string) data_get($ticket, 'ticket.description', '')) ?: [];
        $content = [];

        foreach ([
            'Submitted from Snipe-IT after tech support approval.',
            'Requester: '.$this->valueOrFallback((string) data_get($ticket, 'requester.name', ''), 'Unknown requester').' ('.$this->valueOrFallback((string) data_get($ticket, 'requester.email', ''), 'no email').')',
            'Employee Number: '.$this->valueOrFallback((string) data_get($ticket, 'requester.employee_number', ''), 'Not provided'),
            'Department: '.$this->valueOrFallback((string) data_get($ticket, 'requester.department', ''), 'Not provided'),
            'Contact Number: '.$this->valueOrFallback((string) data_get($ticket, 'requester.contact_number', ''), 'Not provided'),
            'Item Name: '.$this->valueOrFallback((string) data_get($ticket, 'asset.item_name', ''), 'Not provided'),
            'Asset Tag: '.$this->valueOrFallback((string) data_get($ticket, 'asset.asset_tag', ''), 'Not provided'),
            'Serial Number: '.$this->valueOrFallback((string) data_get($ticket, 'asset.serial_number', ''), 'Not provided'),
            'Location: '.$this->valueOrFallback((string) data_get($ticket, 'asset.location', ''), 'Not provided'),
            'Issue Category: '.$this->valueOrFallback((string) data_get($ticket, 'ticket.issue_category', ''), 'Not provided'),
            'Jira Issue Type Requested: '.$this->valueOrFallback((string) data_get($ticket, 'ticket.jira_issue_type', ''), 'Not provided'),
            'Priority: '.$this->valueOrFallback((string) data_get($ticket, 'ticket.priority', ''), 'Not provided'),
            'Submitted At: '.$this->valueOrFallback((string) ($ticket['submitted_at'] ?? ''), 'Not provided'),
            'Problem Description:',
        ] as $line) {
            $content[] = $this->paragraphNode($line);
        }

        foreach ($descriptionLines as $line) {
            $content[] = $this->paragraphNode(trim($line) !== '' ? $line : ' ');
        }

        return [
            'type' => 'doc',
            'version' => 1,
            'content' => $content,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function paragraphNode(string $text): array
    {
        return [
            'type' => 'paragraph',
            'content' => [
                [
                    'type' => 'text',
                    'text' => $text,
                ],
            ],
        ];
    }

    protected function valueOrFallback(string $value, string $fallback): string
    {
        return trim($value) !== '' ? trim($value) : $fallback;
    }

    protected function formatCreateIssueError(RequestException $exception): string
    {
        $response = $exception->response;
        $body = $response?->json();
        $messages = [];

        if (is_array($body)) {
            foreach (($body['errorMessages'] ?? []) as $message) {
                if (is_string($message) && $message !== '') {
                    $messages[] = $message;
                }
            }

            foreach (($body['errors'] ?? []) as $field => $message) {
                if (is_string($message) && $message !== '') {
                    $messages[] = sprintf('%s: %s', $field, $message);
                }
            }
        }

        if ($messages !== []) {
            return 'Jira issue could not be created: '.implode(' ', $messages);
        }

        return 'Jira issue could not be created. Check JIRA_BASE_URL, JIRA_PROJECT_KEY, issue type mapping, and Jira API credentials.';
    }

    protected function safeConfigValue(string $key): string
    {
        return trim((string) config($key));
    }

    /**
     * @return array<string, mixed>
     */
    protected function performDiagnosticGet(string $url, string $email, string $apiToken): array
    {
        try {
            $response = Http::withBasicAuth($email, $apiToken)
                ->acceptJson()
                ->timeout($this->timeout())
                ->get($url)
                ->throw()
                ->json();
        } catch (RequestException $exception) {
            return [
                'ok' => false,
                'message' => $this->formatDiagnosticError($exception),
                'data' => [],
            ];
        }

        return [
            'ok' => true,
            'message' => 'OK',
            'data' => is_array($response) ? $response : [],
        ];
    }

    protected function formatDiagnosticError(RequestException $exception): string
    {
        $response = $exception->response;
        $status = $response?->status();
        $body = $response?->json();
        $messages = [];

        if (is_array($body)) {
            foreach (($body['errorMessages'] ?? []) as $message) {
                if (is_string($message) && $message !== '') {
                    $messages[] = $message;
                }
            }

            foreach (($body['errors'] ?? []) as $field => $message) {
                if (is_string($message) && $message !== '') {
                    $messages[] = sprintf('%s: %s', $field, $message);
                }
            }
        }

        if ($messages !== []) {
            return sprintf('Jira returned HTTP %s: %s', (string) $status, implode(' ', $messages));
        }

        return sprintf('Jira returned HTTP %s for the diagnostic request.', (string) $status);
    }
}
