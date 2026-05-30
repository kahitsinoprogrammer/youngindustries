<?php

namespace App\Services;

use App\Models\Asset;
use App\Models\Location;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class AssetChatbotService
{
    private const LIST_LIMIT = 10;

    private const STATUS_CONFIG = [
        'deployed' => [
            'label' => 'deployed assets',
            'patterns' => ['deployed', 'checked out'],
        ],
        'rtd' => [
            'label' => 'ready to deploy assets',
            'patterns' => ['ready to deploy', 'rtd', 'available'],
        ],
        'pending' => [
            'label' => 'pending assets',
            'patterns' => ['pending'],
        ],
        'archived' => [
            'label' => 'archived assets',
            'patterns' => ['archived'],
        ],
        'undeployable' => [
            'label' => 'undeployable assets',
            'patterns' => ['undeployable'],
        ],
    ];

    public function __construct(
        private readonly OllamaService $ollamaService
    ) {
    }

    public static function exampleQuestions(): array
    {
        return [
            'What are the list of deployed items?',
            'How many deployed items do we have?',
            'Show me deployed ThinkPads.',
            'Show me the ready to deploy assets.',
            'List pending items.',
            'Give me an asset status summary.',
        ];
    }

    public function assistantStatus(): array
    {
        return $this->ollamaService->status();
    }

    public function respond(string $question): array
    {
        $normalizedQuestion = $this->normalizeQuestion($question);

        if ($normalizedQuestion === '') {
            return $this->fallbackResponse('Please enter a question first.');
        }

        $intent = $this->interpretQuestion($question, $normalizedQuestion);

        if (($intent['action'] ?? 'unsupported') === 'summary') {
            return $this->summaryResponse($intent);
        }

        if (($intent['action'] ?? 'unsupported') === 'count') {
            return $this->countResponse($intent);
        }

        if (($intent['action'] ?? 'unsupported') === 'list') {
            return $this->listResponse($intent);
        }

        return $this->fallbackResponse();
    }

    private function listResponse(array $intent): array
    {
        $status = $intent['status'] ?: null;
        $search = $intent['search'] ?: null;
        $limit = $intent['limit'] ?? self::LIST_LIMIT;
        $descriptor = $this->descriptorForIntent($status, $search);
        $query = $this->buildAssetQuery($status, $search);

        $total = (clone $query)->count();
        $assets = $query
            ->select([
                'assets.id',
                'assets.asset_tag',
                'assets.name',
                'assets.model_id',
                'assets.assigned_to',
                'assets.assigned_type',
                'assets.status_id',
            ])
            ->with(['model:id,name'])
            ->orderBy('assets.asset_tag')
            ->limit($limit)
            ->get();

        if ($total === 0) {
            return [
                'reply' => "I couldn't find any {$descriptor}.",
                'intent' => [
                    'action' => 'list',
                    'status' => $status,
                    'search' => $search,
                ],
                'items' => [],
                'status_breakdown' => [],
                'suggestions' => self::exampleQuestions(),
                'meta' => [
                    'provider' => $intent['provider'] ?? 'fallback',
                ],
            ];
        }

        $reply = $total > $limit
            ? "I found {$total} {$descriptor}. Here are the first {$limit}."
            : "I found {$total} {$descriptor}.";

        return [
            'reply' => $reply,
            'intent' => [
                'action' => 'list',
                'status' => $status,
                'search' => $search,
            ],
            'items' => $this->formatAssets($assets),
            'status_breakdown' => [],
            'suggestions' => [],
            'meta' => [
                'provider' => $intent['provider'] ?? 'fallback',
            ],
        ];
    }

    private function countResponse(array $intent): array
    {
        $status = $intent['status'] ?: null;
        $search = $intent['search'] ?: null;
        $count = $this->buildAssetQuery($status, $search)->count();
        $descriptor = $this->descriptorForIntent($status, $search);

        return [
            'reply' => "There are {$count} {$descriptor} in the system.",
            'intent' => [
                'action' => 'count',
                'status' => $status,
                'search' => $search,
            ],
            'items' => [],
            'status_breakdown' => [],
            'suggestions' => [],
            'meta' => [
                'provider' => $intent['provider'] ?? 'fallback',
            ],
        ];
    }

    private function summaryResponse(array $intent): array
    {
        $counts = collect(array_keys(self::STATUS_CONFIG))
            ->map(fn (string $status) => [
                'status' => $status,
                'label' => Str::headline(self::STATUS_CONFIG[$status]['label']),
                'count' => $this->buildAssetQuery($status)->count(),
            ]);

        $reply = 'Here is the current asset status snapshot: '.$counts
            ->map(fn (array $entry) => "{$entry['count']} ".Str::lower($entry['label']))
            ->implode(', ').'.';

        return [
            'reply' => $reply,
            'intent' => [
                'action' => 'summary',
                'status' => null,
            ],
            'items' => [],
            'status_breakdown' => $counts->all(),
            'suggestions' => [],
            'meta' => [
                'provider' => $intent['provider'] ?? 'summary',
            ],
        ];
    }

    private function fallbackResponse(?string $reply = null): array
    {
        return [
            'reply' => $reply ?: "I can answer database-backed asset questions. Try asking about deployed assets, pending assets, a status summary, or a specific asset or model name.",
            'intent' => [
                'action' => 'fallback',
                'status' => null,
            ],
            'items' => [],
            'status_breakdown' => [],
            'suggestions' => self::exampleQuestions(),
            'meta' => [
                'provider' => 'fallback',
            ],
        ];
    }

    private function interpretQuestion(string $question, string $normalizedQuestion): array
    {
        $ollamaIntent = $this->ollamaService->interpretAssetQuestion($question);

        if (is_array($ollamaIntent)) {
            return [
                'action' => $ollamaIntent['action'],
                'status' => $ollamaIntent['status'],
                'search' => $ollamaIntent['search'],
                'limit' => max(1, min(self::LIST_LIMIT, (int) $ollamaIntent['limit'])),
                'provider' => 'ollama',
            ];
        }

        return [
            'action' => $this->fallbackAction($normalizedQuestion),
            'status' => $this->fallbackStatus($normalizedQuestion) ?? '',
            'search' => '',
            'limit' => self::LIST_LIMIT,
            'provider' => 'fallback',
        ];
    }

    private function fallbackStatus(string $question): ?string
    {
        foreach (self::STATUS_CONFIG as $status => $config) {
            if (Str::contains($question, $config['patterns'])) {
                return $status;
            }
        }

        return null;
    }

    private function fallbackAction(string $question): string
    {
        if ($this->isSummaryQuestion($question)) {
            return 'summary';
        }

        if (Str::contains($question, ['how many', 'count', 'total', 'number of'])) {
            return 'count';
        }

        return $this->fallbackStatus($question) ? 'list' : 'unsupported';
    }

    private function isSummaryQuestion(string $question): bool
    {
        return Str::contains($question, ['summary', 'overview', 'breakdown', 'snapshot']);
    }

    private function normalizeQuestion(string $question): string
    {
        return Str::of($question)
            ->lower()
            ->replaceMatches('/[^a-z0-9\s]+/', ' ')
            ->squish()
            ->value();
    }

    private function buildAssetQuery(?string $status = null, ?string $search = null): Builder
    {
        $query = Asset::query();

        $query = match ($status) {
            'deployed' => $query->Deployed(),
            'rtd' => $query->RTD(),
            'pending' => $query->Pending(),
            'archived' => $query->Archived(),
            'undeployable' => $query->Undeployable(),
            default => $query,
        };

        if (! $search) {
            return $query;
        }

        $like = '%'.$search.'%';

        return $query->where(function (Builder $searchQuery) use ($like) {
            $searchQuery
                ->where('assets.asset_tag', 'like', $like)
                ->orWhere('assets.name', 'like', $like)
                ->orWhere('assets.serial', 'like', $like)
                ->orWhereHas('model', function (Builder $modelQuery) use ($like) {
                    $modelQuery
                        ->where('name', 'like', $like)
                        ->orWhere('model_number', 'like', $like);
                })
                ->orWhereHasMorph('assignedTo', [User::class], function (Builder $assignedUserQuery) use ($like) {
                    $assignedUserQuery->where(function (Builder $userQuery) use ($like) {
                        $userQuery
                            ->where('first_name', 'like', $like)
                            ->orWhere('last_name', 'like', $like)
                            ->orWhere('display_name', 'like', $like)
                            ->orWhere('username', 'like', $like)
                            ->orWhere('employee_num', 'like', $like);
                    });
                })
                ->orWhereHasMorph('assignedTo', [Location::class], function (Builder $locationQuery) use ($like) {
                    $locationQuery->where('name', 'like', $like);
                });
        });
    }

    private function descriptorForIntent(?string $status, ?string $search): string
    {
        $base = $status ? self::STATUS_CONFIG[$status]['label'] : 'assets';

        if (! $search) {
            return $base;
        }

        return $base.' matching "'.$search.'"';
    }

    private function formatAssets(Collection $assets): array
    {
        return $assets->map(function (Asset $asset): array {
            return [
                'id' => $asset->id,
                'asset_tag' => (string) $asset->asset_tag,
                'asset_name' => $asset->name ?: ($asset->model->name ?? 'Unnamed asset'),
                'model' => $asset->model->name ?? 'Unknown model',
                'assigned_to' => $this->relatedModelLabel($asset->assignedTo),
                'url' => route('hardware.show', $asset),
            ];
        })->all();
    }

    private function relatedModelLabel(?Model $model): ?string
    {
        if (! $model) {
            return null;
        }

        if (method_exists($model, 'present')) {
            return html_entity_decode(trim(strip_tags((string) $model->present()->fullName)));
        }

        return $model->display_name
            ?? $model->name
            ?? $model->asset_tag
            ?? 'Record #'.$model->getKey();
    }
}
