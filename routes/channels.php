<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Broadcast;
use Lahatre\Iam\Models\User;

/*
 * Matches Model::broadcastChannel() and the default notification channel of
 * the IAM user. User IDs are UUIDs, so they are compared as strings.
 */
Broadcast::channel('Lahatre.Iam.Models.User.{id}', fn (User $user, string $id): bool => $user->id === $id);
