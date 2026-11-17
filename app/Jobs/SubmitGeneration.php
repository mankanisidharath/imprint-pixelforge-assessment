<?php

namespace App\Jobs;

use App\Models\Generation;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use LogicException;

class SubmitGeneration implements ShouldQueue
{
    use Queueable;

    public function __construct(public Generation $generation) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        throw new LogicException('Implemented in a later phase');
    }
}
