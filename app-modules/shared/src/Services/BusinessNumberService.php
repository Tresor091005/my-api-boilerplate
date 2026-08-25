<?php

declare(strict_types=1);

namespace Lahatre\Shared\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Lahatre\Shared\Exceptions\BusinessNumberException;

final class BusinessNumberService
{
    private const array SYSTEM_TOKENS = ['YEAR', 'YEAR2', 'MONTH', 'DAY', 'SEQ'];

    private const array RESET_PERIODS = ['never', 'daily', 'monthly', 'yearly'];

    /**
     * Generate the next human-readable number for the active organization.
     *
     * The atomic counter statement uses the current Laravel database connection. It
     * participates in an existing transaction and is automatically committed when
     * called outside one. Consuming a number outside the surrounding business
     * transaction can therefore leave a gap if that business operation later fails.
     *
     * @throws BusinessNumberException
     */
    public static function next(string $key): string
    {
        $definition = self::resolveDefinition($key);
        $now = now();
        $organizationId = currentOrganizationId();

        $numberIdentity = self::render(
            format: $definition['format'],
            sequence: '0',
            now: $now,
        );

        $value = self::incrementCounter(
            key: $key,
            organizationId: $organizationId,
            numberIdentity: $numberIdentity,
            start: $definition['sequence']['start'],
        );

        return self::render(
            format: $definition['format'],
            sequence: self::formatSequence($value, $definition['sequence']),
            now: $now,
        );
    }

    /**
     * @return array{
     *     format: string,
     *     reset: string,
     *     sequence: array{start: int, pad: int, grouping: array{every: int, separator: string}|null}
     * }
     */
    private static function resolveDefinition(string $key): array
    {
        $definition = config('business-numbering.'.$key);

        if (!is_array($definition)) {
            throw BusinessNumberException::definitionNotFound($key);
        }

        self::validateDefinition($key, $definition);

        /** @var array{format: string, reset: string, sequence: array{start: int, pad: int, grouping: array{every: int, separator: string}|null}} $definition */
        return $definition;
    }

    /**
     * @param  array<string, mixed>  $definition
     */
    private static function validateDefinition(string $key, array $definition): void
    {
        $format = $definition['format'] ?? null;
        $reset = $definition['reset'] ?? null;
        $sequence = $definition['sequence'] ?? null;

        if (!is_string($format) || $format === '') {
            throw BusinessNumberException::invalidDefinition($key, __('shared::exceptions.business_number_reasons.format_empty'));
        }

        if (!is_string($reset) || !in_array($reset, self::RESET_PERIODS, true)) {
            throw BusinessNumberException::invalidDefinition($key, __('shared::exceptions.business_number_reasons.reset_invalid'));
        }

        if (!is_array($sequence)) {
            throw BusinessNumberException::invalidDefinition($key, __('shared::exceptions.business_number_reasons.sequence_invalid'));
        }

        $start = $sequence['start'] ?? null;
        $pad = $sequence['pad'] ?? null;
        $grouping = $sequence['grouping'] ?? null;

        if (!is_int($start) || $start < 1) {
            throw BusinessNumberException::invalidDefinition($key, __('shared::exceptions.business_number_reasons.sequence_start_invalid'));
        }

        if (!is_int($pad) || $pad < 1) {
            throw BusinessNumberException::invalidDefinition($key, __('shared::exceptions.business_number_reasons.sequence_pad_invalid'));
        }

        if ($grouping !== null && (!is_array($grouping)
            || !is_int($grouping['every'] ?? null)
            || $grouping['every'] < 1
            || !is_string($grouping['separator'] ?? null)
            || $grouping['separator'] === '')) {
            throw BusinessNumberException::invalidDefinition($key, __('shared::exceptions.business_number_reasons.grouping_invalid'));
        }

        $tokens = self::formatTokens($key, $format);

        if (count(array_keys($tokens, 'SEQ', true)) !== 1) {
            throw BusinessNumberException::invalidDefinition($key, __('shared::exceptions.business_number_reasons.seq_token_invalid', ['token' => '{SEQ}']));
        }

        self::validateResetFormat($key, $reset, $tokens);

        $unsupportedTokens = array_values(array_diff(array_unique($tokens), self::SYSTEM_TOKENS));

        if ($unsupportedTokens !== []) {
            throw BusinessNumberException::invalidDefinition($key, __('shared::exceptions.business_number_reasons.unsupported_tokens'));
        }
    }

