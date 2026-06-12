<?php

namespace Tests\Feature\Requests\Ui;

use App\Models\Asset;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AssetChatbotTest extends TestCase
{
    public function test_requires_asset_view_permission_to_open_chatbot()
    {
        $this->actingAs(User::factory()->create())
            ->get(route('asset-request.chatbot'))
            ->assertForbidden();
    }

    public function test_asset_view_user_can_open_chatbot()
    {
        $this->actingAs(User::factory()->viewAssets()->create())
            ->get(route('asset-request.chatbot'))
            ->assertOk()
            ->assertSeeText('Asset Chatbot');
    }

    public function test_chatbot_returns_deployed_assets_for_matching_question()
    {
        $assignedUser = User::factory()->create();

        config()->set('services.ollama.enabled', true);
        config()->set('services.ollama.base_url', 'https://ollama.example.com');
        config()->set('services.ollama.api_key', 'demo-token');
        config()->set('services.ollama.auth_header', 'Authorization');
        config()->set('services.ollama.auth_scheme', 'Bearer');
        config()->set('services.ollama.model', 'llama3.1:8b');

        Http::fake([
            'https://ollama.example.com/api/chat' => Http::response([
                'message' => [
                    'content' => json_encode([
                        'action' => 'list',
                        'status' => 'deployed',
                        'search' => '',
                        'limit' => 10,
                        'reason' => 'The user wants deployed assets.',
                    ]),
                ],
            ], 200),
        ]);

        Asset::factory()->create([
            'asset_tag' => 'AST-DEPLOYED-1',
            'name' => 'ThinkPad X1',
            'assigned_to' => $assignedUser->id,
            'assigned_type' => User::class,
        ]);

        Asset::factory()->create([
            'asset_tag' => 'AST-READY-1',
            'name' => 'MacBook Air',
        ]);

        $this->actingAs(User::factory()->superuser()->create())
            ->postJson(route('asset-request.chatbot.ask'), [
                'question' => 'What are the list of deployed items?',
            ])
            ->assertOk()
            ->assertJsonPath('intent.action', 'list')
            ->assertJsonPath('intent.status', 'deployed')
            ->assertJsonPath('meta.provider', 'ollama')
            ->assertJsonPath('items.0.asset_tag', 'AST-DEPLOYED-1')
            ->assertJsonMissing([
                'asset_tag' => 'AST-READY-1',
            ]);

        Http::assertSent(function ($request): bool {
            return $request->url() === 'https://ollama.example.com/api/chat'
                && $request->hasHeader('Authorization', 'Bearer demo-token');
        });
    }
}
