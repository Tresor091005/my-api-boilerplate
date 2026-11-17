<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Lahatre\Catalog\Models\CatalogItem;
use Lahatre\Catalog\Models\CatalogItemPrice;
use Lahatre\Catalog\Models\CatalogItemPriceTax;
use Lahatre\Catalog\Models\PriceLevel;
use Lahatre\Catalog\Models\Product;
use Lahatre\Catalog\Models\ProductVariant;
use Lahatre\Customer\Contracts\CustomerInterface;
use Lahatre\Customer\Models\Customer;
use Lahatre\Iam\Models\OrganizationMember;
use Lahatre\Master\Contracts\MasterInterface;
use Lahatre\Organization\Models\Organization;
use Lahatre\Sale\Enums\SaleStatus;
use Lahatre\Sale\Models\Sale;
use Lahatre\Sale\Models\SaleLine;
use Lahatre\Sale\Models\SaleTransition;
use Lahatre\Tax\Data\TaxData;
use Lahatre\Tax\Services\TaxService;
use Lahatre\Tax\Tests\Concerns\InteractsWithTaxTenant;

uses(DatabaseTransactions::class, InteractsWithTaxTenant::class);

const SALE_WORKFLOW_PERMISSIONS = [
    'sale_sale.list', 'sale_sale.retrieve', 'sale_sale.create', 'sale_sale.update',
    'sale_sale.approve', 'sale_sale.confirm', 'sale_sale.cancel',
    'customer_customer.retrieve', 'customer_customer.update', 'customer_customer.delete',
];

beforeEach(function (): void {
    $this->withoutMiddleware(ThrottleRequests::class);
    $this->setUpTaxTenant();
    $this->memberId = workflowActAs(SALE_WORKFLOW_PERMISSIONS);

    $this->vat = $this->createPercentageTax('18', ['code' => 'VAT', 'kind' => 'vat']);
    $this->levy = $this->createPercentageTax('5', ['code' => 'LEVY']);
    $this->profile = $this->createProfile([
        ['tax_id' => $this->vat->id, 'treatment' => 'standard'],
        ['tax_id' => $this->levy->id, 'treatment' => 'standard'],
    ]);
    $this->retail = PriceLevel::factory()->create(['organization_id' => $this->organization->id, 'name' => 'retail']);
    $this->customer = Customer::factory()->create(['organization_id' => $this->organization->id, 'name' => 'Ada']);
    // 1180 per kg, its 18 % VAT included; the 5 % levy is added on top
    $this->rice = workflowVariant('Rice', $this->profile->id);
    $this->ricePrice = workflowPrice($this->rice, 1180, [$this->vat->id]);
});

/** @param list<string> $permissions */
function workflowActAs(array $permissions): string
{
    $organizationId = currentOrganizationId();
    $known = OrganizationMember::query()->where('organization_id', $organizationId)->pluck('id');
    (fn (): mixed => $this->authenticateTaxMember($permissions))->call(test());
    app('auth')->forgetGuards();

    return (string) OrganizationMember::query()
        ->where('organization_id', $organizationId)
        ->whereNotIn('id', $known)
        ->value('id');
}

/** @return list<string> */
function workflowPermissionsWithout(string $permission): array
{
    return array_values(array_diff(SALE_WORKFLOW_PERMISSIONS, [$permission]));
}

function workflowVariant(string $name, ?string $taxProfileId = null): ProductVariant
{
    $organizationId = currentOrganizationId();
    $product = Product::factory()->create(['organization_id' => $organizationId, 'name' => $name]);

    return createCatalogProductVariant(
        ['organization_id' => $organizationId, 'product_id' => $product->id],
        [
            'organization_id' => $organizationId,
            'unit_group_id'   => app(MasterInterface::class)->unit('kg')->group_id,
            'tax_profile_id'  => $taxProfileId,
        ],
    );
}

/** @param list<string> $includedTaxIds */
function workflowPrice(ProductVariant $item, int $amount, array $includedTaxIds = []): CatalogItemPrice
{
    $price = CatalogItemPrice::factory()->create([
        'organization_id' => currentOrganizationId(),
        'catalog_item_id' => $item->id,
        'price_level_id'  => test()->retail->id,
        'unit_id'         => app(MasterInterface::class)->unit('kg')->id,
        'amount'          => $amount,
    ]);

    foreach ($includedTaxIds as $taxId) {
        CatalogItemPriceTax::factory()->create(['catalog_item_price_id' => $price->id, 'tax_id' => $taxId]);
    }

    return $price;
}

function workflowLabel(ProductVariant $item): string
{
    return CatalogItem::query()->whereKey($item->id)->value('sku').' (kg)';
}

function workflowDueDate(): string
{
    return now()->addDays(30)->toDateString();
}

/** @return array<string, mixed> */
function workflowCredit(array $overrides = []): array
{
    return ['required_payment_percent' => 50, 'payment_due_date' => workflowDueDate(), ...$overrides];
}

/** @return array<string, mixed> */
function workflowDraftPayload(array $overrides = []): array
{
    $payload = [
        'customer_id' => test()->customer->id,
        'lines'       => [['catalog_item_id' => test()->rice->id, 'unit_code' => 'kg', 'quantity' => 2]],
        ...$overrides,
    ];

    if (is_array($payload['lines'] ?? null)) {
        $payload['lines'] = pricedSaleLines($payload['lines'], test()->retail->id);
    }

    return $payload;
}

function workflowDraft(array $overrides = []): string
{
    return (string) test()->postJson('/v1/sale/sales?response=resource', workflowDraftPayload($overrides))
        ->assertCreated()
        ->json('data.id');
}

function saleGesture(string $saleId, string $gesture, array $payload = [], string $query = '?response=resource'): TestResponse
{
    return test()->postJson("/v1/sale/sales/{$saleId}/{$gesture}{$query}", $payload);
}

