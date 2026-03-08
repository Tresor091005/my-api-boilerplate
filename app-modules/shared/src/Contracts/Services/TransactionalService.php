<?php

declare(strict_types=1);

namespace Lahatre\Shared\Contracts\Services;

/**
 * Interface TransactionalService
 *
 * Marks a service that provides reusable business logic but
 * does NOT manage its own database transactions.
 * It must be called from within a StandaloneService's transaction.
 */
interface TransactionalService {}
