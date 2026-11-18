<?php

namespace App\Http\Controllers;

use App\Features\Pixelforge\GenerationOptions;
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
            'sizes' => GenerationOptions::SIZES,
            'qualities' => GenerationOptions::QUALITIES,
            'maxPromptLength' => GenerationOptions::MAX_PROMPT_LENGTH,
            'maxReferenceImages' => GenerationOptions::MAX_REFERENCE_IMAGES,
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
