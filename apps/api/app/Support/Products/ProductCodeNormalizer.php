<?php

namespace App\Support\Products;

use Illuminate\Support\Str;

class ProductCodeNormalizer
{
    public static function normalize(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $trimmed = trim($value);
        if ($trimmed === '') {
            return null;
        }

        $normalized = preg_replace('/[^A-Z0-9]+/u', '', Str::upper($trimmed));

        if (! is_string($normalized) || $normalized === '') {
            return null;
        }

        return $normalized;
    }
}
