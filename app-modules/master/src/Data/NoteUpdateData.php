<?php

declare(strict_types=1);

namespace Lahatre\Master\Data;

use Carbon\CarbonImmutable;
use Lahatre\Master\Enums\NoteKind;
use Lahatre\Shared\Data\MissingValue;
use Lahatre\Shared\Data\MissingValueReader;

final readonly class NoteUpdateData
{
    private function __construct(
        public MissingValue|string $body,
        public MissingValue|NoteKind $kind,
        public MissingValue|CarbonImmutable|null $expiresAt,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     * @param  list<string>  $missingFields
     */
    public static function fromArray(array $data, array $missingFields = []): self
    {
        $read = MissingValueReader::fromArray($data, $missingFields);
        $kind = $read->get('kind');
        $expiresAt = $read->get('expires_at');

        return new self(
            body: $read->get('body'),
            kind: $kind instanceof MissingValue ? $kind : NoteKind::from((string) $kind),
            expiresAt: $expiresAt instanceof MissingValue || $expiresAt === null
                ? $expiresAt
                : CarbonImmutable::parse($expiresAt),
        );
    }
}
