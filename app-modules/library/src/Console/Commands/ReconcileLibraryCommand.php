<?php

declare(strict_types=1);

namespace Lahatre\Library\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Lahatre\Library\Services\LibraryMaintenanceService;

final class ReconcileLibraryCommand extends Command
{
    protected $signature = 'library:reconcile
        {--organization= : Limit reconciliation to one organization UUID}
        {--delete-orphans : Delete orphan objects after the safety delay}
        {--verify-checksums : Recompute checksums for active files}
        {--grace-hours= : Override the configured orphan safety delay}';

    protected $description;

    public function __construct(private readonly LibraryMaintenanceService $maintenanceService)
    {
        parent::__construct();
        $this->description = __('library::console.reconcile.description');
    }

    public function handle(): int
    {
        $organizationId = $this->option('organization');
        $graceHoursOption = $this->option('grace-hours');
        $graceHours = $graceHoursOption === null
            ? (int) config('library.maintenance.orphan_grace_hours')
            : (int) $graceHoursOption;

        if (($organizationId !== null && (!is_string($organizationId) || !Str::isUuid($organizationId)))
            || $graceHours < 0) {
            $this->error(__('library::console.reconcile.invalid_options'));

            return self::FAILURE;
        }

        $report = $this->maintenanceService->reconcile(
            organizationId: $organizationId,
            deleteOrphans: (bool) $this->option('delete-orphans'),
            verifyChecksums: (bool) $this->option('verify-checksums'),
            orphanGraceHours: $graceHours,
        );

        $this->info(__('library::console.reconcile.completed', [
            'missing'         => count($report->missingFileIds),
            'corrupted'       => count($report->corruptedFileIds),
            'deleted_objects' => $report->deletedObjectsRemoved,
            'orphans'         => $report->orphanObjectsFound,
            'purged_files'    => $report->purgedFilesRemoved,
            'purged_folders'  => $report->purgedFoldersRemoved,
            'pruned'          => $report->orphanObjectsRemoved,
        ]));

        foreach ($report->missingFileIds as $fileId) {
            $this->warn(__('library::console.reconcile.missing_file', ['file_id' => $fileId]));
        }

        foreach ($report->corruptedFileIds as $fileId) {
            $this->warn(__('library::console.reconcile.corrupted_file', ['file_id' => $fileId]));
        }

        return self::SUCCESS;
    }
}
