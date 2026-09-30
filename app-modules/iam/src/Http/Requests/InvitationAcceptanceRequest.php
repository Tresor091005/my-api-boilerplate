<?php

declare(strict_types=1);

namespace Lahatre\Iam\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use Lahatre\Iam\Services\InvitationService;

class InvitationAcceptanceRequest extends FormRequest
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

        $this->merge($prepared);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $accountExists = $this->invitationAccountExists();

        return [
            'first_name' => [Rule::requiredIf($accountExists === false), 'nullable', 'string', 'max:100'],
            'last_name'  => [Rule::requiredIf($accountExists === false), 'nullable', 'string', 'max:100'],
            'email'      => ['required', 'string', 'email', 'max:254'],
            'token'      => ['required', 'string', 'size:64'],
            'password'   => [Rule::requiredIf($accountExists === false), 'nullable', 'string', 'min:8', 'max:255', 'confirmed'],
        ];
    }

    /** @return array<int, callable> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            $accountExists = $this->invitationAccountExists();
            if ($accountExists === null) {
                $validator->errors()->add('token', __('iam::validation.invalid_invitation_token'));

                return;
            }
            if (!$accountExists) {
                return;
            }
            foreach (['first_name', 'last_name', 'password', 'password_confirmation'] as $field) {
                if (array_key_exists($field, $this->all())) {
                    $validator->errors()->add($field, __('iam::validation.user_details_forbidden'));
                }
            }
        }];
    }

    private function invitationAccountExists(): ?bool
    {
        if ($this->accountLookupComplete) {
            return $this->accountExists;
        }

        $email = $this->input('email');
        $token = $this->input('token');
        if (!is_string($email) || !is_string($token) || strlen($token) !== 64) {
            return null;
        }

        $this->accountExists = app(InvitationService::class)->accountExistsForToken($email, $token);
        $this->accountLookupComplete = true;

        return $this->accountExists;
    }
}
