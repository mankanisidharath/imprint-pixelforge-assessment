<?php

namespace App\Http\Controllers;

use App\Features\Pixelforge\GenerationOptions;
use App\Http\Requests\StoreGenerationRequest;
use App\Jobs\SubmitGeneration;
use App\Models\Generation;
use App\Services\CostCalculator;
use App\Services\Pixelforge;
use App\Support\Money;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

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
                'charge_exact' => $generation->charge_nano === null ? null : Money::format((int) $generation->charge_nano),
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

    public function estimate(Request $request, Pixelforge $pixelforge, CostCalculator $calculator): JsonResponse
    {
        $data = $request->validate([
            'chars' => ['required', 'integer', 'between:0,'.GenerationOptions::MAX_PROMPT_LENGTH],
            'size' => ['required', Rule::in(GenerationOptions::SIZES)],
            'quality' => ['required', Rule::in(GenerationOptions::QUALITIES)],
            'references' => ['required', 'integer', 'between:0,'.GenerationOptions::MAX_REFERENCE_IMAGES],
        ]);
        $range = $calculator->estimate($pixelforge->pricing(), (int) $data['chars'], $data['size'], $data['quality'], (int) $data['references']);

        return response()->json(['min_nano' => $calculator->charge($range['min']), 'max_nano' => $calculator->charge($range['max'])]);
    }

    public function store(StoreGenerationRequest $request, Pixelforge $pixelforge, CostCalculator $calculator): RedirectResponse
    {
        $data = $request->validated();
        try {
            $pricing = $pixelforge->pricing();
        } catch (Throwable $exception) {
            throw ValidationException::withMessages(['prompt' => 'Pricing is temporarily unavailable. Please try again in a moment.']);
        }

        $references = $data['reference_images'] ?? [];
        $range = $calculator->estimate($pricing, mb_strlen($data['prompt']), $data['size'], $data['quality'], count($references));
        $generation = $request->user()->generations()->create([
            ...$data,
            'reference_images' => $references,
            'status' => 'queued',
            'estimated_charge_min_nano' => $calculator->charge($range['min']),
            'estimated_charge_max_nano' => $calculator->charge($range['max']),
        ]);
        SubmitGeneration::dispatch($generation);

        return redirect()->route('generations.index');
    }
}
