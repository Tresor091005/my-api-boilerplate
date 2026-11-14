<?php

declare(strict_types=1);

namespace Lahatre\Customer\Http\Resources;

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
            ->field('created_at')
            ->field('updated_at')
            ->include('addresses', AddressResource::class)
            ->include('contacts', ContactResource::class)
            ->include('price_level', PriceLevelResource::class)
            ->include('profile_picture_files', CustomerFileAttachmentResource::class, relation: 'profilePictureFileAttachments');
    }
}
