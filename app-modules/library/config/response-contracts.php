<?php

declare(strict_types=1);

$fileContracts = array_fill_keys([
    'lahatre.library.files.index',
    'lahatre.library.files.store',
    'lahatre.library.files.show',
    'lahatre.library.files.update',
    'lahatre.library.files.restore',
    'lahatre.library.trash.files',
], []);

$folderContracts = array_fill_keys([
    'lahatre.library.folders.index',
    'lahatre.library.folders.store',
    'lahatre.library.folders.show',
    'lahatre.library.folders.update',
], []);

$emptyContracts = array_fill_keys([
    'lahatre.library.files.destroy',
    'lahatre.library.folders.destroy',
    'lahatre.library.files.content',
], []);

return [
    ...$fileContracts,
    ...$folderContracts,
    ...$emptyContracts,
];
