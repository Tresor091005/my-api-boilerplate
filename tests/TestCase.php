<?php

declare(strict_types=1);

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected mixed $organization;

    protected mixed $otherOrganization;

    protected mixed $organizationId;

    protected mixed $otherOrganizationId;

    protected mixed $user;

    protected mixed $member;

    protected mixed $memberRole;

    protected mixed $role;

    protected mixed $service;

    protected mixed $inventoryService;

    protected mixed $product;

    protected mixed $otherProduct;

    protected mixed $unitGroup;

    protected mixed $group;

    protected mixed $unit;

    protected mixed $currency;

    protected mixed $item;

    protected mixed $location;

    protected mixed $locA;

    protected mixed $locB;

    protected mixed $unitOne;

    protected mixed $unitTwo;

    protected mixed $unitThree;

    protected mixed $taggableUnit;

    protected mixed $baseUnit;

    protected mixed $cache;
}
