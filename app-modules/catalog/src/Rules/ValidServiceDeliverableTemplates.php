<?php

declare(strict_types=1);

namespace Lahatre\Catalog\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\ValidatorAwareRule;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Translation\PotentiallyTranslatedString;
use Illuminate\Validation\Validator;
use Lahatre\Catalog\Models\Service;

final class ValidServiceDeliverableTemplates implements ValidationRule, ValidatorAwareRule
{
    private ?Validator $validator = null;

    public function __construct(
        private readonly Service $service,
        private readonly string $organizationId,
    ) {}

    public function setValidator(Validator $validator): static
    {
        $this->validator = $validator;

        return $this;
    }

    /** @param Closure(string): PotentiallyTranslatedString $fail */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (!is_array($value)) {
            return;
        }

        /** @var Collection<int, array<string, mixed>> $templates */
        $templates = collect($value)
            ->filter(fn (mixed $template): bool => is_array($template))
            ->values();
        $templateIds = $templates
            ->pluck('id')
            ->filter(fn (mixed $id): bool => is_string($id) && Str::isUuid($id));

        if ($templateIds->isEmpty()) {
            return;
        }

        $availableIds = DB::table('catalog_service_deliverable_templates')
            ->where('organization_id', $this->organizationId)
            ->where('service_id', $this->service->id)
            ->whereNull('deleted_at')
            ->whereIn('id', $templateIds->unique()->values()->all())
            ->pluck('id')
            ->flip();

        $this->addUnavailableErrors($attribute, $templates, $availableIds, $fail);
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $templates
     * @param  Collection<string, int>  $availableIds
     * @param  Closure(string): PotentiallyTranslatedString  $fail
     */
    private function addUnavailableErrors(
        string $attribute,
        Collection $templates,
        Collection $availableIds,
        Closure $fail,
    ): void {
        foreach ($templates as $index => $template) {
            $templateId = $template['id'] ?? null;

            if (!is_string($templateId) || !Str::isUuid($templateId) || $availableIds->has($templateId)) {
                continue;
            }

            if ($this->validator instanceof Validator) {
                $this->validator->errors()->add(
                    "{$attribute}.{$index}.id",
                    __('catalog::validation.service_deliverable_template_unavailable'),
                );

                continue;
            }

            $fail(__('catalog::validation.service_deliverable_template_unavailable'));
        }
    }
}
