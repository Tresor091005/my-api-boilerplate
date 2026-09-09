<?php

declare(strict_types=1);

namespace Lahatre\Catalog\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Lahatre\Shared\Rules\BulkExists;

final class ServiceCreateRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $prepared = [];

        if (is_string($this->input('name'))) {
            $prepared['name'] = Str::sanitize($this->input('name'));
        }
        if (is_string($this->input('sku'))) {
            $prepared['sku'] = Str::toUpper($this->input('sku'));
        }
        if (is_array($this->input('deliverable_templates'))) {
            $prepared['deliverable_templates'] = array_map(
                $this->normalizeTemplate(...),
                $this->input('deliverable_templates'),
            );
        }

        $this->merge($prepared);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $organizationId = currentOrganizationId();

        return [
            'name' => ['required', 'string', 'max:150'],
            'sku'  => [
                'nullable',
                'string',
                'max:100',
                Rule::unique('catalog_items', 'sku')->where('organization_id', $organizationId),
            ],
            'unit_group_id' => [
                'required',
                'uuid',
                new BulkExists('master_unit_groups', 'id', 'unit_group_id', 'uuid', true, [
                    fn ($query) => $query->whereNull('organization_id')->orWhere('organization_id', $organizationId),
                ]),
            ],
            'is_active'                    => ['boolean'],
            'deliverable_templates'        => ['required', 'array', 'min:1', 'max:100'],
            'deliverable_templates.*.id'   => ['prohibited'],
            'deliverable_templates.*.name' => ['required', 'string', 'max:150'],
        ];
    }

    private function normalizeTemplate(mixed $template): mixed
    {
        if (!is_array($template) || !is_string($template['name'] ?? null)) {
            return $template;
        }

        return [...$template, 'name' => Str::sanitize($template['name'])];
    }
}
