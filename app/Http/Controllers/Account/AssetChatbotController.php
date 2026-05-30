<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Models\Asset;
use App\Services\AssetChatbotService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AssetChatbotController extends Controller
{
    public function __construct(
        private readonly AssetChatbotService $assetChatbotService
    ) {
    }

    public function index(): View
    {
        $this->authorize('view', Asset::class);

        return view('account.asset-request.chatbot', [
            'exampleQuestions' => AssetChatbotService::exampleQuestions(),
            'assistantStatus' => $this->assetChatbotService->assistantStatus(),
        ]);
    }

    public function ask(Request $request): JsonResponse
    {
        $this->authorize('view', Asset::class);

        $validated = $request->validate([
            'question' => ['required', 'string', 'max:500'],
        ]);

        return response()->json(
            $this->assetChatbotService->respond($validated['question'])
        );
    }
}