/** Brings a new sale to the status through the gestures; a lowered share is validated by a member who may not approve. */
function workflowSaleIn(SaleStatus $status): string
{
    $lowered = in_array($status, [SaleStatus::WaitingApproval, SaleStatus::Rejected], true);
    $saleId = workflowDraft($lowered ? workflowCredit() : []);

    if ($status === SaleStatus::Draft) {
        return $saleId;
    }

    if ($lowered) {
        workflowActAs(workflowPermissionsWithout('sale_sale.approve'));
        saleGesture($saleId, 'validate', ['version' => 1])->assertOk()->assertJsonPath('data.status', 'waiting_approval');
        test()->memberId = workflowActAs(SALE_WORKFLOW_PERMISSIONS);
    } elseif ($status !== SaleStatus::Abandoned) {
        saleGesture($saleId, 'validate', ['version' => 1])->assertOk()->assertJsonPath('data.status', 'approved');
    }

    match ($status) {
        SaleStatus::Rejected   => saleGesture($saleId, 'reject', ['reason' => 'Too risky'])->assertOk(),
        SaleStatus::Active     => saleGesture($saleId, 'confirm')->assertOk(),
        SaleStatus::Cancelling => [saleGesture($saleId, 'confirm')->assertOk(), saleGesture($saleId, 'cancel')->assertOk()],
        SaleStatus::Abandoned  => saleGesture($saleId, 'abandon')->assertOk(),
        default                => null,
    };

    expect(Sale::query()->whereKey($saleId)->value('status'))->toBe($status);

    return $saleId;
}

function confirmationBlocked(string $saleId, string $reason): string
{
    return __('sale::exceptions.confirmation_blocked', ['number' => workflowSale($saleId)->number, 'reason' => $reason]);
}

/** Ties the customer to a new `wholesale` level pricing rice per kg. */
function workflowTieCustomerToWholesale(string $customerId, int $amount): void
{
    $wholesale = PriceLevel::factory()->create(['organization_id' => currentOrganizationId(), 'name' => 'wholesale']);
    CatalogItemPrice::factory()->create([
        'organization_id' => currentOrganizationId(),
        'catalog_item_id' => test()->rice->id,
        'price_level_id'  => $wholesale->id,
        'unit_id'         => app(MasterInterface::class)->unit('kg')->id,
        'amount'          => $amount,
    ]);
    Customer::query()->whereKey($customerId)->update(['price_level_id' => $wholesale->id]);
}

function workflowSale(string $saleId): Sale
{
    return Sale::query()->whereKey($saleId)->firstOrFail();
}

function lastSaleTransition(string $saleId): SaleTransition
{
    return SaleTransition::query()->where('sale_id', $saleId)->orderByDesc('created_at')->orderByDesc('id')->firstOrFail();
}

describe('transitions', function (): void {
    it('moves a sale along every transition of the graph', function (SaleStatus $from, string $gesture, array $payload, SaleStatus $to): void {
        $saleId = workflowSaleIn($from);

        saleGesture($saleId, $gesture, $payload)->assertOk()->assertJsonPath('data.status', $to->value);

        $transition = lastSaleTransition($saleId);
        expect($transition->event->value)->toBe($gesture)
            ->and($transition->from_status)->toBe($from)
            ->and($transition->to_status)->toBe($to)
            ->and($transition->member_id)->toBe($this->memberId);
    })->with([
        'validate a draft'         => [SaleStatus::Draft, 'validate', ['version' => 1], SaleStatus::Approved],
        'abandon a draft'          => [SaleStatus::Draft, 'abandon', [], SaleStatus::Abandoned],
        'approve'                  => [SaleStatus::WaitingApproval, 'approve', [], SaleStatus::Approved],
        'reject'                   => [SaleStatus::WaitingApproval, 'reject', ['reason' => 'No'], SaleStatus::Rejected],
        'reopen a pending sale'    => [SaleStatus::WaitingApproval, 'reopen', [], SaleStatus::Draft],
        'abandon a waiting sale'   => [SaleStatus::WaitingApproval, 'abandon', [], SaleStatus::Abandoned],
        'reopen a rejected sale'   => [SaleStatus::Rejected, 'reopen', [], SaleStatus::Draft],
        'abandon a rejected sale'  => [SaleStatus::Rejected, 'abandon', [], SaleStatus::Abandoned],
        'reopen an approved sale'  => [SaleStatus::Approved, 'reopen', [], SaleStatus::Draft],
        'confirm an approved sale' => [SaleStatus::Approved, 'confirm', [], SaleStatus::Active],
        'abandon an approved sale' => [SaleStatus::Approved, 'abandon', [], SaleStatus::Abandoned],
        'cancel an active sale'    => [SaleStatus::Active, 'cancel', [], SaleStatus::Cancelling],
        'resume a cancelling sale' => [SaleStatus::Cancelling, 'resume', [], SaleStatus::Active],
    ]);

    it('answers 204 by default', function (): void {
        $saleId = workflowDraft();

        saleGesture($saleId, 'validate', ['version' => 1], query: '')->assertNoContent();
    });

    it('refuses a gesture the status does not allow, naming the status and what can be done', function (): void {
        $saleId = workflowDraft();
        $number = workflowSale($saleId)->number;

        saleGesture($saleId, 'approve')
            ->assertUnprocessable()
            ->assertJsonPath('message', __('sale::exceptions.gesture_not_allowed', [
                'number' => $number, 'status' => 'draft', 'event' => 'approve', 'events' => 'validate, abandon',
            ]));

        expect(SaleTransition::query()->where('sale_id', $saleId)->count())->toBe(1);
    });

    it('allows no gesture on an abandoned sale', function (SaleStatus $status, string $gesture, array $payload): void {
        $saleId = workflowSaleIn($status);
        $number = workflowSale($saleId)->number;

        saleGesture($saleId, $gesture, $payload)
            ->assertUnprocessable()
            ->assertJsonPath('message', __('sale::exceptions.gesture_not_allowed_final', [
                'number' => $number, 'status' => $status->value, 'event' => $gesture,
            ]));
    })->with([SaleStatus::Abandoned])->with([
        'validate' => ['validate', ['version' => 1]],
        'reopen'   => ['reopen', []],
        'abandon'  => ['abandon', []],
        'approve'  => ['approve', []],
        'reject'   => ['reject', ['reason' => 'No']],
        'confirm'  => ['confirm', []],
        'cancel'   => ['cancel', []],
        'resume'   => ['resume', []],
    ]);

    it('refuses to confirm a draft, saying to validate it first', function (): void {
        $saleId = workflowDraft();
        $number = workflowSale($saleId)->number;

        saleGesture($saleId, 'confirm')
            ->assertUnprocessable()
            ->assertJsonPath('message', __('sale::exceptions.gesture_not_allowed', [
                'number' => $number, 'status' => 'draft', 'event' => 'confirm', 'events' => 'validate, abandon',
            ]));

        expect(workflowSale($saleId)->status)->toBe(SaleStatus::Draft)
            ->and(SaleTransition::query()->where('sale_id', $saleId)->count())->toBe(1);
    });

    it('allows a cancelling sale to be resumed only', function (string $gesture, array $payload): void {
        $saleId = workflowSaleIn(SaleStatus::Cancelling);
        $number = workflowSale($saleId)->number;

        saleGesture($saleId, $gesture, $payload)
            ->assertUnprocessable()
            ->assertJsonPath('message', __('sale::exceptions.gesture_not_allowed', [
                'number' => $number, 'status' => 'cancelling', 'event' => $gesture, 'events' => 'resume',
            ]));
    })->with([
        'validate' => ['validate', ['version' => 1]],
        'reopen'   => ['reopen', []],
        'abandon'  => ['abandon', []],
        'confirm'  => ['confirm', []],
        'cancel'   => ['cancel', []],
    ]);
});

