<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Support\Products\ProductImageDataUrl;
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

    private function etag(Product $product, int $width, ?string $path, string $fallbackBinary): string
    {
        $size = $path !== null && is_file($path) ? (int) filesize($path) : strlen($fallbackBinary);
        $mtime = $path !== null && is_file($path) ? (int) filemtime($path) : $this->version($product);

        return '"'.sha1($product->id.'|'.$this->version($product).'|'.$width.'|'.$size.'|'.$mtime).'"';
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
