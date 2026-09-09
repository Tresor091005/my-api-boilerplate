<?php

declare(strict_types=1);

namespace Lahatre\Catalog\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Lahatre\Catalog\Models\Service;
use Lahatre\Catalog\Rules\ValidServiceDeliverableTemplates;

final class ServiceUpdateRequest extends FormRequest
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
        $service = $this->route('service');

        return [
            'name' => ['string', 'max:150'],
            'sku'  => [
                'string',
                'max:100',
                Rule::unique('catalog_items', 'sku')
                    ->where('organization_id', currentOrganizationId())
                    ->ignore($service instanceof Service ? $service->id : null),
            ],
            'unit_group_id'         => ['prohibited'],
            'is_active'             => ['boolean'],
            'deliverable_templates' => [
                'array',
                'min:1',
                'max:100',
                ...($service instanceof Service ? [
                    new ValidServiceDeliverableTemplates($service, currentOrganizationId()),
                ] : []),
            ],
            'deliverable_templates.*.id'   => ['nullable', 'uuid', 'distinct'],
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
