<?php

namespace App\Services;

use Firebase\JWT\JWT;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class FirebaseHelpdeskService
{
    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function createRepairTicket(array $payload): array
    {
        $serviceAccount = $this->serviceAccount();
        $accessToken = $this->issueAccessToken($serviceAccount);
        $response = Http::withToken($accessToken)
            ->post($this->documentUrl(), [
                'fields' => $this->toFirestoreFields($payload),
            ])
            ->throw()
            ->json();

        if (! is_array($response) || empty($response['name'])) {
            throw new RuntimeException('Firebase did not return a document name.');
        }

        return [
            'document_name' => (string) $response['name'],
            'document_id' => (string) last(explode('/', (string) $response['name'])),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listRepairTickets(): array
    {
        $serviceAccount = $this->serviceAccount();
        $accessToken = $this->issueAccessToken($serviceAccount);
        $tickets = [];
        $pageToken = null;

        do {
            $params = ['pageSize' => 100];

            if ($pageToken) {
                $params['pageToken'] = $pageToken;
            }

            $response = Http::withToken($accessToken)
                ->get($this->documentUrl(), $params)
                ->throw()
                ->json();

            $documents = is_array($response) ? ($response['documents'] ?? []) : [];

            if (is_array($documents)) {
                foreach ($documents as $document) {
                    if (! is_array($document)) {
                        continue;
                    }

                    $ticket = $this->fromFirestoreDocument($document);

                    if (($ticket['module'] ?? null) !== 'tech_support' || ($ticket['request_type'] ?? null) !== 'repair_ticket') {
                        continue;
                    }

                    $tickets[] = $ticket;
                }
            }

            $pageToken = is_array($response) ? ($response['nextPageToken'] ?? null) : null;
        } while (is_string($pageToken) && $pageToken !== '');

        usort($tickets, function (array $left, array $right): int {
            return strcmp((string) ($right['submitted_at'] ?? ''), (string) ($left['submitted_at'] ?? ''));
        });

        return $tickets;
    }

    public function reviewRepairTicket(
        string $documentId,
        string $approvalStatus,
        int $reviewerId,
        string $reviewerName,
        string $remarks = '',
        array $additionalFields = []
    ): void {
        if (! in_array($approvalStatus, ['approved', 'rejected'], true)) {
            throw new RuntimeException('Invalid approval status provided.');
        }

        $serviceAccount = $this->serviceAccount();
        $accessToken = $this->issueAccessToken($serviceAccount);
        $ticket = $this->fetchRepairTicket($accessToken, $documentId);

        if (! is_array($ticket)) {
            throw new RuntimeException('The selected ticket request could not be found.');
        }

        $currentStatus = $this->normalizeApprovalStatus((string) ($ticket['status'] ?? ''));

        if ($currentStatus !== 'pending') {
            throw new RuntimeException('This ticket request has already been reviewed.');
        }

        $timestamp = now()->toIso8601String();
        $payload = [
            'status' => $approvalStatus,
            'updated_at' => $timestamp,
            'reviewed_at' => $timestamp,
            'reviewed_by' => $reviewerId,
            'reviewed_by_name' => $reviewerName,
            'approver_remarks' => $remarks,
        ];

        if ($additionalFields !== []) {
            $payload = array_replace_recursive($payload, $additionalFields);
        }

        Http::withToken($accessToken)
            ->patch(
                $this->documentPatchUrl($documentId, array_keys($payload)),
                ['fields' => $this->toFirestoreFields($payload)]
            )
            ->throw();
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findRepairTicket(string $documentId): ?array
    {
        $serviceAccount = $this->serviceAccount();
        $accessToken = $this->issueAccessToken($serviceAccount);

        return $this->fetchRepairTicket($accessToken, $documentId);
    }

    public function closeRepairTicket(
        string $documentId,
        string $resolution,
        int $closedById,
        string $closedByName
    ): void {
        $serviceAccount = $this->serviceAccount();
        $accessToken = $this->issueAccessToken($serviceAccount);
        $ticket = $this->fetchRepairTicket($accessToken, $documentId);

        if (! is_array($ticket)) {
            throw new RuntimeException('The selected ticket request could not be found.');
        }

        $currentStatus = $this->normalizeApprovalStatus((string) ($ticket['status'] ?? ''));

        if ($currentStatus !== 'approved') {
            throw new RuntimeException('Only approved ticket requests can be closed.');
        }

        $timestamp = now()->toIso8601String();
        $payload = [
            'status' => 'closed',
            'updated_at' => $timestamp,
            'closed_at' => $timestamp,
            'closed_by' => $closedById,
            'closed_by_name' => $closedByName,
            'resolution' => $resolution,
        ];

        Http::withToken($accessToken)
            ->patch(
                $this->documentPatchUrl($documentId, array_keys($payload)),
                ['fields' => $this->toFirestoreFields($payload)]
            )
            ->throw();
    }

    /**
     * @return array<string, mixed>
     */
    protected function serviceAccount(): array
    {
        $json = config('services.firebase.service_account_json');
        $path = config('services.firebase.service_account_path');

        if ($json) {
            $serviceAccount = json_decode((string) $json, true);
        } elseif ($path && is_file($path)) {
            $serviceAccount = json_decode((string) file_get_contents($path), true);
        } else {
            throw new RuntimeException('Firebase service account configuration is incomplete.');
        }

        if (! is_array($serviceAccount)) {
            throw new RuntimeException('Firebase service account configuration could not be parsed.');
        }

        foreach (['client_email', 'private_key'] as $requiredField) {
            if (empty($serviceAccount[$requiredField])) {
                throw new RuntimeException('Firebase service account configuration is incomplete.');
            }
        }

        return $serviceAccount;
    }

    /**
     * @param  array<string, mixed>  $serviceAccount
     */
    protected function issueAccessToken(array $serviceAccount): string
    {
        $tokenUri = (string) config('services.firebase.token_uri', 'https://oauth2.googleapis.com/token');
        $now = time();
        $assertion = JWT::encode([
            'iss' => $serviceAccount['client_email'],
            'scope' => 'https://www.googleapis.com/auth/datastore',
            'aud' => $tokenUri,
            'iat' => $now,
            'exp' => $now + 3600,
        ], $serviceAccount['private_key'], 'RS256');

        $response = Http::asForm()
            ->post($tokenUri, [
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion' => $assertion,
            ])
            ->throw()
            ->json();

        $accessToken = is_array($response) ? ($response['access_token'] ?? null) : null;

        if (! is_string($accessToken) || $accessToken === '') {
            throw new RuntimeException('Firebase access token could not be retrieved.');
        }

        return $accessToken;
    }

    protected function documentUrl(): string
    {
        $projectId = (string) config('services.firebase.project_id');
        $database = (string) config('services.firebase.database', '(default)');
        $collection = (string) config('services.firebase.helpdesk_collection', 'helpdesk_tickets');
        $baseUrl = rtrim((string) config('services.firebase.firestore_base_url', 'https://firestore.googleapis.com/v1'), '/');

        if (($projectId === '') || ($collection === '')) {
            throw new RuntimeException('Firebase project configuration is incomplete.');
        }

        return sprintf(
            '%s/projects/%s/databases/%s/documents/%s',
            $baseUrl,
            $projectId,
            $database,
            $collection
        );
    }

    protected function ticketDocumentUrl(string $documentId): string
    {
        return $this->documentUrl().'/'.rawurlencode($documentId);
    }

    protected function documentPatchUrl(string $documentId, array $fieldPaths): string
    {
        $queryString = implode('&', array_map(
            fn (string $fieldPath): string => 'updateMask.fieldPaths='.rawurlencode($fieldPath),
            $fieldPaths
        ));

        return $this->ticketDocumentUrl($documentId).'?'.$queryString;
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

        if (is_float($value)) {
            return ['doubleValue' => $value];
        }

        if (is_bool($value)) {
            return ['booleanValue' => $value];
        }

        if (is_null($value)) {
            return ['nullValue' => null];
        }

        if (is_array($value)) {
            if (array_is_list($value)) {
                return [
                    'arrayValue' => [
                        'values' => array_map(fn (mixed $item) => $this->toFirestoreValue($item), $value),
                    ],
                ];
            }

            return [
                'mapValue' => [
                    'fields' => $this->toFirestoreFields($value),
                ],
            ];
        }

        throw new RuntimeException('Unsupported Firebase value type encountered.');
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function fetchRepairTicket(string $accessToken, string $documentId): ?array
    {
        try {
            $response = Http::withToken($accessToken)
                ->get($this->ticketDocumentUrl($documentId))
                ->throw()
                ->json();
        } catch (RequestException $exception) {
            if ($exception->response?->status() === 404) {
                return null;
            }

            throw $exception;
        }

        if (! is_array($response)) {
            return null;
        }

        $ticket = $this->fromFirestoreDocument($response);

        if (($ticket['module'] ?? null) !== 'tech_support' || ($ticket['request_type'] ?? null) !== 'repair_ticket') {
            return null;
        }

        return $ticket;
    }

    /**
     * @param  array<string, mixed>  $document
     * @return array<string, mixed>
     */
    protected function fromFirestoreDocument(array $document): array
    {
        $fields = $this->fromFirestoreFields((array) ($document['fields'] ?? []));
        $documentName = (string) ($document['name'] ?? '');
        $segments = explode('/', $documentName);

        $fields['document_name'] = $documentName;
        $fields['document_id'] = (string) (end($segments) ?: '');
        $fields['created_at'] = (string) ($fields['created_at'] ?? $document['createTime'] ?? '');
        $fields['updated_at'] = (string) ($fields['updated_at'] ?? $document['updateTime'] ?? '');

        return $fields;
    }

    /**
     * @param  array<string, mixed>  $fields
     * @return array<string, mixed>
     */
    protected function fromFirestoreFields(array $fields): array
    {
        $payload = [];

        foreach ($fields as $key => $value) {
            if (! is_array($value)) {
                continue;
            }

            $payload[$key] = $this->fromFirestoreValue($value);
        }

        return $payload;
    }

    protected function fromFirestoreValue(array $value): mixed
    {
        if (array_key_exists('stringValue', $value)) {
            return (string) $value['stringValue'];
        }

        if (array_key_exists('integerValue', $value)) {
            return (int) $value['integerValue'];
        }

        if (array_key_exists('doubleValue', $value)) {
            return (float) $value['doubleValue'];
        }

        if (array_key_exists('booleanValue', $value)) {
            return (bool) $value['booleanValue'];
        }

        if (array_key_exists('nullValue', $value)) {
            return null;
        }

        if (array_key_exists('timestampValue', $value)) {
            return (string) $value['timestampValue'];
        }

        if (isset($value['mapValue']['fields']) && is_array($value['mapValue']['fields'])) {
            return $this->fromFirestoreFields($value['mapValue']['fields']);
        }

        if (isset($value['arrayValue']['values']) && is_array($value['arrayValue']['values'])) {
            return array_map(function (mixed $item): mixed {
                return is_array($item) ? $this->fromFirestoreValue($item) : $item;
            }, $value['arrayValue']['values']);
        }

        if (array_key_exists('arrayValue', $value)) {
            return [];
        }

        return $value;
    }

    protected function normalizeApprovalStatus(string $status): string
    {
        return match (strtolower(trim($status))) {
            '', 'for approval', 'pending' => 'pending',
            'approved' => 'approved',
            'rejected' => 'rejected',
            'closed' => 'closed',
            default => strtolower(trim($status)),
        };
    }
}
