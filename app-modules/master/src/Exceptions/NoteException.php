<?php

declare(strict_types=1);

namespace Lahatre\Master\Exceptions;

use Lahatre\Shared\Exceptions\AssertionException;

final class NoteException extends AssertionException
{
    public static function rootHasReplies(): self
    {
        return self::message(__('master::exceptions.note_root_has_replies'));
    }

    public static function repliesCannotExpire(): self
    {
        return self::message(__('master::exceptions.note_replies_cannot_expire'));
    }

    public static function rootCannotExpireWithReplies(): self
    {
        return self::message(__('master::exceptions.note_root_cannot_expire_with_replies'));
    }

    public static function mentionedVisibilityRequiresMembers(): self
    {
        return self::message(__('master::exceptions.note_mentioned_visibility_requires_members'));
    }

    public static function visibilityCannotBeChanged(): self
    {
        return self::message(__('master::exceptions.note_visibility_cannot_be_changed'));
    }

    public static function expiredNoteCannotReceiveReplies(): self
    {
        return self::message(__('master::exceptions.note_expired_cannot_receive_replies'));
    }

    public static function expiredNoteCannotBePinned(): self
    {
        return self::message(__('master::exceptions.note_expired_cannot_be_pinned'));
    }

    public static function mentionsRequireMentionedVisibility(): self
    {
        return self::message(__('master::exceptions.note_mentions_require_mentioned_visibility'));
    }

    public static function invalidNotableTarget(): self
    {
        return self::message(__('master::exceptions.note_invalid_notable_target'));
    }

    public static function invalidMentionTarget(): self
    {
        return self::message(__('master::exceptions.note_invalid_mention_target'));
    }

    public static function memberContextRequired(): self
    {
        return self::message(__('master::exceptions.note_member_context_required'));
    }

    private static function message(string $message, array $context = []): self
    {
        return new self($message, $context);
    }

    /** @param array<string, mixed> $context */
    private function __construct(string $message, array $context = [])
    {
        parent::__construct($message, $context);
    }
}
