<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Relations\Relation;

it('verifies that all Eloquent models are registered in the morph map', function (): void {
    $models = getAllModels();
    $ignoredModels = config('model-integrity.ignored_models', []);
    $morphMap = Relation::morphMap();
    $failures = [];

    foreach ($models as $modelClass) {
        if (in_array($modelClass, $ignoredModels, true)) {
            continue;
        }

        // We check if the class exists in the values of the morph map
        if (!in_array($modelClass, $morphMap, true)) {
            $failures[] = "[{$modelClass}] is not registered in the Eloquent morph map. " .
                "Please run 'php artisan morph-map:cache' or register it manually in a ServiceProvider.";
        }
    }

    if ($failures !== []) {
        $this->fail("Morph Map Registration Failures:\n\n" . implode("\n", $failures));
    }

    expect(true)->toBeTrue();
});
