<?php

declare(strict_types=1);

namespace Lahatre\ResponseSelection\Tests\Support\Example;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Lahatre\ResponseSelection\ResolveResponseSelection;

/**
 * Tables, routes and rows of the response selection example: depots, stock transfers leaving a
 * depot, and their lines. The routes run ResolveResponseSelection, as an application's API group does.
 */
final class ExampleSchema
{
    public const string PREFIX = 'response-selection-example';

    public static function createTables(): void
    {
        Schema::create('response_selection_example_depots', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('code');
        });

        Schema::create('response_selection_example_transfers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('source_depot_id');
            $table->string('reference');
            $table->string('status');
        });

        Schema::create('response_selection_example_transfer_lines', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('transfer_id');
            $table->string('sku');
            $table->integer('quantity');
            $table->integer('unit_cost_minor');
        });
    }

    public static function registerRoutes(): void
    {
        Route::middleware(ResolveResponseSelection::class)->prefix(self::PREFIX)->group(function (): void {
            Route::get('transfers', [ExampleTransferController::class, 'index']);
            Route::get('transfers/{transfer}', [ExampleTransferController::class, 'show']);
            Route::get('undeclared-column-transfers', [ExampleTransferController::class, 'undeclaredColumn']);
            Route::get('querying-closure-transfers', [ExampleTransferController::class, 'queryingClosure']);
        });
    }

    /**
     * Seeds $count transfers after the $alreadySeeded ones, alternating two depots and open or
     * closed status, each with two lines.
     */
    public static function seedTransfers(int $count, int $alreadySeeded = 0): void
    {
        $depots = ExampleDepot::query()->orderBy('id')->get();

        if ($depots->isEmpty()) {
            $depots = collect([
                ExampleDepot::query()->create(['name' => 'Main depot', 'code' => 'MAIN']),
                ExampleDepot::query()->create(['name' => 'North depot', 'code' => 'NORTH']),
            ]);
        }

        for ($index = $alreadySeeded; $index < $alreadySeeded + $count; $index++) {
            $transfer = ExampleTransfer::query()->create([
                'source_depot_id' => $depots[$index % 2]->id,
                'reference'       => sprintf('TR-%03d', $index + 1),
                'status'          => $index % 3 === 0 ? 'closed' : 'open',
            ]);

            foreach ([1, 2] as $line) {
                ExampleTransferLine::query()->create([
                    'transfer_id'     => $transfer->id,
                    'sku'             => "SKU-{$index}-{$line}",
                    'quantity'        => $line * 5,
                    'unit_cost_minor' => 1250 * $line,
                ]);
            }
        }
    }
}
