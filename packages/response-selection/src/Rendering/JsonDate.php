<?php

declare(strict_types=1);

namespace Lahatre\ResponseSelection\Rendering;

use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Carbon\FactoryImmutable;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Support\Carbon as IlluminateCarbon;
use JsonSerializable;

/**
 * Formats supported date fields during JSON encoding without Carbon's ISO formatter. When the
 * native format cannot give the same result, Carbon's own jsonSerialize() handles the date.
 *
 * @internal
 */
final class JsonDate implements JsonSerializable
{
    private function __construct(private readonly CarbonInterface $date) {}

    /**
     * Classes whose JSON form is Carbon's own toJSON(): a subclass may change it.
     *
     * @var array<class-string<CarbonInterface>, true>
     */
    private const array CARBON_CLASSES = [
        Carbon::class           => true,
        CarbonImmutable::class  => true,
        IlluminateCarbon::class => true,
    ];

    /**
     * Defers formatting until JSON encoding, as Carbon does, so later accessors can still change
     * the date or its serializer before it is encoded.
     */
    public static function serialize(mixed $value): mixed
    {
        if (!is_object($value) || !isset(self::CARBON_CLASSES[$value::class])) {
            return $value;
        }

        /** @var CarbonInterface $value */
        return new self($value);
    }

    public function jsonSerialize(): mixed
    {
        $value = $this->date;
        $year = (int) $value->rawFormat('Y');

        // Carbon renders year 0 as null and years beyond four digits with six; a serializer set on
        // the date or on Carbon replaces toJSON(); a date made by another factory may have one
        if ($year < 1 || $year > 9999
            || $value->getClock() !== null
            || isset($value->getSettings()['toJsonFormat'])
            || isset(FactoryImmutable::getDefaultInstance()->getSettings()['toJsonFormat'])) {
            return $value->jsonSerialize();
        }

        return DateTimeImmutable::createFromInterface($value)
            ->setTimezone(new DateTimeZone('UTC'))
            ->format('Y-m-d\TH:i:s.u\Z');
    }
}
