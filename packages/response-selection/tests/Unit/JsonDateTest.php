<?php

declare(strict_types=1);

use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Carbon\FactoryImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon as IlluminateCarbon;
use Lahatre\ResponseSelection\ApiResource;
use Lahatre\ResponseSelection\ApiResponse;
use Lahatre\ResponseSelection\Declaration\ResponseDeclaration;
use Lahatre\ResponseSelection\Enums\ResponseMode;
use Lahatre\ResponseSelection\Enums\ResponseShape;
use Lahatre\ResponseSelection\Rendering\JsonDate;
use Lahatre\ResponseSelection\Rendering\ResponseContext;
use Lahatre\ResponseSelection\ResourceDefinition;
use Lahatre\ResponseSelection\Selection\SelectionTree;

/**
 * A date whose JSON form its class changes: only Carbon's own classes may skip Carbon.
 */
final class JsonDateTestCustomDate extends CarbonImmutable
{
    public function toJSON(): string
    {
        return 'custom';
    }
}

/**
 * Unsaved; reads its dates through Eloquent casts, as a Resource field does.
 */
final class JsonDateTestEvent extends Model
{
    public $timestamps = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'starts_at' => 'immutable_datetime',
            'ends_at'   => 'datetime',
            'held_on'   => 'immutable_date',
            'cancelled' => 'immutable_datetime',
        ];
    }
}

final class JsonDateTestEventResource extends ApiResource
{
    protected static function define(ResourceDefinition $resource): void
    {
        $resource
            ->field('id')
            ->field('starts_at')
            ->field('ends_at')
            ->field('held_on')
            ->field('cancelled')
            ->field('local_start', columns: ['starts_at'], computedBy: fn (Model $event): mixed => $event->getAttribute('starts_at')?->setTimezone('America/St_Johns'))
            ->field('schedule', columns: ['starts_at'], computedBy: fn (Model $event): array => ['starts_at' => $event->getAttribute('starts_at')]);
    }
}

final class JsonDateTestChangingEvent extends Model
{
    public CarbonInterface $date;

    public Closure $change;

    public function getObservedAtAttribute(mixed $value): CarbonInterface
    {
        return $this->date;
    }

    public function getLaterAttribute(mixed $value): bool
    {
        ($this->change)($this->date);

        return true;
    }
}

final class JsonDateTestChangingEventResource extends ApiResource
{
    protected static function define(ResourceDefinition $resource): void
    {
        $resource
            ->field('observed_at', columns: [])
            ->field('later', columns: []);
    }
}

afterEach(function (): void {
    Carbon::serializeUsing(null);
    Carbon::setLocale('en');
});

it('gives a date the exact JSON Carbon gives it', function (Closure $date, bool $skipsCarbon): void {
    $date = $date();
    $value = JsonDate::serialize($date);

    expect(json_encode($value, JSON_THROW_ON_ERROR))->toBe(json_encode($date, JSON_THROW_ON_ERROR));

    if ($skipsCarbon) {
        expect($value)->toBeInstanceOf(JsonDate::class);
    }
})->with([
    'immutable UTC with microseconds'       => [fn (): CarbonImmutable => CarbonImmutable::parse('2026-10-26 08:00:00.123456', 'UTC'), true],
    'mutable in an African timezone'        => [fn (): Carbon => Carbon::parse('2026-10-26 23:30:00.000001', 'Africa/Porto-Novo'), true],
    'Laravel Carbon with an odd offset'     => [fn (): IlluminateCarbon => IlluminateCarbon::parse('2026-01-01 00:10:00', '+05:45'), true],
    'ambiguous hour of a DST change'        => [fn (): CarbonImmutable => CarbonImmutable::parse('2026-11-01 01:30:00', 'America/St_Johns'), true],
    'spring DST gap'                        => [fn (): CarbonImmutable => CarbonImmutable::parse('2026-03-08 02:30:00', 'America/New_York'), true],
    'year 1'                                => [fn (): CarbonImmutable => CarbonImmutable::parse('0001-01-01 00:00:00', 'UTC'), true],
    'year 1 that is year 0 in UTC'          => [fn (): CarbonImmutable => CarbonImmutable::parse('0001-01-01 05:00:00', '+14:00'), true],
    'year 999 moving to another day in UTC' => [fn (): CarbonImmutable => CarbonImmutable::parse('0999-12-31 23:59:59.999999', 'Asia/Tokyo'), true],
    'year 9999 that is year 10000 in UTC'   => [fn (): CarbonImmutable => CarbonImmutable::parse('9999-12-31 23:59:59', '-05:00'), true],
    'year 0, rendered null by Carbon'       => [fn (): ?CarbonImmutable => CarbonImmutable::create(0, 6, 1, 0, 0, 0, 'UTC'), false],
    'year 10000'                            => [fn (): ?CarbonImmutable => CarbonImmutable::create(10000, 1, 1, 0, 0, 0, 'UTC'), false],
    'negative year'                         => [fn (): ?CarbonImmutable => CarbonImmutable::create(-44, 3, 15, 0, 0, 0, 'UTC'), false],
    'Arabic locale on the date'             => [fn () => CarbonImmutable::parse('2026-10-26 08:00:00', 'UTC')->locale('ar'), true],
    'Nepali locale for every date'          => [function (): CarbonImmutable {
        Carbon::setLocale('ne');

        return CarbonImmutable::parse('2026-10-26 08:00:00', 'Asia/Kathmandu');
    }, true],
    'serializer set on the date'    => [fn (): CarbonImmutable => CarbonImmutable::parse('2026-10-26 08:00:00', 'UTC')->settings(['toJsonFormat' => 'd/m/Y']), false],
    'serializer set for every date' => [function (): CarbonImmutable {
        Carbon::serializeUsing(fn (CarbonImmutable|Carbon $date): string => $date->format('U'));

        return CarbonImmutable::parse('2026-10-26 08:00:00', 'UTC');
    }, false],
    'date made by a factory with a serializer' => [fn (): CarbonImmutable => new FactoryImmutable(['toJsonFormat' => 'd/m/Y'])->parse('2026-10-26 08:00:00', 'UTC'), false],
    'Carbon subclass'                          => [fn (): JsonDateTestCustomDate => JsonDateTestCustomDate::parse('2026-10-26 08:00:00', 'UTC'), false],
]);