describe('approval', function (): void {
    it('sends a lowered share to waiting_approval when the one validating may not approve', function (): void {
        $saleId = workflowDraft(workflowCredit());
        workflowActAs(workflowPermissionsWithout('sale_sale.approve'));

        saleGesture($saleId, 'validate', ['version' => 1])
            ->assertOk()
            ->assertJsonPath('data.status', 'waiting_approval')
            ->assertJsonPath('data.approval_reasons', ['credit_granted'])
            ->assertJsonPath('data.due_date', workflowDueDate());
    });

    it('approves a lowered share at once when the one validating may approve', function (): void {
        $saleId = workflowDraft(workflowCredit());

        saleGesture($saleId, 'validate', ['version' => 1])
            ->assertOk()
            ->assertJsonPath('data.status', 'approved')
            ->assertJsonPath('data.approval_reasons', ['credit_granted']);
    });

    it('keeps the reasons when the sale is approved', function (): void {
        $saleId = workflowSaleIn(SaleStatus::WaitingApproval);

        saleGesture($saleId, 'approve')
            ->assertOk()
            ->assertJsonPath('data.status', 'approved')
            ->assertJsonPath('data.approval_reasons', ['credit_granted'])
            ->assertJsonPath('data.due_date', workflowDueDate());
    });

    it('needs a reason to reject, and keeps it on the transition', function (): void {
        $saleId = workflowSaleIn(SaleStatus::WaitingApproval);

        saleGesture($saleId, 'reject')->assertUnprocessable()->assertJsonValidationErrors('reason');
        saleGesture($saleId, 'reject', ['reason' => str_repeat('a', 501)])->assertUnprocessable()->assertJsonValidationErrors('reason');

        saleGesture($saleId, 'reject', ['reason' => 'Deposit too low'])->assertOk()->assertJsonPath('data.status', 'rejected');
        expect(lastSaleTransition($saleId)->reason)->toBe('Deposit too low');
    });

    it('loses the approval when the sale goes back to draft', function (SaleStatus $from, string $gesture): void {
        $saleId = workflowSaleIn($from);

        saleGesture($saleId, $gesture)
            ->assertOk()
            ->assertJsonPath('data.status', 'draft')
            ->assertJsonPath('data.approval_reasons', null)
            ->assertJsonPath('data.due_date', workflowDueDate());
    })->with([
        'reopen a pending sale'  => [SaleStatus::WaitingApproval, 'reopen'],
        'reopen a rejected sale' => [SaleStatus::Rejected, 'reopen'],
    ]);

    it('loses the approval when an approved sale is reopened', function (): void {
        $saleId = workflowDraft(workflowCredit());
        saleGesture($saleId, 'validate', ['version' => 1])->assertOk()->assertJsonPath('data.status', 'approved');

        saleGesture($saleId, 'reopen')
            ->assertOk()
            ->assertJsonPath('data.status', 'draft')
            ->assertJsonPath('data.approval_reasons', null)
            ->assertJsonPath('data.due_date', workflowDueDate());
    });

    it('needs no approval when everything is paid at once', function (): void {
        $saleId = workflowDraft(['required_payment_percent' => 100]);
        workflowActAs(workflowPermissionsWithout('sale_sale.approve'));

        saleGesture($saleId, 'validate', ['version' => 1])
            ->assertOk()
            ->assertJsonPath('data.status', 'approved')
            ->assertJsonPath('data.approval_reasons', null);
    });

    it('needs an approval for any share below 100', function (int $percent): void {
        $saleId = workflowDraft(workflowCredit(['required_payment_percent' => $percent]));
        workflowActAs(workflowPermissionsWithout('sale_sale.approve'));

        saleGesture($saleId, 'validate', ['version' => 1])
            ->assertOk()
            ->assertJsonPath('data.status', 'waiting_approval')
            ->assertJsonPath('data.approval_reasons', ['credit_granted']);
    })->with([0, 50, 99]);

    it('refuses at validation a credit that reached the walk-in customer, and says how to get out', function (): void {
        $walkIn = app(CustomerInterface::class)->walkInCustomer($this->organization->id);
        $saleId = workflowDraft();
        Sale::query()->whereKey($saleId)->update(['customer_id' => $walkIn->id, 'required_payment_percent' => 50, 'payment_due_date' => workflowDueDate()]);
        workflowActAs(workflowPermissionsWithout('sale_sale.approve'));

        saleGesture($saleId, 'validate', ['version' => 1])
            ->assertUnprocessable()
            ->assertJsonPath('message', __('sale::exceptions.credit_to_walk_in_customer'));

        expect(workflowSale($saleId)->status)->toBe(SaleStatus::Draft);
    });
});

