<?php

namespace App\Features\Pixelforge;

final class TokenType
{
    public const INPUT_TEXT = 'input_text_tokens';

    public const INPUT_IMAGE = 'input_image_tokens';

    public const OUTPUT = 'output_tokens';

    public const REASONING = 'reasoning_tokens';

    public const BILLABLE_KEYS = [
        self::INPUT_TEXT,
        self::INPUT_IMAGE,
        self::OUTPUT,
        self::REASONING,
    ];
}
