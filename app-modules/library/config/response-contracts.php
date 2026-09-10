<?php

declare(strict_types=1);

$folderContracts = array_fill_keys([
    'lahatre.library.folders.index',
    'lahatre.library.folders.store',
    'lahatre.library.folders.show',
    'lahatre.library.folders.update',
], []);

$emptyContracts = array_fill_keys([
    'lahatre.library.folders.destroy',
], []);

return [
    ...$folderContracts,
    ...$emptyContracts,
];