it('leaves values other than Carbon dates as they are', function (mixed $value): void {
    expect(JsonDate::serialize($value))->toBe($value);
})->with([
    'native date' => [new DateTimeImmutable('2026-10-26 08:00:00', new DateTimeZone('UTC'))],
    'date string' => ['2026-10-26T08:00:00.000000Z'],
    'null'        => [null],
    'array'       => [['2026-10-26']],
]);

it('renders the dates of a Resource exactly as Laravel encodes the Carbon values', function (): void {
    app(ResponseContext::class)->start(ResponseMode::Resource, SelectionTree::rootFields(), new ResponseDeclaration(JsonDateTestEventResource::class, ResponseShape::Item, [200]));
    $event = new JsonDateTestEvent([
        'id'        => 7,
        'starts_at' => '2026-10-26 08:00:00',
        'ends_at'   => '2026-10-26 18:45:00',
        'held_on'   => '2026-10-26',
        'cancelled' => null,
    ]);
    $startsAt = $event->getAttribute('starts_at');
    $expected = new JsonResponse(['data' => [
        'id'          => 7,
        'starts_at'   => $startsAt,
        'ends_at'     => $event->getAttribute('ends_at'),
        'held_on'     => $event->getAttribute('held_on'),
        'cancelled'   => null,
        'local_start' => $startsAt->setTimezone('America/St_Johns'),
        'schedule'    => ['starts_at' => $startsAt],
    ]])->getContent();

    $response = ApiResponse::item($event, JsonDateTestEventResource::class);

    expect($response->getContent())->toBe($expected)
        ->and($response->getContent())->toContain('"starts_at":"2026-10-26T08:00:00.000000Z"');
});

it('keeps date serialization after later accessors have run', function (Closure $date, Closure $change): void {
    $makeEvent = function () use ($date, $change): JsonDateTestChangingEvent {
        $event = new JsonDateTestChangingEvent;
        $event->date = $date();
        $event->change = $change;

        return $event;
    };
    $original = $makeEvent();
    $expected = new JsonResponse(['data' => [
        'observed_at' => $original->getAttribute('observed_at'),
        'later'       => $original->getAttribute('later'),
    ]])->getContent();
    app(ResponseContext::class)->start(ResponseMode::Resource, SelectionTree::rootFields(), new ResponseDeclaration(JsonDateTestChangingEventResource::class, ResponseShape::Item, [200]));

    $response = ApiResponse::item($makeEvent(), JsonDateTestChangingEventResource::class);

    expect($response->getContent())->toBe($expected);
})->with([
    'mutable value changed by a later accessor' => [
        fn (): Carbon => Carbon::parse('2026-10-26 08:00:00', 'UTC'),
        fn (CarbonInterface $date): CarbonInterface => $date->addDay(),
    ],
    'immutable date serializer changed by a later accessor' => [
        fn (): CarbonImmutable => CarbonImmutable::parse('2026-10-26 08:00:00', 'UTC'),
        fn (CarbonInterface $date): CarbonInterface => $date->settings(['toJsonFormat' => 'd/m/Y']),
    ],
]);
