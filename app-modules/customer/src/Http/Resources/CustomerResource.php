<?php

declare(strict_types=1);

namespace Lahatre\Customer\Http\Resources;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Lahatre\Catalog\Http\Resources\PriceLevelResource;
use Lahatre\Master\Http\Resources\AddressResource;
use Lahatre\Master\Http\Resources\ContactResource;
use Lahatre\ResponseSelection\ApiResource;
use Lahatre\ResponseSelection\ResourceDefinition;

class CustomerResource extends ApiResource
{
    protected static function define(ResourceDefinition $resource): void
    {
        $resource
            ->field('id')
            ->field('type')
            ->field('name')
            ->field('identification_number')
            ->field('is_active')
            ->field('price_level_id')
            ->field('payment_due_date', columns: ['payment_due_date'], computedBy: fn (Model $customer): ?string => self::date($customer->getAttribute('payment_due_date')))
            ->field('is_walk_in')
            ->field('created_at')
            ->field('updated_at')
            ->include('addresses', AddressResource::class)
            ->include('contacts', ContactResource::class)
            ->include('price_level', PriceLevelResource::class)
            ->include('profile_picture_files', CustomerFileAttachmentResource::class, relation: 'profilePictureFileAttachments');
    }

    private static function date(mixed $date): ?string
    {
        return $date instanceof CarbonInterface ? $date->toDateString() : null;
    }
}
