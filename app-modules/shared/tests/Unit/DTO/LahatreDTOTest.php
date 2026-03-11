<?php

declare(strict_types=1);

namespace Lahatre\Shared\Tests\Unit\DTO;

use Lahatre\Shared\DTO\LahatreDTO;
use Illuminate\Validation\ValidationException;
use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Validator;

class NestedDTO extends LahatreDTO
{
    public string $name;

    protected function defaults(): array
    {
        return [];
    }

    protected function rules(): array
    {
        return [
            'name' => 'required|string',
        ];
    }

    protected function casts(): array
    {
        return [];
    }
}

class TestDTO extends LahatreDTO
{
    public string $title;
    public string $slug;
    public int $age;
    public bool $is_active;
    public ?\Carbon\CarbonImmutable $published_at;
    public ?NestedDTO $nested;
    /** @var NestedDTO[] */
    public array $items;

    protected function defaults(): array
    {
        return [
            'age' => 18,
            'is_active' => false,
        ];
    }

    protected function beforeValidation(array $data): array
    {
        if (isset($data['title'])) {
            $data['slug'] = strtolower(str_replace(' ', '-', $data['title']));
        }
        return $data;
    }

    protected function rules(): array
    {
        return [
            'title' => 'required|string',
            'slug' => 'required|string',
            'age' => 'required|integer',
            'is_active' => 'required|boolean',
            'published_at' => 'nullable|date',
            'nested' => 'nullable|array',
            'items' => 'nullable|array',
        ];
    }

    protected function casts(): array
    {
        return [
            'nested' => NestedDTO::class,
            'items' => 'array:' . NestedDTO::class,
            'age' => 'int',
            'is_active' => 'bool',
            'published_at' => 'immutable_datetime',
        ];
    }
}

it('casts simple types from strings', function () {
    $dto = new TestDTO([
        'title' => 'Hello',
        'age' => '25',
        'is_active' => '1'
    ]);

    expect($dto->age)->toBe(25)
        ->and($dto->is_active)->toBeTrue();
});

it('casts immutable datetimes', function () {
    $dto = new TestDTO([
        'title' => 'Hello',
        'published_at' => '2026-03-11 10:00:00'
    ]);

    expect($dto->published_at)->toBeInstanceOf(\Carbon\CarbonImmutable::class)
        ->and($dto->published_at->format('Y-m-d'))->toBe('2026-03-11');
});

it('merges defaults', function () {
    $dto = new TestDTO(['title' => 'Hello World']);

    expect($dto->title)->toBe('Hello World')
        ->and($dto->age)->toBe(18);
});

it('executes beforeValidation hook', function () {
    $dto = new TestDTO(['title' => 'Hello World']);

    expect($dto->slug)->toBe('hello-world');
});

it('validates data', function () {
    expect(fn() => new TestDTO([]))->toThrow(ValidationException::class);
});

it('casts nested DTO', function () {
    $dto = new TestDTO([
        'title' => 'Hello',
        'nested' => ['name' => 'John Doe']
    ]);

    expect($dto->nested)->toBeInstanceOf(NestedDTO::class)
        ->and($dto->nested->name)->toBe('John Doe');
});

it('casts array of DTOs', function () {
    $dto = new TestDTO([
        'title' => 'Hello',
        'items' => [
            ['name' => 'Item 1'],
            ['name' => 'Item 2'],
        ]
    ]);

    expect($dto->items)->toBeArray()
        ->and($dto->items)->toHaveCount(2)
        ->and($dto->items[0])->toBeInstanceOf(NestedDTO::class)
        ->and($dto->items[1]->name)->toBe('Item 2');
});

it('converts to array recursively', function () {
    $dto = new TestDTO([
        'title' => 'Hello',
        'nested' => ['name' => 'John Doe'],
        'items' => [['name' => 'Item 1']]
    ]);

    $array = $dto->toArray();

    expect($array['nested'])->toBeArray()
        ->and($array['nested']['name'])->toBe('John Doe')
        ->and($array['items'][0]['name'])->toBe('Item 1');
});

it('handles forUpdate', function () {
    // Mock a model
    $model = new class extends Model {
        protected $attributes = [
            'id' => 123,
            'title' => 'Old Title',
            'age' => 30
        ];
        public function getKey() { return 123; }
        public function getAttributes() { return $this->attributes; }
    };

    $request = new Request(['title' => 'New Title']);
    
    $dto = TestDTO::forUpdate($request, $model);

    expect($dto->title)->toBe('New Title')
        ->and($dto->age)->toBe(30)
        ->and($dto->toArray()['title'])->toBe('New Title');
});

it('resolves from JSON', function () {
    $json = json_encode([
        'title' => 'JSON Title',
        'age' => 40,
        'is_active' => true
    ]);

    $dto = TestDTO::fromJson($json);

    expect($dto->title)->toBe('JSON Title')
        ->and($dto->age)->toBe(40);
});

it('sanitizes strings automatically', function () {
    $dto = new TestDTO([
        'title' => '  Hello   World  ',
        'age' => 20,
        'is_active' => true
    ]);

    expect($dto->title)->toBe('Hello World');
});
