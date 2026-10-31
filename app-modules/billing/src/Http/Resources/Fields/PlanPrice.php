<?php

declare(strict_types=1);

namespace Lahatre\Billing\Http\Resources\Fields;

use Illuminate\Database\Eloquent\Model;
use Lahatre\Master\Contracts\MasterInterface;

final class PlanPrice
{
    /** The minor price of a plan version as a decimal of its currency, such as "4.30". */
    public static function decimal(Model $version): string
    {
        return self::amount((int) $version->getAttribute('price'), (string) $version->getAttribute('currency_code'));
    }

    public static function amount(int $minor, string $currencyCode): string
    {
        return app(MasterInterface::class)->fromMinor((string) $minor, $currencyCode);
    }
}
