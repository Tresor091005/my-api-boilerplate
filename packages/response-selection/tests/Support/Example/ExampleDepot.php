<?php

declare(strict_types=1);

namespace Lahatre\ResponseSelection\Tests\Support\Example;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $name
 * @property string $code
 */
final class ExampleDepot extends Model
{
    public $timestamps = false;

    protected $table = 'response_selection_example_depots';

    protected $guarded = [];
}
