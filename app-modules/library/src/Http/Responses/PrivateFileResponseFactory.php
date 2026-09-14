<?php

declare(strict_types=1);

namespace Lahatre\Library\Http\Responses;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Lahatre\Library\Models\File;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class PrivateFileResponseFactory
{
    public function make(Request $request, File $file): Response
    {
        $disk = Storage::disk($file->storage_disk);

        if (!$disk->exists($file->storage_key)) {
            throw new NotFoundHttpException(__('library::exceptions.file_content_missing'));
        }

        $headers = $this->headers($file);

        if ($this->isNotModified($request, $file)) {
            unset($headers['Content-Disposition'], $headers['Content-Length'], $headers['Content-Type']);

            return new Response(null, Response::HTTP_NOT_MODIFIED, $headers);
        }

        $stream = $disk->readStream($file->storage_key);

        if ($stream === false) {
            throw new NotFoundHttpException(__('library::exceptions.file_content_missing'));
        }

        return new StreamedResponse(
            static function () use ($stream): void {
                fpassthru($stream);
                fclose($stream);
            },
            Response::HTTP_OK,
            $headers,
        );
    }

    /** @return array<string, string> */
    private function headers(File $file): array
    {
        $inlineMimeTypes = (array) config('library.serving.inline_mimes', []);
        $disposition = in_array($file->mime_type, $inlineMimeTypes, true) ? 'inline' : 'attachment';
        $fallbackName = str_replace(['%', '/', '\\'], '-', Str::ascii($file->name));

        return [
            'Cache-Control'       => (string) config('library.serving.cache_control', 'private, no-cache'),
            'Content-Disposition' => HeaderUtils::makeDisposition(
                $disposition,
                $file->name,
                $fallbackName === '' ? 'download' : $fallbackName,
            ),
            'Content-Length'         => (string) $file->size,
            'Content-Type'           => $file->mime_type,
            'ETag'                   => '"'.$file->checksum.'"',
            'Last-Modified'          => $file->created_at?->format(DATE_RFC7231) ?? now()->format(DATE_RFC7231),
            'X-Content-Type-Options' => 'nosniff',
        ];
    }

    private function isNotModified(Request $request, File $file): bool
    {
        $etag = '"'.$file->checksum.'"';
        $ifNoneMatch = $request->headers->get('If-None-Match');

        if ($ifNoneMatch !== null) {
            $candidates = array_map('trim', explode(',', $ifNoneMatch));

            return in_array('*', $candidates, true) || in_array($etag, $candidates, true);
        }

        $ifModifiedSince = $request->headers->get('If-Modified-Since');

        if ($ifModifiedSince === null || $file->created_at === null) {
            return false;
        }

        $timestamp = strtotime($ifModifiedSince);

        return $timestamp !== false && $file->created_at->timestamp <= $timestamp;
    }
}
