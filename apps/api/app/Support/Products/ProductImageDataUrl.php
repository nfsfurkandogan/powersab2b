<?php

namespace App\Support\Products;

use Illuminate\Support\Str;

class ProductImageDataUrl
{
    public static function fromMeta(mixed $meta): ?string
    {
        $image = self::imageFromMeta($meta);

        if ($image !== null) {
            return sprintf('data:%s;base64,%s', $image['mime'], base64_encode($image['binary']));
        }

        return self::urlFromMeta($meta);
    }

    /**
     * @return array{mime:string,binary:string}|null
     */
    public static function imageFromMeta(mixed $meta): ?array
    {
        if (! is_array($meta)) {
            return null;
        }

        foreach (self::imageCandidates($meta) as $candidate) {
            $image = self::normalizeCandidateImage($candidate['value']);

            if ($image !== null) {
                return $image;
            }
        }

        return null;
    }

    public static function urlFromMeta(mixed $meta): ?string
    {
        if (! is_array($meta)) {
            return null;
        }

        foreach (self::imageCandidates($meta) as $candidate) {
            $value = trim($candidate['value']);

            if (Str::startsWith($value, ['http://', 'https://'])) {
                return $value;
            }
        }

        return null;
    }

    /**
     * @return array<int, array{value:string, score:int}>
     */
    private static function imageCandidates(array $meta): array
    {
        $candidates = [];
        self::collectNamedImageCandidates($meta, $candidates);
        self::collectCandidates(data_get($meta, 'integrations.logo.payload.raw'), 'integrations.logo.payload.raw', $candidates);
        self::collectCandidates(data_get($meta, 'integrations.logo.payload'), 'integrations.logo.payload', $candidates);

        usort($candidates, fn (array $left, array $right) => $right['score'] <=> $left['score']);

        return $candidates;
    }

    /**
     * @param  array<int, array{value:string, score:int}>  $candidates
     */
    private static function collectNamedImageCandidates(array $meta, array &$candidates): void
    {
        foreach ([
            'image_data_url',
            'image_url',
            'image_url1',
            'image_url_1',
            'image_uri',
            'image',
            'photo',
            'picture',
            'picture_url',
            'picture_url1',
            'photo_url',
            'photo_url1',
            'resim',
            'resim_url',
            'resim_url1',
            'integrations.logo.image_data_url',
            'integrations.logo.image_url',
            'integrations.logo.image',
            'integrations.logo.photo',
            'integrations.logo.resim',
            'integrations.logo.image_url1',
            'integrations.logo.photo_url1',
            'integrations.logo.picture_url1',
            'integrations.logo.resim_url1',
        ] as $path) {
            self::collectCandidates(data_get($meta, $path), $path, $candidates);
        }
    }

    /**
     * @param  array<int, array{value:string, score:int}>  $candidates
     */
    private static function collectCandidates(mixed $value, string $path, array &$candidates): void
    {
        if (is_string($value)) {
            $normalized = trim($value);

            if ($normalized !== '') {
                $candidates[] = [
                    'value' => $normalized,
                    'score' => self::scorePath($path),
                ];
            }

            return;
        }

        if (! is_array($value)) {
            return;
        }

        foreach ($value as $key => $nested) {
            self::collectCandidates($nested, $path.'.'.(string) $key, $candidates);
        }
    }

    private static function scorePath(string $path): int
    {
        $score = 0;
        $normalized = Str::lower($path);

        foreach (['image', 'photo', 'picture', 'resim', 'img', 'pic'] as $needle) {
            if (Str::contains($normalized, $needle)) {
                $score += 10;
            }
        }

        if (Str::contains($normalized, 'thumb')) {
            $score += 3;
        }

        if (Str::contains($normalized, 'raw')) {
            $score += 1;
        }

        return $score;
    }

