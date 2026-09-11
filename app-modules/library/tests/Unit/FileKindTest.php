<?php

declare(strict_types=1);

use Lahatre\Library\Enums\FileKind;

it('derives stable file kinds from server mime types', function (string $mimeType, FileKind $kind): void {
    expect(FileKind::fromMimeType($mimeType))->toBe($kind);
})->with([
    ['image/jpeg', FileKind::Image],
    ['application/pdf', FileKind::Document],
    ['text/csv', FileKind::Document],
    ['video/mp4', FileKind::Video],
    ['audio/mpeg', FileKind::Audio],
    ['application/zip', FileKind::Archive],
    ['application/octet-stream', FileKind::Other],
]);
