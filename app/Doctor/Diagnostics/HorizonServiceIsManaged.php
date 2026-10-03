<?php

declare(strict_types=1);

namespace App\Doctor\Diagnostics;

use Laravel\Doctor\Diagnostic;
use Laravel\Doctor\Results\DiagnosticResult;
use Laravel\Doctor\Results\Message;

class HorizonServiceIsManaged extends Diagnostic
{
    public string $name = 'Horizon service is reachable';

    public string $group = 'docker';

    /**
     * Get the diagnostic's named message definitions.
     *
     * @return array<string, string|Message>
     */
    protected function messages(): array
    {
        return [
            'reachable'   => 'The Docker Horizon service is reachable from the application container.',
            'not-docker'  => 'The Docker Horizon service check was skipped outside a container.',
            'unreachable' => Message::make(
                summary: 'The Docker Horizon service is not reachable from the application container.',
                remediation: 'Start the Horizon service and verify that it shares the application Docker network.',
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

        if (gethostbyname('horizon') === 'horizon') {
            return $this->fail('unreachable');
        }

        return $this->pass('reachable');
    }
}
