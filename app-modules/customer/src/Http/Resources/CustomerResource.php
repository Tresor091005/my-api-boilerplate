<?php

declare(strict_types=1);

namespace Lahatre\Customer\Http\Resources;

use Lahatre\Master\Http\Resources\AddressApiResource;
use Lahatre\Master\Http\Resources\ContactApiResource;
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
            ->include('addresses', AddressApiResource::class)
            ->include('contacts', ContactApiResource::class)
            ->include('profile_picture_files', CustomerFileAttachmentResource::class, relation: 'profilePictureFileAttachments')
            ->field('created_at')
            ->field('updated_at');
    }
}
