<?php

declare(strict_types=1);

namespace Lahatre\ResponseSelection\Tests\Support\Example;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $source_depot_id
 * @property string $reference
 * @property string $status
 */
final class ExampleTransfer extends Model
{
    public $timestamps = false;

    protected $table = 'response_selection_example_transfers';

    protected $guarded = [];

    /**
     * @return BelongsTo<ExampleDepot, $this>
     */
    public function sourceDepot(): BelongsTo
    {
        return $this->belongsTo(ExampleDepot::class, 'source_depot_id');
    }

    /**
     * @return HasMany<ExampleTransferLine, $this>
     */
    public function lines(): HasMany
    {
        return $this->hasMany(ExampleTransferLine::class, 'transfer_id')->orderBy('id');
    }
}
