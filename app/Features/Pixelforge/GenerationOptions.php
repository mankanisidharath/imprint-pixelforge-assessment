<?php

namespace App\Features\Pixelforge;

final class GenerationOptions
{
    public const DEFAULT_SIZE = '1024x1024';

    public const SIZES = [self::DEFAULT_SIZE, '1536x1024', '1024x1536'];

    public const DEFAULT_QUALITY = 'medium';

    public const QUALITIES = ['low', self::DEFAULT_QUALITY, 'high'];

    public const MAX_PROMPT_LENGTH = 4000;

    public const MAX_REFERENCE_IMAGES = 4;
}
