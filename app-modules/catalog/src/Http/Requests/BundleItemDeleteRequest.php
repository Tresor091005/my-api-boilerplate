<?php

declare(strict_types=1);

namespace Lahatre\Catalog\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Lahatre\Catalog\Models\Bundle;
use Lahatre\Shared\Rules\BulkExists;

final class BundleItemDeleteRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $bundle = $this->route('bundle');

        return [
            'ids' => [
                'required',
                'array',
                'min:1',
                'max:50',
                new BulkExists('catalog_bundle_items', 'id', 'id', 'uuid', true, [
                    'organization_id' => currentOrganizationId(),
                    'bundle_id'       => $bundle instanceof Bundle ? $bundle->id : null,
                ]),
            ],
            'ids.*' => ['required', 'uuid', 'distinct'],
        ];
    }
}
