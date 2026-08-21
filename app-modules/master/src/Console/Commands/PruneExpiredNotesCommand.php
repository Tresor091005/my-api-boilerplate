<?php

declare(strict_types=1);

namespace Lahatre\Master\Console\Commands;

use Illuminate\Console\Command;
use Lahatre\Master\Services\NoteService;

final class PruneExpiredNotesCommand extends Command
{
    protected $signature = 'master:notes:prune {--retention-days=30}';

    protected $description;

    public function __construct(private readonly NoteService $noteService)
    {
        parent::__construct();
        $this->description = __('master::console.prune_expired_notes.description');
    }

    public function handle(): int
    {
        $retentionDays = (int) $this->option('retention-days');

        if ($retentionDays < 0) {
            $this->error(__('master::console.prune_expired_notes.invalid_retention_days'));

            return self::FAILURE;
        }

        $deletedNotes = $this->noteService->pruneExpiredAcrossOrganizations($retentionDays);

        $this->info(__('master::console.prune_expired_notes.completed', [
            'notes' => $deletedNotes,
        ]));

        return self::SUCCESS;
    }
}
