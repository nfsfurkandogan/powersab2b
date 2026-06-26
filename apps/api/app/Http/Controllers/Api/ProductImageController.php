<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Support\Products\ProductImageDataUrl;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class ProductImageController extends Controller
{
    public function __invoke(Request $request, Product $product): Response|RedirectResponse
    {
        abort_unless($product->is_active, SymfonyResponse::HTTP_NOT_FOUND);

        $image = ProductImageDataUrl::imageFromMeta($product->meta);

        if ($image === null) {
            $imageUrl = ProductImageDataUrl::urlFromMeta($product->meta);

            abort_if($imageUrl === null, SymfonyResponse::HTTP_NOT_FOUND);

            $remoteImage = $this->remoteImage($imageUrl, (int) $product->id);
            if ($remoteImage !== null) {
                $etag = $this->etag(
                    $product,
                    $request->integer('w', 192),
                    $remoteImage['path'],
                    null
                );

                if ($request->headers->get('If-None-Match') === $etag) {
                    return response('', SymfonyResponse::HTTP_NOT_MODIFIED)
                        ->header('ETag', $etag)
                        ->header('Cache-Control', 'private, max-age=2592000, immutable');
                }

                return response(file_get_contents($remoteImage['path']) ?: '', SymfonyResponse::HTTP_OK)
                    ->header('Content-Type', $remoteImage['mime'])
                    ->header('Content-Length', (string) filesize($remoteImage['path']))
                    ->header('Cache-Control', 'private, max-age=2592000, immutable')
                    ->header('ETag', $etag)
                    ->header('X-Content-Type-Options', 'nosniff');
            }

            return redirect()
                ->away($imageUrl)
                ->header('Cache-Control', 'private, max-age=604800')
                ->header('X-Content-Type-Options', 'nosniff');
        }

        $width = max(64, min(1280, (int) $request->integer('w', 192)));
        $cached = $this->cachedThumbnail($product, $image, $width);
        $etag = $this->etag($product, $width, $cached['path'] ?? null, $image['binary']);

        if ($request->headers->get('If-None-Match') === $etag) {
            return response('', SymfonyResponse::HTTP_NOT_MODIFIED)
                ->header('ETag', $etag)
                ->header('Cache-Control', 'private, max-age=2592000, immutable');
        }

        if ($cached !== null) {
            return response(file_get_contents($cached['path']) ?: '', SymfonyResponse::HTTP_OK)
                ->header('Content-Type', $cached['mime'])
                ->header('Content-Length', (string) filesize($cached['path']))
                ->header('Cache-Control', 'private, max-age=2592000, immutable')
                ->header('ETag', $etag)
                ->header('X-Content-Type-Options', 'nosniff');
        }

        return response($image['binary'], SymfonyResponse::HTTP_OK)
            ->header('Content-Type', $image['mime'])
            ->header('Content-Length', (string) strlen($image['binary']))
            ->header('Cache-Control', 'private, max-age=604800')
            ->header('ETag', $etag)
            ->header('X-Content-Type-Options', 'nosniff');
    }

    /**
     * @param  array{mime:string,binary:string}  $image
     * @return array{path:string,mime:string}|null
     */
    private function cachedThumbnail(Product $product, array $image, int $width): ?array
    {
        if ($image['mime'] === 'image/svg+xml' || ! function_exists('imagecreatefromstring')) {
            return null;
        }

        $version = $this->version($product);
        $directory = storage_path('app/product-image-cache');
        $path = $directory.'/product-'.$product->id.'-'.$version.'-'.$width.'.jpg';

        if (is_file($path) && filesize($path) > 0) {
            return ['path' => $path, 'mime' => 'image/jpeg'];
        }

        if (! is_dir($directory) && ! mkdir($directory, 0775, true) && ! is_dir($directory)) {
            return null;
        }

        $thumbnail = $this->makeThumbnail($image['binary'], $width);

        if ($thumbnail === null) {
            return null;
        }

        file_put_contents($path, $thumbnail, LOCK_EX);

        return ['path' => $path, 'mime' => 'image/jpeg'];
    }

    private function makeThumbnail(string $binary, int $maxSize): ?string
    {
        $source = @imagecreatefromstring($binary);

        if ($source === false) {
            return null;
        }

        $sourceWidth = imagesx($source);
        $sourceHeight = imagesy($source);

        if ($sourceWidth <= 0 || $sourceHeight <= 0) {
            imagedestroy($source);

            return null;
        }

        $scale = min(1, $maxSize / max($sourceWidth, $sourceHeight));
        $targetWidth = max(1, (int) round($sourceWidth * $scale));
        $targetHeight = max(1, (int) round($sourceHeight * $scale));
        $target = imagecreatetruecolor($targetWidth, $targetHeight);

        if ($target === false) {
            imagedestroy($source);

            return null;
        }

        $background = imagecolorallocate($target, 255, 255, 255);
        imagefill($target, 0, 0, $background);
        imagecopyresampled($target, $source, 0, 0, 0, 0, $targetWidth, $targetHeight, $sourceWidth, $sourceHeight);

        ob_start();
        imagejpeg($target, null, 90);
        $thumbnail = ob_get_clean();

        imagedestroy($target);
        imagedestroy($source);

        return is_string($thumbnail) && $thumbnail !== '' ? $thumbnail : null;
    }

    private function etag(Product $product, int $width, ?string $path, ?string $fallbackBinary): string
    {
        $size = $path !== null && is_file($path) ? (int) filesize($path) : strlen((string) $fallbackBinary);
        $mtime = $path !== null && is_file($path) ? (int) filemtime($path) : $this->version($product);

        return '"'.sha1($product->id.'|'.$this->version($product).'|'.$width.'|'.$size.'|'.$mtime).'"';
    }

    private function remoteImage(string $imageUrl, int $productId): ?array
    {
        if (! filter_var($imageUrl, FILTER_VALIDATE_URL)) {
            return null;
        }

        if (! $this->isRemoteImageFetchEnabled() || ! $this->isAllowedRemoteImageHost($imageUrl)) {
            return null;
        }

        $cacheTtlSeconds = (int) (env('PRODUCT_IMAGE_REMOTE_CACHE_TTL_SECONDS', 86400));
        $cacheDir = storage_path('app/product-image-remote-cache');
        if (! is_dir($cacheDir) && ! mkdir($cacheDir, 0775, true) && ! is_dir($cacheDir)) {
            return null;
        }

        $hash = hash('sha256', $imageUrl);
        $subFolder = $cacheDir.'/'.substr($hash, 0, 2).'/'.substr($hash, 2, 2);
        if (! is_dir($subFolder) && ! mkdir($subFolder, 0775, true) && ! is_dir($subFolder)) {
            return null;
        }

        $path = $subFolder.'/'.$hash;
        $metaPath = $path.'.meta';

        if (is_file($path) && is_file($metaPath)) {
            $meta = json_decode((string) file_get_contents($metaPath), true);
            if (is_array($meta)) {
                $fetchedAt = (int) ($meta['fetched_at'] ?? 0);
                if (time() - $fetchedAt < $cacheTtlSeconds) {
                    $mime = is_string($meta['mime'] ?? null) ? $meta['mime'] : null;
                    if (is_string($mime) && $mime !== '' && is_file($path) && filesize($path) > 0) {
                        return ['path' => $path, 'mime' => $mime];
                    }
                }
            }
        }

        return $this->downloadAndCacheRemoteImage($imageUrl, $path, $metaPath, $productId);
    }

    private function isRemoteImageFetchEnabled(): bool
    {
        return filter_var(env('PRODUCT_IMAGE_FETCH_REMOTE', false), FILTER_VALIDATE_BOOLEAN);
    }

    private function isAllowedRemoteImageHost(string $imageUrl): bool
    {
        $hostList = env('PRODUCT_IMAGE_ALLOWED_HOSTS', '');
        $allowedHosts = array_values(array_filter(array_map(
            static fn (string $host): string => strtolower(trim($host)),
            preg_split('/\s*,\s*/', $hostList ?? '', -1, PREG_SPLIT_NO_EMPTY)
        )));

        if ($allowedHosts === []) {
            return false;
        }

        $hostname = strtolower((string) parse_url($imageUrl, PHP_URL_HOST));
        if ($hostname === '') {
            return false;
        }

        foreach ($allowedHosts as $allowedHost) {
            if ($allowedHost === '') {
                continue;
            }

            $normalizedAllowed = ltrim($allowedHost, '*');
            $normalizedAllowed = ltrim($normalizedAllowed, '.');

            if (
                $hostname === $normalizedAllowed
                || str_ends_with($hostname, '.'.$normalizedAllowed)
            ) {
                return true;
            }
        }

        return false;
    }

    private function downloadAndCacheRemoteImage(
        string $imageUrl,
        string $path,
        string $metaPath,
        int $productId
    ): ?array {
        try {
            $timeoutSeconds = (int) (env('PRODUCT_IMAGE_REMOTE_TIMEOUT_SECONDS', 8));
            $maxBytes = (int) (env('PRODUCT_IMAGE_REMOTE_MAX_BYTES', 8_000_000));

            $response = Http::timeout($timeoutSeconds)
                ->withHeaders([
                    'Accept' => 'image/*',
                    'User-Agent' => 'PowersaB2B Product Image Fetcher',
                ])
                ->get($imageUrl);

            if (! $response->successful()) {
                return null;
            }

            $contentType = trim((string) ($response->header('Content-Type') ?? ''));
            $contentLength = (int) ($response->header('Content-Length') ?? 0);
            if ($contentLength > 0 && $contentLength > $maxBytes) {
                return null;
            }

            $binary = $response->body();
            if (strlen($binary) === 0 || strlen($binary) > $maxBytes) {
                return null;
            }

            $mime = $this->extractImageMimeFromContent($binary, $contentType);
            if ($mime === null) {
                return null;
            }

            if (! str_starts_with($mime, 'image/')) {
                return null;
            }

            if (file_put_contents($path, $binary, LOCK_EX) === false) {
                return null;
            }

            $meta = [
                'mime' => $mime,
                'fetched_at' => time(),
                'product_id' => $productId,
                'source' => $imageUrl,
            ];

            file_put_contents($metaPath, json_encode($meta), LOCK_EX);

            return ['path' => $path, 'mime' => $mime];
        } catch (RequestException | ConnectionException) {
            return null;
        }
    }

    private function extractImageMimeFromContent(string $binary, string $contentType): ?string
    {
        if ($contentType !== '' && str_starts_with($contentType, 'image/')) {
            return explode(';', $contentType, 2)[0];
        }

        $info = @finfo_open(FILEINFO_MIME_TYPE);
        if ($info === false) {
            return null;
        }

        $detected = finfo_buffer($info, $binary);
        finfo_close($info);

        if (is_string($detected) && $detected !== '' && str_starts_with($detected, 'image/')) {
            return explode(';', $detected, 2)[0];
        }

        return null;
    }

    private function version(Product $product): int
    {
        $updatedAt = $product->updated_at;

        if ($updatedAt !== null) {
            return $updatedAt->getTimestamp();
        }

        return crc32((string) $product->id);
    }
}
