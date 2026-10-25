<?php

declare(strict_types=1);

namespace Lahatre\Customer\Http\Resources;

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
            ->include('addresses', AddressResource::class)
            ->include('contacts', ContactResource::class)
            ->include('profile_picture_files', CustomerFileAttachmentResource::class, relation: 'profilePictureFileAttachments')
            ->field('created_at')
            ->field('updated_at');
    }
}
