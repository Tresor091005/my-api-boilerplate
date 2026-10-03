<?php

declare(strict_types=1);

namespace App\Doctor\Diagnostics;

use Laravel\Doctor\Diagnostic;
use Laravel\Doctor\Results\DiagnosticResult;
use Laravel\Doctor\Results\Message;

class ReverbServiceIsManaged extends Diagnostic
{
    public string $name = 'Reverb service is reachable';

    public string $group = 'docker';

    /**
     * Get the diagnostic's named message definitions.
     *
     * @return array<string, string|Message>
     */
    protected function messages(): array
    {
        return [
            'reachable'   => 'The Docker Reverb service is reachable from the application container.',
            'not-docker'  => 'The Docker Reverb service check was skipped outside a container.',
            'unreachable' => Message::make(
                summary: 'The Docker Reverb service is not reachable from the application container.',
                remediation: 'Start the Reverb service and verify that it shares the application Docker network.',
            ),
        ];
    }

    /**
     * Run the diagnostic.
     */
    public function check(): DiagnosticResult
    {
        if (!is_file('/.dockerenv')) {
            return $this->skip('not-docker');
        }

        if (gethostbyname('reverb') === 'reverb') {
            return $this->fail('unreachable');
        }

        return $this->pass('reachable');
    }
}
