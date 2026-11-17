<?php

declare(strict_types=1);

namespace Lahatre\Sale\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Lahatre\Customer\Models\Customer;
use Lahatre\Sale\Database\Factories\SaleFactory;
use Lahatre\Sale\Enums\SaleMode;
use Lahatre\Sale\Enums\SaleStatus;
use Lahatre\Shared\Traits\SharedTraits;

/**
 * @property string $id
 * @property string $organization_id
 * @property string $number
 * @property SaleStatus $status
 * @property int $version
 * @property string $customer_id
 * @property string $customer_name
 * @property string $currency_code
 * @property SaleMode $mode
 * @property int $required_payment_percent
 * @property CarbonImmutable|null $payment_due_date
 * @property list<string>|null $approval_reasons
 * @property int $lines_base_total
 * @property int $lines_tax_total
 * @property int $lines_total
 * @property int $total
 * @property CarbonImmutable|null $confirmed_at
 * @property CarbonImmutable|null $cancelled_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 *
 * @method static Builder<static>|Sale newModelQuery()
 * @method static Builder<static>|Sale newQuery()
 * @method static Builder<static>|Sale query()
 * @method static SaleFactory factory($count = null, $state = [])
 *
 * @mixin \Eloquent
 */
class Sale extends Model
{
    use SharedTraits;

    /** The share that leaves nothing to pay later; any lower share is a credit. */
    public const int FULL_PAYMENT_PERCENT = 100;

    protected $table = 'sale_sales';

    protected $fillable = [
        'organization_id',
        'number',
        'status',
        'version',
        'customer_id',
        'customer_name',
        'currency_code',
        'mode',
        'required_payment_percent',
        'payment_due_date',
        'approval_reasons',
        'lines_base_total',
        'lines_tax_total',
        'lines_total',
        'total',
        'confirmed_at',
        'cancelled_at',
    ];

    protected $casts = [
        'id'                       => 'string',
        'organization_id'          => 'string',
        'number'                   => 'string',
        'status'                   => SaleStatus::class,
        'version'                  => 'integer',
        'customer_id'              => 'string',
        'customer_name'            => 'string',
        'currency_code'            => 'string',
        'mode'                     => SaleMode::class,
        'required_payment_percent' => 'integer',
        'payment_due_date'         => 'immutable_date',
        'approval_reasons'         => 'array',
        'lines_base_total'         => 'integer',
        'lines_tax_total'          => 'integer',
        'lines_total'              => 'integer',
        'total'                    => 'integer',
        'confirmed_at'             => 'immutable_datetime',
        'cancelled_at'             => 'immutable_datetime',
        'created_at'               => 'immutable_datetime',
        'updated_at'               => 'immutable_datetime',
    ];

    /** @return HasMany<SaleLine, $this> */
    public function lines(): HasMany
    {
        return $this->hasMany(SaleLine::class)
            ->where('sale_lines.organization_id', currentOrganizationId())
            ->orderBy('position');
    }

    /** @return HasMany<SaleTransition, $this> */
    public function transitions(): HasMany
    {
        return $this->hasMany(SaleTransition::class)
            ->where('sale_transitions.organization_id', currentOrganizationId())
            ->orderBy('created_at')
            ->orderBy('id');
    }

    /** The customer, even deleted since: the sale keeps its name. */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class)
            ->withTrashed()
            ->where('customer_customers.organization_id', currentOrganizationId());
    }

    /**
     * The due date that applies now, never stored: the sale's own, else the customer's.
     */
    public function dueDate(?CarbonImmutable $customerDueDate): ?CarbonImmutable
    {
        if ($this->required_payment_percent >= self::FULL_PAYMENT_PERCENT) {
            return null;
        }

        return $this->payment_due_date ?? $customerDueDate;
    }
}
