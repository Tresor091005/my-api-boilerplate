<?php

declare(strict_types=1);

namespace Lahatre\Library\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Lahatre\Library\Services\LibraryMaintenanceService;

final class VerifyLibraryChecksumsCommand extends Command
{
    protected $signature = 'library:verify-checksums
        {--organization= : Limit verification to one organization UUID}';

    protected $description;

    public function __construct(private readonly LibraryMaintenanceService $maintenanceService)
    {
        parent::__construct();
        $this->description = __('library::console.verify_checksums.description');
    }

    public function handle(): int
    {
        $organizationId = $this->option('organization');

        if ($organizationId !== null && (!is_string($organizationId) || !Str::isUuid($organizationId))) {
            $this->error(__('library::console.verify_checksums.invalid_options'));

            return self::FAILURE;
        }

        $corruptedFileIds = $this->maintenanceService->verifyChecksums($organizationId);

        $this->info(__('library::console.verify_checksums.completed', [
            'corrupted' => count($corruptedFileIds),
        ]));

        foreach ($corruptedFileIds as $fileId) {
            $this->warn(__('library::console.verify_checksums.corrupted_file', ['file_id' => $fileId]));
        }

        return self::SUCCESS;
    }
}
