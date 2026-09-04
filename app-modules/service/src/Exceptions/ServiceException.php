<?php

declare(strict_types=1);

namespace Lahatre\Service\Exceptions;

use Lahatre\Shared\Exceptions\AssertionException;

final class ServiceException extends AssertionException
{
    public static function invalidCommitmentState(): self
    {
        return new self(__('service::exceptions.invalid_commitment_state'));
    }

    public static function invalidDeliverableState(): self
    {
        return new self(__('service::exceptions.invalid_deliverable_state'));
    }

    public static function invalidEvidenceState(): self
    {
        return new self(__('service::exceptions.invalid_evidence_state'));
    }

    public static function commitmentAlreadyExists(): self
    {
        return new self(__('service::exceptions.commitment_already_exists'));
    }

    public static function serviceTemplatesRequired(): self
    {
        return new self(__('service::exceptions.service_templates_required'));
    }

    private function __construct(string $message, array $context = [])
    {
        parent::__construct($message, $context);
    }
}
