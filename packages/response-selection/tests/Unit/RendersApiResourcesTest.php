<?php

declare(strict_types=1);

namespace Lahatre\ResponseSelection\Tests\Unit;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;
use Lahatre\ResponseSelection\ApiResource;
use Lahatre\ResponseSelection\Rendering\ResponseContext;
use Lahatre\ResponseSelection\ResourceDefinition;
use Lahatre\ResponseSelection\Testing\RendersApiResources;

uses(RendersApiResources::class);

final class RendersApiResourcesTestCity extends Model
{
    public $timestamps = false;

    protected $guarded = [];
}

final class RendersApiResourcesTestCityResource extends ApiResource
{
    protected static function define(ResourceDefinition $resource): void
    {
        $resource
            ->field('id')
            ->field('name')
            ->field('country');
    }
}

it('renders a model with the given selection, then leaves the context as it was', function (): void {
    $city = new RendersApiResourcesTestCity(['id' => 1, 'name' => 'Cotonou', 'country' => 'BJ']);

    expect($this->renderApiResource($city, RendersApiResourcesTestCityResource::class))->toBe(['id' => 1, 'name' => 'Cotonou', 'country' => 'BJ'])
        ->and($this->renderApiResource($city, RendersApiResourcesTestCityResource::class, 'name'))->toBe(['id' => 1, 'name' => 'Cotonou'])
        ->and(app(ResponseContext::class)->isActive())->toBeFalse();
});

it('rejects a name the Resource does not declare, as a request would', function (): void {
    $this->renderApiResource(new RendersApiResourcesTestCity(['id' => 1]), RendersApiResourcesTestCityResource::class, 'population');
})->throws(ValidationException::class);
