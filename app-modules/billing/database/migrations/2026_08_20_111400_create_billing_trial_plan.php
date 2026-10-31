<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        $planId = (string) Str::uuid7();

        DB::table('billing_plans')->insert([
            'id'         => $planId,
            'code'       => 'trial',
            'name'       => 'Trial',
            'is_trial'   => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('billing_plan_versions')->insert([
            'id'                      => (string) Str::uuid7(),
            'plan_id'                 => $planId,
            'version'                 => 1,
            'status'                  => 'published',
            'price'                   => 0,
            'currency_code'           => 'USD',
            'billing_interval_months' => 1,
            'published_at'            => now(),
            'created_at'              => now(),
            'updated_at'              => now(),
        ]);
    }

    public function down(): void
    {
        $planIds = DB::table('billing_plans')->where('is_trial', true)->pluck('id');

        DB::table('billing_plan_version_features')
            ->whereIn('plan_version_id', DB::table('billing_plan_versions')->whereIn('plan_id', $planIds)->select('id'))
            ->delete();
        DB::table('billing_plan_versions')->whereIn('plan_id', $planIds)->delete();
        DB::table('billing_plans')->whereIn('id', $planIds)->delete();
    }
};