describe('customer who may no longer buy', function (): void {
    it('refuses to validate or approve once the customer was deactivated', function (): void {
        $draftId = workflowDraft();
        $waitingId = workflowSaleIn(SaleStatus::WaitingApproval);
        Customer::query()->whereKey($this->customer->id)->update(['is_active' => false]);
        $refusal = __('sale::exceptions.customer_inactive', ['customer' => 'Ada']);

        saleGesture($draftId, 'validate', ['version' => 1])->assertUnprocessable()->assertJsonPath('message', $refusal);
        saleGesture($waitingId, 'approve')->assertUnprocessable()->assertJsonPath('message', $refusal);

        expect(workflowSale($draftId)->status)->toBe(SaleStatus::Draft)
            ->and(workflowSale($waitingId)->status)->toBe(SaleStatus::WaitingApproval);
    });

    it('refuses to validate once the customer of the draft was deleted', function (): void {
        $saleId = workflowDraft();
        $this->deleteJson("/v1/customer/customers/{$this->customer->id}")->assertNoContent();

        saleGesture($saleId, 'validate', ['version' => 1])
            ->assertUnprocessable()
            ->assertJsonPath('message', __('sale::exceptions.customer_unavailable'));
    });

    it('still lets a confirmed sale of a deactivated customer be cancelled and resumed', function (): void {
        $saleId = workflowSaleIn(SaleStatus::Active);
        Customer::query()->whereKey($this->customer->id)->update(['is_active' => false]);

        saleGesture($saleId, 'cancel')->assertOk()->assertJsonPath('data.status', 'cancelling');
        saleGesture($saleId, 'resume')->assertOk()->assertJsonPath('data.status', 'active');
    });

    it('still lets a sale waiting for approval of a deactivated customer be rejected or abandoned', function (string $gesture, array $payload, string $status): void {
        $saleId = workflowSaleIn(SaleStatus::WaitingApproval);
        Customer::query()->whereKey($this->customer->id)->update(['is_active' => false]);

        saleGesture($saleId, $gesture, $payload)->assertOk()->assertJsonPath('data.status', $status);
    })->with([
        'reject'  => ['reject', ['reason' => 'Customer closed'], 'rejected'],
        'abandon' => ['abandon', [], 'abandoned'],
    ]);
});

describe('due date', function (): void {
    it('shows the date typed on the sale, else the customer\'s', function (?string $saleDate, ?string $customerDate, string $applied): void {
        $customer = Customer::factory()->create(['organization_id' => $this->organization->id, 'payment_due_date' => $customerDate]);
        $saleId = workflowDraft(workflowCredit(['customer_id' => $customer->id, 'payment_due_date' => $saleDate]));

        saleGesture($saleId, 'validate', ['version' => 1])
            ->assertOk()
            ->assertJsonPath('data.payment_due_date', $saleDate)
            ->assertJsonPath('data.due_date', $applied);
    })->with([
        'typed on the sale, earlier than the customer\'s' => [now()->addDays(10)->toDateString(), now()->addDays(40)->toDateString(), now()->addDays(10)->toDateString()],
        'typed on the sale, later than the customer\'s'   => [now()->addDays(90)->toDateString(), now()->addDays(40)->toDateString(), now()->addDays(90)->toDateString()],
        'typed on the sale, the customer has none'        => [now()->addDays(10)->toDateString(), null, now()->addDays(10)->toDateString()],
        'of the customer'                                 => [null, now()->addDays(40)->toDateString(), now()->addDays(40)->toDateString()],
        'of the customer, today'                          => [null, now()->toDateString(), now()->toDateString()],
    ]);

    it('refuses a credit without any due date, and says how to get out', function (?string $customerDate): void {
        $customer = Customer::factory()->create(['organization_id' => $this->organization->id, 'payment_due_date' => $customerDate]);
        $saleId = workflowDraft(workflowCredit(['customer_id' => $customer->id, 'payment_due_date' => null]));
        $number = workflowSale($saleId)->number;

        saleGesture($saleId, 'validate', ['version' => 1])
            ->assertUnprocessable()
            ->assertJsonPath('message', __('sale::exceptions.payment_due_date_missing', ['number' => $number]));

        expect(workflowSale($saleId)->status)->toBe(SaleStatus::Draft)
            ->and(__('sale::exceptions.payment_due_date_missing'))->toContain('Type a due date on the sale', 'set one on the customer', 'require 100 %');
    })->with([
        'the customer has none'           => [null],
        'the customer\'s date has passed' => [now()->subDay()->toDateString()],
    ]);

    it('refuses a date typed on the draft that has passed since, and says to change it', function (): void {
        $saleId = workflowDraft(workflowCredit());
        $number = workflowSale($saleId)->number;
        $this->travel(31)->days();

        saleGesture($saleId, 'validate', ['version' => 1])
            ->assertUnprocessable()
            ->assertJsonPath('message', __('sale::exceptions.payment_due_date_passed', ['number' => $number, 'date' => workflowSale($saleId)->payment_due_date->toDateString()]));

        expect(workflowSale($saleId)->status)->toBe(SaleStatus::Draft)
            ->and(__('sale::exceptions.payment_due_date_passed'))->toContain('Type a due date from today on the draft');
    });

    it('shows no due date when everything is paid at once', function (): void {
        $customer = Customer::factory()->create(['organization_id' => $this->organization->id, 'payment_due_date' => workflowDueDate()]);
        $saleId = workflowDraft(['customer_id' => $customer->id, 'payment_due_date' => workflowDueDate()]);

        saleGesture($saleId, 'validate', ['version' => 1])
            ->assertOk()
            ->assertJsonPath('data.payment_due_date', workflowDueDate())
            ->assertJsonPath('data.due_date', null);
    });

    it('refuses to confirm an approved credit whose due date has passed, saying to reopen it', function (): void {
        $saleId = workflowDraft(workflowCredit());
        saleGesture($saleId, 'validate', ['version' => 1])->assertOk()->assertJsonPath('data.status', 'approved');
        $this->travel(31)->days();

        saleGesture($saleId, 'confirm')
            ->assertUnprocessable()
            ->assertJsonPath('message', confirmationBlocked($saleId, __('sale::exceptions.payment_due_date_passed', [
                'number' => workflowSale($saleId)->number, 'date' => workflowSale($saleId)->payment_due_date->toDateString(),
            ])));

        expect(workflowSale($saleId)->status)->toBe(SaleStatus::Approved);
    });

    it('confirms an approved credit on its due date', function (): void {
        $saleId = workflowDraft(workflowCredit());
        saleGesture($saleId, 'validate', ['version' => 1])->assertOk();
        $this->travel(30)->days();

        saleGesture($saleId, 'confirm')->assertOk()->assertJsonPath('data.status', 'active');
    });
});

