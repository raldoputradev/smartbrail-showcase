<?php

namespace App\Http\Controllers;

use App\Services\HelpdeskAiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HelpdeskChatController extends Controller
{
    public function __invoke(Request $request, HelpdeskAiService $ai): JsonResponse
    {
        $validated = $request->validate([
            'message' => ['required', 'string', 'max:500'],
            'history' => ['nullable', 'array', 'max:10'],
            'history.*.role' => ['required_with:history', 'in:user,assistant'],
            'history.*.content' => ['required_with:history', 'string', 'max:800'],
        ]);

        $history = [];
        foreach ($validated['history'] ?? [] as $row) {
            $history[] = [
                'role' => $row['role'],
                'content' => $row['content'],
            ];
        }

        $result = $ai->reply($validated['message'], $history);

        return response()->json([
            'ok' => true,
            'success' => true,
            'reply' => $result['reply'],
            'source' => $result['source'],
            'ai_enabled' => $ai->isGeminiConfigured(),
        ]);
    }
}