    /**
     * @return array<int, string>
     */
    private static function formatTokens(string $key, string $format): array
    {
        preg_match_all('/\{([^{}]*)\}/', $format, $matches);
        $tokens = $matches[1];

        if (substr_count($format, '{') !== count($tokens) || substr_count($format, '}') !== count($tokens)) {
            throw BusinessNumberException::invalidDefinition($key, __('shared::exceptions.business_number_reasons.unmatched_brace'));
        }

        foreach ($tokens as $token) {
            if (!preg_match('/^[A-Z][A-Z0-9_]*$/', $token)) {
                throw BusinessNumberException::invalidDefinition($key, __('shared::exceptions.business_number_reasons.invalid_token', ['token' => $token]));
            }
        }

        return $tokens;
    }

    /**
     * @param  array<int, string>  $tokens
     */
    private static function validateResetFormat(string $key, string $reset, array $tokens): void
    {
        $hasYear = in_array('YEAR', $tokens, true) || in_array('YEAR2', $tokens, true);
        $hasMonth = in_array('MONTH', $tokens, true);
        $hasDay = in_array('DAY', $tokens, true);

        $matchesReset = match ($reset) {
            'never'   => true,
            'yearly'  => $hasYear,
            'monthly' => $hasYear && $hasMonth,
            'daily'   => $hasYear && $hasMonth && $hasDay,
            default   => false,
        };

        if (!$matchesReset) {
            throw BusinessNumberException::invalidDefinition($key, __('shared::exceptions.business_number_reasons.reset_format_invalid'));
        }
    }

    private static function incrementCounter(
        string $key,
        string $organizationId,
        string $numberIdentity,
        int $start,
    ): string {
        $numberIdentityHash = hash('sha256', $numberIdentity);
        $row = DB::selectOne(<<<'SQL'
            INSERT INTO shared_business_number_counters
                (id, organization_id, number_identity_hash, number_identity, value)
            VALUES (?, ?, ?, ?, ?)
            ON CONFLICT (organization_id, number_identity_hash)
            DO UPDATE SET
                value = shared_business_number_counters.value + 1
            WHERE shared_business_number_counters.number_identity = EXCLUDED.number_identity
            RETURNING value
            SQL, [
            Str::uuid7()->toString(),
            $organizationId,
            $numberIdentityHash,
            $numberIdentity,
            $start,
        ]);

        if ($row === null) {
            throw BusinessNumberException::invalidDefinition($key, __('shared::exceptions.business_number_reasons.counter_missing'));
        }

        return (string) $row->value;
    }

    private static function render(
        string $format,
        string $sequence,
        \DateTimeInterface $now,
    ): string {
        $system = [
            'YEAR'  => $now->format('Y'),
            'YEAR2' => $now->format('y'),
            'MONTH' => $now->format('m'),
            'DAY'   => $now->format('d'),
            'SEQ'   => $sequence,
        ];

        return (string) preg_replace_callback(
            '/\{([A-Z][A-Z0-9_]*)\}/',
            static function (array $match) use ($system): string {
                $name = $match[1];

                return $system[$name];
            },
            $format,
        );
    }

    /**
     * @param  array{start: int, pad: int, grouping: array{every: int, separator: string}|null}  $sequence
     */
    private static function formatSequence(string $value, array $sequence): string
    {
        $digits = str_pad($value, $sequence['pad'], '0', STR_PAD_LEFT);
        $grouping = $sequence['grouping'];

        if ($grouping === null) {
            return $digits;
        }

        $chunks = [];
        while ($digits !== '') {
            array_unshift($chunks, substr($digits, -$grouping['every']));
            $digits = substr($digits, 0, -$grouping['every']);
        }

        return implode($grouping['separator'], $chunks);
    }
}
