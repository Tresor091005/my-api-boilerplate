<?php

declare(strict_types=1);

namespace Lahatre\ResponseSelection\Tests\Support\Example;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $transfer_id
 * @property string $sku
 * @property int $quantity
 * @property int $unit_cost_minor
 */
final class ExampleTransferLine extends Model
{
    public $timestamps = false;

    protected $table = 'response_selection_example_transfer_lines';

    protected $guarded = [];
}
