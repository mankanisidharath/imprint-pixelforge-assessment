<?php

namespace App\Http\Controllers;

use App\Models\Generation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class GenerationController extends Controller
{
    public function index(Request $request): Response
    {
        $generations = Generation::query()
            ->whereBelongsTo($request->user())
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(20)
            ->through(fn (Generation $generation) => [
                'id' => $generation->id,
                'prompt' => $generation->prompt,
                'size' => $generation->size,
                'quality' => $generation->quality,
                'reference_images' => $generation->reference_images,
                'status' => $generation->status->value,
                'status_note' => $generation->status_note,
                'estimated_charge_min_nano' => $generation->estimated_charge_min_nano,
                'estimated_charge_max_nano' => $generation->estimated_charge_max_nano,
                'charge_nano' => $generation->charge_nano,
                'charge_exact' => null,
                'image_url' => $generation->image_url,
                'image_expired' => $generation->image_url_expires_at?->isPast() ?? false,
                'error_message' => $generation->error_message,
                'created_at' => $generation->created_at->toISOString(),
                'completed_at' => $generation->completed_at?->toISOString(),
            ]);

        return Inertia::render('generations/Index', [
            'generations' => $generations,
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('generations/Create', [
            'sizes' => ['1024x1024', '1536x1024', '1024x1536'],
            'qualities' => ['low', 'medium', 'high'],
            'maxPromptLength' => 4000,
            'maxReferenceImages' => 4,
        ]);
    }

    public function estimate(): JsonResponse
    {
        return response()->json([]);
    }

    public function store(): RedirectResponse
    {
        return redirect()->route('generations.index');
    }
}
