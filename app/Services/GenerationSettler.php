<?php

namespace App\Services;

use LogicException;

class GenerationSettler
{
    public function apply(array $snapshot): string
    {
        throw new LogicException('Implemented in a later phase');
    }
}
