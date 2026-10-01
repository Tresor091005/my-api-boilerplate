<?php

declare(strict_types=1);

namespace Lahatre\Iam\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use Lahatre\Iam\Services\OrganizationOnboardingService;
use Lahatre\Shared\Rules\IanaTimezone;

class OrganizationRegistrationRequest extends FormRequest
{
    private bool $accountLookupComplete = false;

    private ?bool $accountExists = null;

    protected function prepareForValidation(): void
    {
        $prepared = [];

        foreach (['first_name', 'last_name'] as $field) {
            if (is_string($this->input($field))) {
                $prepared[$field] = Str::sanitize($this->input($field));
            }
        }
        if (is_string($this->input('email'))) {
            $prepared['email'] = Str::normalize($this->input('email'));
        }

        $organization = $this->input('organization');
        if (is_array($organization)) {
            if (is_string($organization['name'] ?? null)) {
                $organization['name'] = Str::sanitize($organization['name']);
            }
            if (is_string($organization['currency_code'] ?? null)) {
                $organization['currency_code'] = Str::toUpper(Str::sanitize($organization['currency_code']));
            }
            $prepared['organization'] = $organization;
        }

        $this->merge($prepared);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $accountExists = $this->registrationAccountExists();

        return [
            'first_name'                 => [Rule::requiredIf($accountExists === false), 'nullable', 'string', 'max:100'],
            'last_name'                  => [Rule::requiredIf($accountExists === false), 'nullable', 'string', 'max:100'],
            'email'                      => ['required', 'string', 'email', 'max:254'],
            'token'                      => ['required', 'string', 'size:64'],
            'organization'               => ['required', 'array:name,currency_code,timezone'],
            'organization.name'          => ['required', 'string', 'max:100'],
            'organization.currency_code' => ['required', 'string', 'size:3', 'alpha', Rule::exists('master_currencies', 'code')->whereNull('deleted_at')],
            'organization.timezone'      => ['required', 'string', new IanaTimezone],
        ];
    }

    /** @return array<int, callable> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            $accountExists = $this->registrationAccountExists();
            if ($accountExists === null) {
                $validator->errors()->add('token', __('iam::validation.invalid_registration_token'));

                return;
            }
            if (!$accountExists) {
                return;
            }
            foreach (['first_name', 'last_name'] as $field) {
                if (array_key_exists($field, $this->all())) {
                    $validator->errors()->add($field, __('iam::validation.user_details_forbidden'));
                }
            }
        }];
    }

    private function registrationAccountExists(): ?bool
    {
        if ($this->accountLookupComplete) {
            return $this->accountExists;
        }

        $email = $this->input('email');
        $token = $this->input('token');
        if (!is_string($email) || !is_string($token) || strlen($token) !== 64) {
            return null;
        }

        $this->accountExists = app(OrganizationOnboardingService::class)->accountExistsForToken($email, $token);
        $this->accountLookupComplete = true;

        return $this->accountExists;
    }
}
