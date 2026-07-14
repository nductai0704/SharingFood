<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\AiTextService;

class AiServiceController extends Controller
{
    public function categorizeFood(Request $request, AiTextService $aiService)
    {
        $validated = $request->validate([
            'items' => 'required|array|min:1',
            'items.*' => 'required|string|max:255',
        ]);

        $result = $aiService->categorizeFoodItems($validated['items']);

        return response()->json([
            'success' => true,
            'data' => $result
        ]);
    }
}
