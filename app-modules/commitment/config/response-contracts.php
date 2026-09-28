<?php

declare(strict_types=1);

$resource = ['default_shape' => 'default', 'shapes' => ['default' => []]];

return [
    'lahatre.commitment.service-commitments.index'                       => $resource,
    'lahatre.commitment.service-commitments.store'                       => $resource,
    'lahatre.commitment.service-commitments.show'                        => $resource,
    'lahatre.commitment.service-commitments.update'                      => $resource,
    'lahatre.commitment.service-commitments.proposals.index'             => $resource,
    'lahatre.commitment.service-commitments.proposals.store'             => $resource,
    'lahatre.commitment.service-commitments.deliverables.index'          => $resource,
    'lahatre.commitment.service-commitments.deliverables.store'          => $resource,
    'lahatre.commitment.service-commitments.deliverables.show'           => $resource,
    'lahatre.commitment.service-commitments.deliverables.update'         => $resource,
    'lahatre.commitment.service-commitments.deliverables.evidence.index' => $resource,
    'lahatre.commitment.service-commitments.deliverables.evidence.store' => $resource,
    'lahatre.commitment.service-commitments.closure-requests.store'      => $resource,
    'lahatre.commitment.service-commitments.reopening-requests.store'    => $resource,
    'lahatre.commitment.service-commitments.report.show'                 => $resource,
    'lahatre.commitment.service-commitments.events.index'                => $resource,
    'lahatre.commitment.guest.access-challenges.store'                   => [],
    // The token is returned immediately because the guest needs it for subsequent requests.
    'lahatre.commitment.guest.access-sessions.store'                       => ['default_mode' => 'resource'],
    'lahatre.commitment.guest.service-commitments.show'                    => [],
    'lahatre.commitment.guest.service-commitments.deliverables.index'      => [],
    'lahatre.commitment.guest.service-commitments.evidence.index'          => [],
    'lahatre.commitment.guest.service-commitments.reviews.index'           => [],
    'lahatre.commitment.guest.service-commitments.evidence.files.index'    => [],
    'lahatre.commitment.guest.service-commitments.report.show'             => [],
    'lahatre.commitment.guest.service-commitments.evidence.files.content'  => [],
    'lahatre.commitment.guest.service-commitments.proposal-reviews.store'  => $resource,
    'lahatre.commitment.guest.service-commitments.evidence-reviews.store'  => $resource,
    'lahatre.commitment.guest.service-commitments.closure-reviews.store'   => $resource,
    'lahatre.commitment.guest.service-commitments.reopening-reviews.store' => $resource,
];
