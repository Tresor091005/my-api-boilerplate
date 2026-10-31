<?php

declare(strict_types=1);

namespace App\Doctor\Diagnostics;

use Illuminate\Support\Facades\Schema;
use Lahatre\Billing\Services\FeatureGrantCheck;
use Laravel\Doctor\Diagnostic;
use Laravel\Doctor\Results\DiagnosticResult;
use Laravel\Doctor\Results\Message;

class BillingFeatureGrantsMatchCode extends Diagnostic
{
    public string $name = 'Billing plans grant features the code declares';

    public string $group = 'database';

    /**
     * Get the diagnostic's named message definitions.
     *
     * @return array<string, string|Message>
     */
    protected function messages(): array
    {
        return [
            'not-migrated' => 'The billing tables do not exist yet.',
            'mismatched'   => Message::make(
                summary: '{count} granted feature(s) no longer match the code: {mismatches}',
                remediation: 'Run `php artisan billing:features:check` for the details, then register the feature again or revise the plans that grant it.',
            ),
            'matching' => 'Every feature granted by a plan is declared with the same type and options.',
        ];
    }

    /**
     * Run the diagnostic.
     */
    public function check(): DiagnosticResult
    {
        if (!Schema::hasTable('billing_plan_version_features')) {
            return $this->skip('not-migrated');
        }

        ['mismatches' => $mismatches] = app(FeatureGrantCheck::class)->run();

        return $mismatches === []
            ? $this->pass('matching')
            : $this->fail('mismatched', ['count' => count($mismatches), 'mismatches' => implode(' ', $mismatches)]);
    }
}
