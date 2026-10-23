<?php

declare(strict_types=1);

return [
    'dynamic_factory_call'           => ':action: call ApiResponse factories by name, not dynamically.',
    'action_not_found'               => ':action: the method was not found in :file.',
    'cache_invalid'                  => 'The response declaration cache [:path] must return an array: run response-declarations:cache.',
    'single_factory_call_expected'   => ':action: expected one explicit ApiResponse::item(), list() or cursorPage() call.',
    'resource_class_not_literal'     => ':action: use an explicit Resource::class in ApiResponse:::factory().',
    'status_not_literal'             => ':action: use a literal integer status or declare possibleStatuses as a literal list in ApiResponse:::factory().',
    'status_not_possible'            => ':action: status :status is not in possibleStatuses.',
    'not_a_response_factory'         => 'ApiResponse:::method() is not a response factory.',
    'possible_statuses_not_literal'  => ':action: possibleStatuses must be a non-empty literal list of integers.',
    'status_without_content'         => ':action: status :status cannot represent a successful response with content.',
    'definition_not_built'           => ':path: definition could not be built: :reason',
    'child_resources_not_resolved'   => ':path: child Resources could not be resolved: :reason',
    'include_without_child_resource' => ':path: include declares no child Resource.',
    'invalid_condition'              => ':resource: `when:` of [:member] must be a Closure(Request $request): bool, evaluated once before loading, or a Closure($model, Request $request): bool, evaluated per model.',
    'class_not_concrete'             => ':path: :class must be a concrete :contract implementation.',
];