describe('version', function (): void {
    it('refuses to validate an old version, and requires one', function (): void {
        $saleId = workflowDraft();
        $this->putJson("/v1/sale/sales/{$saleId}", workflowDraftPayload(['version' => 1]))->assertNoContent();
        $number = workflowSale($saleId)->number;

        saleGesture($saleId, 'validate', ['version' => 1])
            ->assertUnprocessable()
            ->assertJsonPath('message', __('sale::exceptions.version_changed', ['number' => $number]));
        saleGesture($saleId, 'validate')->assertUnprocessable()->assertJsonValidationErrors('version');
        saleGesture($saleId, 'validate', ['version' => 2])->assertOk();
    });
});

describe('confirmation', function (): void {
    it('confirms an approved sale as it was approved, keeping its copies', function (): void {
        $customer = Customer::factory()->create(['organization_id' => $this->organization->id, 'name' => 'Ada']);
        $saleId = workflowDraft(['customer_id' => $customer->id]);
        saleGesture($saleId, 'validate', ['version' => 1])->assertOk();
        $customer->update(['name' => 'Ada Lovelace']);
        Product::query()->whereKey($this->rice->product_id)->update(['name' => 'Rice premium']);
        $this->retail->update(['name' => 'shop']);

        saleGesture($saleId, 'confirm', query: '?response=resource&include=lines')
            ->assertOk()
            ->assertJsonPath('data.status', 'active')
            ->assertJsonPath('data.customer_name', 'Ada')
            ->assertJsonPath('data.lines.0.price_level_name', 'retail')
            ->assertJsonPath('data.lines.0.item_name', 'Rice')
            ->assertJsonPath('data.lines.0.total_amount', '2460')
            ->assertJsonPath('data.total', '2460');

        expect(workflowSale($saleId)->confirmed_at)->not->toBeNull()
            ->and(SaleLine::query()->where('sale_id', $saleId)->count())->toBe(1);
    });

    it('confirms when a tax is only renamed', function (): void {
        $saleId = workflowDraft();
        saleGesture($saleId, 'validate', ['version' => 1])->assertOk();
        app(TaxService::class)->update($this->levy, TaxData::fromArray([
            'code'             => 'LEVY',
            'name'             => 'Renamed levy',
            'calculation_type' => 'percentage',
            'brackets'         => [['base_min' => '0', 'base_max' => null, 'value' => '5']],
        ]));

        saleGesture($saleId, 'confirm')->assertOk()->assertJsonPath('data.status', 'active');
    });

    it('refuses to confirm when an amount changed, naming the item and changing nothing', function (Closure $change): void {
        $water = workflowVariant('Water');
        workflowPrice($water, 500);
        $saleId = workflowDraft(['lines' => [
            ['catalog_item_id' => $this->rice->id, 'unit_code' => 'kg', 'quantity' => 2],
            ['catalog_item_id' => $water->id, 'unit_code' => 'kg', 'quantity' => 1],
        ]]);
        saleGesture($saleId, 'validate', ['version' => 1])->assertOk();
        $linesBefore = SaleLine::query()->where('sale_id', $saleId)->orderBy('position')->get()->toArray();
        $number = workflowSale($saleId)->number;
        $change->call($this);

        saleGesture($saleId, 'confirm')
            ->assertUnprocessable()
            ->assertJsonPath('message', __('sale::exceptions.amounts_changed', ['number' => $number, 'items' => workflowLabel($this->rice)]));

        expect(workflowSale($saleId)->status)->toBe(SaleStatus::Approved)
            ->and(workflowSale($saleId)->confirmed_at)->toBeNull()
            ->and(SaleLine::query()->where('sale_id', $saleId)->orderBy('position')->get()->toArray())->toBe($linesBefore)
            ->and(SaleTransition::query()->where('sale_id', $saleId)->count())->toBe(2);
    })->with([
        'a tax rate changed' => [function (): void {
            app(TaxService::class)->update($this->levy, TaxData::fromArray([
                'code'             => 'LEVY',
                'name'             => 'Tax',
                'calculation_type' => 'percentage',
                'brackets'         => [['base_min' => '0', 'base_max' => null, 'value' => '10']],
            ]));
        }],
    ]);

    it('refuses to confirm when a price was removed, naming the item', function (): void {
        $saleId = workflowSaleIn(SaleStatus::Approved);
        $this->ricePrice->delete();

        saleGesture($saleId, 'confirm')
            ->assertUnprocessable()
            ->assertJsonPath('message', confirmationBlocked($saleId, __('sale::exceptions.prices_missing', ['items' => __('sale::exceptions.priced_line', ['line' => workflowLabel($this->rice), 'level' => 'retail'])])));

        expect(workflowSale($saleId)->status)->toBe(SaleStatus::Approved);
    });

    it('asks before confirming when a catalog price moved since the approval, changing nothing', function (Closure $change, string $expectedChange): void {
        $water = workflowVariant('Water');
        workflowPrice($water, 500);
        $saleId = workflowDraft(['lines' => [
            ['catalog_item_id' => $this->rice->id, 'unit_code' => 'kg', 'quantity' => 2],
            ['catalog_item_id' => $water->id, 'unit_code' => 'kg', 'quantity' => 1],
        ]]);
        saleGesture($saleId, 'validate', ['version' => 1])->assertOk();
        $linesBefore = SaleLine::query()->where('sale_id', $saleId)->orderBy('position')->get()->toArray();
        $change->call($this, workflowSale($saleId));

        saleGesture($saleId, 'confirm')
            ->assertUnprocessable()
            ->assertJsonPath('message', __('sale::exceptions.prices_changed_since_approval', [
                'number' => workflowSale($saleId)->number,
                'items'  => __($expectedChange, [
                    'line'     => __('sale::exceptions.priced_line', ['line' => workflowLabel($this->rice), 'level' => 'retail']),
                    'approved' => '1180',
                    'current'  => $expectedChange === 'sale::exceptions.price_change' ? '1200' : '900',
                    'level'    => 'wholesale',
                ]),
            ]))
            ->assertJsonPath('errors.context.price_changes.0.approved_unit_amount', '1180');

        expect(workflowSale($saleId)->status)->toBe(SaleStatus::Approved)
            ->and(SaleLine::query()->where('sale_id', $saleId)->orderBy('position')->get()->toArray())->toBe($linesBefore)
            ->and(SaleTransition::query()->where('sale_id', $saleId)->count())->toBe(2);
    })->with([
        'at the level of the line' => [function (): void {
            $this->ricePrice->update(['amount' => 1200]);
        }, 'sale::exceptions.price_change'],
        'at a level the customer was since tied to' => [function (Sale $sale): void {
            workflowTieCustomerToWholesale($sale->customer_id, 900);
        }, 'sale::exceptions.price_change_with_level'],
    ]);

    it('confirms at the approved prices when asked to keep them', function (Closure $change): void {
        $saleId = workflowSaleIn(SaleStatus::Approved);
        $change->call($this, workflowSale($saleId));

        saleGesture($saleId, 'confirm', ['keep_approved_prices' => true], '?response=resource&include=lines')
            ->assertOk()
            ->assertJsonPath('data.status', 'active')
            ->assertJsonPath('data.lines.0.price_level_name', 'retail')
            ->assertJsonPath('data.lines.0.unit_amount', '1180')
            ->assertJsonPath('data.total', '2460');
    })->with([
        'a price moved at the level of the line' => [function (): void {
            $this->ricePrice->update(['amount' => 1200]);
        }],
        'the customer was since tied to another level' => [function (Sale $sale): void {
            workflowTieCustomerToWholesale($sale->customer_id, 900);
        }],
    ]);

    it('confirms with keep_approved_prices when nothing moved, and false behaves as left out', function (): void {
        $saleId = workflowSaleIn(SaleStatus::Approved);
        $this->ricePrice->update(['amount' => 1200]);
        saleGesture($saleId, 'confirm', ['keep_approved_prices' => false])->assertUnprocessable();

        $this->ricePrice->update(['amount' => 1180]);
        saleGesture($saleId, 'confirm', ['keep_approved_prices' => true])->assertOk()->assertJsonPath('data.status', 'active');
    });

    it('still refuses a tax change when the approved prices are kept', function (): void {
        $saleId = workflowSaleIn(SaleStatus::Approved);
        $this->ricePrice->update(['amount' => 1200]);
        app(TaxService::class)->update($this->levy, TaxData::fromArray([
            'code'             => 'LEVY',
            'name'             => 'Tax',
            'calculation_type' => 'percentage',
            'brackets'         => [['base_min' => '0', 'base_max' => null, 'value' => '10']],
        ]));

        saleGesture($saleId, 'confirm', ['keep_approved_prices' => true])
            ->assertUnprocessable()
            ->assertJsonPath('message', __('sale::exceptions.amounts_changed', ['number' => workflowSale($saleId)->number, 'items' => workflowLabel($this->rice)]));

        expect(workflowSale($saleId)->status)->toBe(SaleStatus::Approved);
    });

    it('refuses a keep_approved_prices that is not a boolean', function (): void {
        $saleId = workflowSaleIn(SaleStatus::Approved);

        saleGesture($saleId, 'confirm', ['keep_approved_prices' => 'maybe'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['keep_approved_prices']);
    });

    it('says to reopen a locked sale when its customer or an item was since deactivated', function (): void {
        $saleId = workflowSaleIn(SaleStatus::Approved);
        $sale = workflowSale($saleId);

        Customer::query()->whereKey($sale->customer_id)->update(['is_active' => false]);
        saleGesture($saleId, 'confirm')
            ->assertUnprocessable()
            ->assertJsonPath('message', confirmationBlocked($saleId, __('sale::exceptions.customer_inactive', ['customer' => $sale->customer_name])));

        Customer::query()->whereKey($sale->customer_id)->update(['is_active' => true]);
        CatalogItem::query()->whereKey($this->rice->id)->update(['is_active' => false]);
        saleGesture($saleId, 'confirm')
            ->assertUnprocessable()
            ->assertJsonPath('message', confirmationBlocked($saleId, __('sale::exceptions.items_inactive', ['items' => workflowLabel($this->rice)])));

        expect(workflowSale($saleId)->status)->toBe(SaleStatus::Approved);
    });

    it('validates and confirms once the draft is reopened and sent again', function (): void {
        $saleId = workflowSaleIn(SaleStatus::Approved);
        $this->ricePrice->update(['amount' => 1200]);
        saleGesture($saleId, 'confirm')->assertUnprocessable();

        saleGesture($saleId, 'reopen')->assertOk();
        $this->putJson("/v1/sale/sales/{$saleId}", workflowDraftPayload(['version' => 1]))->assertNoContent();
        saleGesture($saleId, 'validate', ['version' => 2])->assertOk()->assertJsonPath('data.status', 'approved');

        saleGesture($saleId, 'confirm')
            ->assertOk()
            ->assertJsonPath('data.status', 'active')
            ->assertJsonPath('data.lines_total', '2502');
    });

    it('runs the same number of queries for 2 lines and for 20', function (): void {
        $lines = collect(range(1, 20))->map(function (int $index): array {
            $variant = workflowVariant("Grain {$index}", $this->profile->id);
            workflowPrice($variant, 1180, [$this->vat->id]);

            return ['catalog_item_id' => $variant->id, 'unit_code' => 'kg', 'quantity' => $index];
        });
        $countQueries = function (array $lines): int {
            $saleId = workflowDraft(['lines' => $lines]);
            saleGesture($saleId, 'validate', ['version' => 1], query: '')->assertNoContent();
            DB::flushQueryLog();
            DB::enableQueryLog();
            saleGesture($saleId, 'confirm', query: '')->assertNoContent();
            DB::disableQueryLog();

            return count(DB::getQueryLog());
        };

        $countQueries($lines->take(2)->all());

        expect($countQueries($lines->take(20)->all()))->toBe($countQueries($lines->take(2)->all()));
    });
});

describe('abandon and cancel', function (): void {
    it('abandons a sale before it is active, keeping the reason given', function (SaleStatus $status): void {
        $saleId = workflowSaleIn($status);

        saleGesture($saleId, 'abandon', ['reason' => 'Customer left'])->assertOk()->assertJsonPath('data.status', 'abandoned');
        expect(lastSaleTransition($saleId)->reason)->toBe('Customer left');
    })->with([SaleStatus::Draft, SaleStatus::WaitingApproval, SaleStatus::Rejected, SaleStatus::Approved]);

    it('cannot abandon an active sale', function (): void {
        $saleId = workflowSaleIn(SaleStatus::Active);

        saleGesture($saleId, 'abandon')->assertUnprocessable();
    });

    it('puts a completed sale in cancelling too', function (): void {
        $saleId = workflowSaleIn(SaleStatus::Active);
        // only the system makes a sale completed; no slice does it yet
        Sale::query()->whereKey($saleId)->update(['status' => SaleStatus::Completed->value]);

        saleGesture($saleId, 'cancel')->assertOk()->assertJsonPath('data.status', 'cancelling');
    });

    it('resumes a cancelling sale to active, keeping the reason given', function (): void {
        $saleId = workflowSaleIn(SaleStatus::Cancelling);

        saleGesture($saleId, 'resume', ['reason' => 'The customer changed their mind'])->assertOk()->assertJsonPath('data.status', 'active');

        expect(lastSaleTransition($saleId)->reason)->toBe('The customer changed their mind')
            ->and(SaleTransition::query()->where('sale_id', $saleId)->count())->toBe(5);
    });

    it('refuses to resume a sale that is not being cancelled, with the generic refusal', function (SaleStatus $status): void {
        $saleId = workflowSaleIn($status);
        $number = workflowSale($saleId)->number;

        saleGesture($saleId, 'resume')
            ->assertUnprocessable()
            ->assertJsonPath('message', fn (string $message): bool => str_contains($message, $number) && str_contains($message, 'resume'));

        expect(workflowSale($saleId)->status)->toBe($status);
    })->with([SaleStatus::Draft, SaleStatus::WaitingApproval, SaleStatus::Rejected, SaleStatus::Approved, SaleStatus::Active, SaleStatus::Abandoned]);

    it('puts an active sale in cancelling, keeping the reason given; only the system makes it cancelled', function (): void {
        $saleId = workflowSaleIn(SaleStatus::Active);

        saleGesture($saleId, 'cancel', ['reason' => 'Wrong customer'])->assertOk()->assertJsonPath('data.status', 'cancelling');

        expect(workflowSale($saleId)->cancelled_at)->toBeNull()
            ->and(lastSaleTransition($saleId)->reason)->toBe('Wrong customer');
    });
});

it('keeps one transition per gesture, rendered with the sale', function (): void {
    $saleId = workflowDraft(workflowCredit());
    $validatorId = workflowActAs(workflowPermissionsWithout('sale_sale.approve'));
    saleGesture($saleId, 'validate', ['version' => 1])->assertOk();
    $approverId = workflowActAs(SALE_WORKFLOW_PERMISSIONS);
    saleGesture($saleId, 'reject', ['reason' => 'Too low'])->assertOk();
    saleGesture($saleId, 'reopen')->assertOk();
    saleGesture($saleId, 'abandon')->assertOk();

    $response = $this->getJson("/v1/sale/sales/{$saleId}?include=transitions")->assertOk()->assertJsonCount(5, 'data.transitions');

    expect(collect($response->json('data.transitions'))->map(fn (array $row): array => [
        $row['event'], $row['from_status'], $row['to_status'], $row['member_id'], $row['reason'],
    ])->all())->toBe([
        ['create', null, 'draft', $this->memberId, null],
        ['validate', 'draft', 'waiting_approval', $validatorId, null],
        ['reject', 'waiting_approval', 'rejected', $approverId, 'Too low'],
        ['reopen', 'rejected', 'draft', $approverId, null],
        ['abandon', 'draft', 'abandoned', $approverId, null],
    ]);
});

it('requires the permission of each gesture', function (SaleStatus $status, string $gesture, array $payload, string $permission): void {
    $saleId = workflowSaleIn($status);
    workflowActAs(workflowPermissionsWithout($permission));

    saleGesture($saleId, $gesture, $payload)->assertForbidden();
    expect(workflowSale($saleId)->status)->toBe($status);
})->with([
    'validate' => [SaleStatus::Draft, 'validate', ['version' => 1], 'sale_sale.update'],
    'reopen'   => [SaleStatus::Approved, 'reopen', [], 'sale_sale.update'],
    'abandon'  => [SaleStatus::Draft, 'abandon', [], 'sale_sale.update'],
    'approve'  => [SaleStatus::WaitingApproval, 'approve', [], 'sale_sale.approve'],
    'reject'   => [SaleStatus::WaitingApproval, 'reject', ['reason' => 'No'], 'sale_sale.approve'],
    'confirm'  => [SaleStatus::Approved, 'confirm', [], 'sale_sale.confirm'],
    'cancel'   => [SaleStatus::Active, 'cancel', [], 'sale_sale.cancel'],
    'resume'   => [SaleStatus::Cancelling, 'resume', [], 'sale_sale.cancel'],
]);

it('does not reach a sale of another organization', function (string $gesture, array $payload): void {
    $foreignOrganization = Organization::factory()->create();
    $foreign = Sale::factory()->create(['organization_id' => $foreignOrganization->id]);

    saleGesture($foreign->id, $gesture, $payload)->assertForbidden();
    expect(SaleTransition::query()->where('sale_id', $foreign->id)->count())->toBe(0);
})->with([
    'validate' => ['validate', ['version' => 1]],
    'reopen'   => ['reopen', []],
    'abandon'  => ['abandon', []],
    'approve'  => ['approve', []],
    'reject'   => ['reject', ['reason' => 'No']],
    'confirm'  => ['confirm', []],
    'cancel'   => ['cancel', []],
    'resume'   => ['resume', []],
]);

describe('due date following the customer', function (): void {
    function followingSale(string $customerDate): array
    {
        $customer = Customer::factory()->create(['organization_id' => currentOrganizationId(), 'payment_due_date' => $customerDate]);
        $saleId = workflowDraft(workflowCredit(['customer_id' => $customer->id, 'payment_due_date' => null]));

        return [$saleId, $customer];
    }

    function updateCustomerDueDate(Customer $customer, ?string $date): TestResponse
    {
        return test()->patchJson("/v1/customer/customers/{$customer->id}", ['payment_due_date' => $date]);
    }

    it('renders due_date from the sale, from the customer, and null when paid at once', function (): void {
        $customerDate = now()->addDays(45)->toDateString();
        [$followingId] = followingSale($customerDate);
        $ownId = workflowDraft(workflowCredit(['payment_due_date' => now()->addDays(5)->toDateString()]));
        $paidId = workflowDraft(['customer_id' => Customer::query()->where('payment_due_date', $customerDate)->value('id')]);

        $this->getJson("/v1/sale/sales/{$followingId}")
            ->assertOk()
            ->assertJsonPath('data.payment_due_date', null)
            ->assertJsonPath('data.due_date', $customerDate);
        $this->getJson("/v1/sale/sales/{$ownId}")->assertJsonPath('data.due_date', now()->addDays(5)->toDateString());
        $this->getJson("/v1/sale/sales/{$paidId}")->assertJsonPath('data.due_date', null);
    });

    it('refuses to delete a customer with a sale under way, and deletes it once the sale is abandoned', function (): void {
        $customer = Customer::factory()->create(['organization_id' => $this->organization->id]);
        $saleId = workflowDraft(['customer_id' => $customer->id]);
        saleGesture($saleId, 'validate', ['version' => 1])->assertOk();

        $this->deleteJson("/v1/customer/customers/{$customer->id}")
            ->assertUnprocessable()
            ->assertJsonPath('message', __('customer::exceptions.in_use'));

        saleGesture($saleId, 'abandon')->assertOk();
        $this->deleteJson("/v1/customer/customers/{$customer->id}")->assertNoContent();
    });

    it('moves the due_date of a sale that follows the customer when the customer date moves', function (): void {
        [$saleId, $customer] = followingSale(now()->addDays(20)->toDateString());
        saleGesture($saleId, 'validate', ['version' => 1])->assertOk()->assertJsonPath('data.status', 'approved');
        $later = now()->addDays(60)->toDateString();

        updateCustomerDueDate($customer, $later)->assertNoContent();

        $this->getJson("/v1/sale/sales/{$saleId}")->assertJsonPath('data.due_date', $later);
        expect(workflowSale($saleId)->payment_due_date)->toBeNull();
    });

    it('accepts a credit that follows the customer date and refuses one with no date anywhere', function (): void {
        [$saleId] = followingSale(now()->addDays(20)->toDateString());
        saleGesture($saleId, 'validate', ['version' => 1])->assertOk()->assertJsonPath('data.status', 'approved');

        $bare = Customer::factory()->create(['organization_id' => $this->organization->id, 'payment_due_date' => null]);
        $bareId = workflowDraft(workflowCredit(['customer_id' => $bare->id, 'payment_due_date' => null]));
        saleGesture($bareId, 'validate', ['version' => 1])
            ->assertUnprocessable()
            ->assertJsonPath('message', __('sale::exceptions.payment_due_date_missing', ['number' => workflowSale($bareId)->number]));
    });

    it('refuses to empty the customer date while an approved credit follows it, saying how to get out', function (): void {
        [$saleId, $customer] = followingSale(now()->addDays(20)->toDateString());
        saleGesture($saleId, 'validate', ['version' => 1])->assertOk();

        updateCustomerDueDate($customer, null)
            ->assertUnprocessable()
            ->assertJsonPath('message', __('customer::exceptions.due_date_is_followed'));

        expect($customer->fresh()->payment_due_date)->not->toBeNull()
            ->and(__('customer::exceptions.due_date_is_followed'))->toContain('Keep a date on the customer', 'move it');
    });

    it('lets the customer date be emptied when no credit sale follows it', function (string $case): void {
        $customer = Customer::factory()->create(['organization_id' => $this->organization->id, 'payment_due_date' => workflowDueDate()]);
        $payload = match ($case) {
            'own date'      => workflowCredit(),
            'paid at once'  => [],
            'abandoned'     => workflowCredit(['payment_due_date' => null]),
            'still a draft' => workflowCredit(['payment_due_date' => null]),
        };
        $saleId = workflowDraft(['customer_id' => $customer->id, ...$payload]);

        if ($case === 'abandoned') {
            saleGesture($saleId, 'abandon')->assertOk();
        } elseif ($case !== 'still a draft') {
            saleGesture($saleId, 'validate', ['version' => 1])->assertOk()->assertJsonPath('data.status', 'approved');
        }

        updateCustomerDueDate($customer, null)->assertNoContent();

        expect($customer->fresh()->payment_due_date)->toBeNull();
    })->with(['own date', 'paid at once', 'abandoned', 'still a draft']);

    it('runs no more queries to list more sales with their customer', function (): void {
        followingSale(now()->addDays(20)->toDateString());
        $queries = function (): int {
            DB::flushQueryLog();
            DB::enableQueryLog();
            test()->getJson('/v1/sale/sales?include=customer')->assertOk();
            $count = count(DB::getQueryLog());
            DB::disableQueryLog();

            return $count;
        };
        $withOne = $queries();

        foreach (range(1, 4) as $unused) {
            workflowDraft(workflowCredit(['customer_id' => Customer::factory()->create(['organization_id' => $this->organization->id, 'payment_due_date' => workflowDueDate()])->id, 'payment_due_date' => null]));
        }

        expect($queries())->toBe($withOne);
    });
});
