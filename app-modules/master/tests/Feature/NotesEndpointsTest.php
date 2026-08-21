<?php

declare(strict_types=1);

use Lahatre\Master\Enums\NoteKind;
use Lahatre\Master\Enums\NoteVisibility;

it('exposes the supported note classifications', function (): void {
    expect(NoteKind::cases())->toHaveCount(4)
        ->and(NoteVisibility::cases())->toHaveCount(3);
});