    /**
     * @return array{mime:string,binary:string}|null
     */
    private static function normalizeCandidateImage(string $value): ?array
    {
        if (Str::startsWith(Str::lower($value), 'data:image/')) {
            [$meta, $payload] = array_pad(explode(',', $value, 2), 2, '');
            $decoded = base64_decode($payload, true);

            if (! is_string($decoded) || $decoded === '') {
                return null;
            }

            $image = self::extractImageBinary($decoded);

            if ($image !== null) {
                return $image;
            }

            if (preg_match('/^data:(image\/[a-z0-9.+-]+);base64$/i', $meta, $matches) === 1) {
                return [
                    'mime' => Str::lower($matches[1]),
                    'binary' => $decoded,
                ];
            }

            return null;
        }

        $normalized = preg_replace('/\s+/', '', $value);

        if (! is_string($normalized) || strlen($normalized) < 32) {
            return null;
        }

        $decoded = base64_decode($normalized, true);

        if (! is_string($decoded) || $decoded === '') {
            return null;
        }

        $image = self::extractImageBinary($decoded);

        if ($image === null) {
            return null;
        }

        return $image;
    }

    /**
     * @return array{mime:string,binary:string}|null
     */
    private static function extractImageBinary(string $binary): ?array
    {
        $mime = self::detectMime($binary);

        if ($mime !== null) {
            return [
                'mime' => $mime,
                'binary' => $binary,
            ];
        }

        foreach (self::embeddedImageOffsets($binary) as $offset) {
            $payload = substr($binary, $offset);
            $mime = self::detectMime($payload);

            if ($mime !== null) {
                return [
                    'mime' => $mime,
                    'binary' => $payload,
                ];
            }
        }

        return null;
    }

    /**
     * @return list<int>
     */
    private static function embeddedImageOffsets(string $binary): array
    {
        $offsets = [];

        foreach ([
            "\x89PNG\x0D\x0A\x1A\x0A",
            "\xFF\xD8\xFF",
            'GIF87a',
            'GIF89a',
            "II\x2A\x00",
            "MM\x00\x2A",
            'BM',
        ] as $signature) {
            $offset = strpos($binary, $signature);
            if ($offset !== false && $offset > 0) {
                $offsets[] = (int) $offset;
            }
        }

        $riffOffset = 0;
        while (($riffOffset = strpos($binary, 'RIFF', $riffOffset)) !== false) {
            if ($riffOffset > 0 && substr($binary, $riffOffset + 8, 4) === 'WEBP') {
                $offsets[] = (int) $riffOffset;
            }

            $riffOffset++;
        }

        foreach (['<svg', '<?xml'] as $signature) {
            $offset = stripos($binary, $signature);
            if ($offset !== false && $offset > 0) {
                $offsets[] = (int) $offset;
            }
        }

        $offsets = array_values(array_unique($offsets));
        sort($offsets);

        return $offsets;
    }

    private static function detectMime(string $binary): ?string
    {
        if (str_starts_with($binary, "\xFF\xD8\xFF")) {
            return 'image/jpeg';
        }

        if (str_starts_with($binary, "\x89PNG\x0D\x0A\x1A\x0A")) {
            return 'image/png';
        }

        if (str_starts_with($binary, 'GIF87a') || str_starts_with($binary, 'GIF89a')) {
            return 'image/gif';
        }

        if (substr($binary, 0, 4) === 'RIFF' && substr($binary, 8, 4) === 'WEBP') {
            return 'image/webp';
        }

        if (str_starts_with($binary, 'BM')) {
            return 'image/bmp';
        }

        if (str_starts_with($binary, "II\x2A\x00") || str_starts_with($binary, "MM\x00\x2A")) {
            return 'image/tiff';
        }

        $trimmed = ltrim($binary);

        if (str_starts_with($trimmed, '<svg') || (str_starts_with($trimmed, '<?xml') && str_contains($trimmed, '<svg'))) {
            return 'image/svg+xml';
        }

        return null;
    }
}
