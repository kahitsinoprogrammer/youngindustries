<?php

namespace App\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

class OllamaService
{
    public function isEnabled(): bool
    {
        return (bool) config('services.ollama.enabled', false);
    }

    public function status(): array
    {
        $status = [
            'enabled' => $this->isEnabled(),
            'available' => false,
            'base_url' => $this->baseUrl(),
            'model' => $this->model(),
            'message' => 'Ollama integration is disabled.',
        ];

        if (! $status['enabled']) {
            return $status;
        }

        try {
            $response = $this->client(min(5, $this->timeout()))
                ->get('/api/tags')
                ->throw();

            $models = collect($response->json('models', []));
            $hasModel = $models->contains(function (array $model): bool {
                return ($model['name'] ?? null) === $this->model()
                    || ($model['model'] ?? null) === $this->model();
            });

            if ($hasModel) {
                $status['available'] = true;
                $status['message'] = 'Connected to the configured Ollama model.';

                return $status;
            }

            $status['message'] = 'Ollama is reachable, but the configured model is not available on that server.';

            return $status;
        } catch (\Throwable) {
            $status['message'] = str_contains($status['base_url'], '127.0.0.1')
                || str_contains($status['base_url'], 'localhost')
                ? 'Ollama is not reachable at the configured endpoint. The app is still pointing to a local Ollama host.'
                : 'Ollama is not reachable at the configured endpoint.';

            return $status;
        }
    }

    public function interpretAssetQuestion(string $question): ?array
    {
        if (! $this->isEnabled()) {
            return null;
        }

        $schema = [
            'type' => 'object',
            'properties' => [
                'action' => [
                    'type' => 'string',
                    'enum' => ['list', 'count', 'summary', 'unsupported'],
                ],
                'status' => [
                    'type' => 'string',
                    'enum' => ['', 'deployed', 'rtd', 'pending', 'archived', 'undeployable'],
                ],
                'search' => [
                    'type' => 'string',
                ],
                'limit' => [
                    'type' => 'integer',
                    'minimum' => 1,
                    'maximum' => 25,
                ],
                'reason' => [
                    'type' => 'string',
                ],
            ],
            'required' => ['action', 'status', 'search', 'limit', 'reason'],
        ];

        $systemPrompt = <<<'PROMPT'
You translate user questions into a structured asset inventory query for a Laravel application.

The chatbot answers database-backed asset questions only.

Supported actions:
- list: show matching assets
- count: count matching assets
- summary: give a high-level status breakdown
- unsupported: anything that cannot be answered from asset inventory data

Supported statuses:
- deployed: assigned assets
- rtd: ready to deploy assets
- pending: pending assets
- archived: archived assets
- undeployable: undeployable assets
- empty string: no specific status filter

Rules:
- Extract a concise search phrase when the question refers to asset tag, asset name, serial, model, or assignee.
- Leave search as an empty string if there is no useful search phrase.
- Use summary only for overview/breakdown/snapshot style questions.
- Use unsupported if the question is not about the asset inventory in this app.
- Return JSON only.
PROMPT;

        $content = $this->chat([
            ['role' => 'system', 'content' => $systemPrompt],
            ['role' => 'user', 'content' => $question],
        ], $schema);

        if (! $content) {
            return null;
        }

        $decoded = json_decode($content, true);

        if (! is_array($decoded)) {
            return null;
        }

        return [
            'action' => in_array($decoded['action'] ?? '', ['list', 'count', 'summary', 'unsupported'], true)
                ? $decoded['action']
                : 'unsupported',
            'status' => in_array($decoded['status'] ?? '', ['', 'deployed', 'rtd', 'pending', 'archived', 'undeployable'], true)
                ? $decoded['status']
                : '',
            'search' => trim((string) ($decoded['search'] ?? '')),
            'limit' => max(1, min(25, (int) ($decoded['limit'] ?? 10))),
            'reason' => trim((string) ($decoded['reason'] ?? '')),
        ];
    }

    private function chat(array $messages, array $schema): ?string
    {
        try {
            $response = $this->client($this->timeout())
                ->post('/api/chat', [
                    'model' => $this->model(),
                    'messages' => $messages,
                    'format' => $schema,
                    'stream' => false,
                    'options' => [
                        'temperature' => 0,
                    ],
                ])
                ->throw();

            $content = $response->json('message.content');

            return is_string($content) ? $content : null;
        } catch (\Throwable) {
            return null;
        }
    }

    private function client(int $timeout): PendingRequest
    {
        $request = Http::baseUrl($this->baseUrl())
            ->acceptJson()
            ->timeout($timeout);

        $apiKey = $this->apiKey();

        if ($apiKey === '') {
            return $request;
        }

        $header = $this->authHeader();
        $scheme = $this->authScheme();
        $value = $scheme !== '' ? "{$scheme} {$apiKey}" : $apiKey;

        return $request->withHeaders([
            $header => $value,
        ]);
    }

    private function baseUrl(): string
    {
        return rtrim((string) config('services.ollama.base_url', 'http://127.0.0.1:11434'), '/');
    }

    private function apiKey(): string
    {
        return trim((string) config('services.ollama.api_key', ''));
    }

    private function authHeader(): string
    {
        $header = trim((string) config('services.ollama.auth_header', 'Authorization'));

        return $header !== '' ? $header : 'Authorization';
    }

    private function authScheme(): string
    {
        return trim((string) config('services.ollama.auth_scheme', 'Bearer'));
    }

    private function model(): string
    {
        return (string) config('services.ollama.model', 'llama3.2:latest');
    }

    private function timeout(): int
    {
        return max(5, (int) config('services.ollama.timeout', 45));
    }
}
