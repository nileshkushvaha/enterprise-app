<?php

declare(strict_types=1);

namespace App\Booking\DTOs;

/**
 * The answer to "does the provider already hold a meeting for this
 * booking?" after an AMBIGUOUS create — stated as what was established,
 * never as a guess.
 *
 *  - `found`        exactly one remote meeting carries this booking's
 *                   reference → it is THE meeting and may be adopted;
 *  - `none`         the search was exhaustive and matched nothing. This
 *                   does NOT prove the original create failed (the
 *                   listing is eventually consistent and bounded to
 *                   upcoming meetings), so callers still fail closed;
 *  - `inconclusive` more than one match, or the bounded search could
 *                   not cover every page. Nothing may be adopted.
 *
 * Only `found` ever leads to an automatic write. Every other status
 * leaves the meeting `failed` with remote_state_unknown set until an
 * operator resolves it explicitly.
 */
final readonly class RemoteMeetingReconciliation
{
    public const string FOUND = 'found';

    public const string NONE = 'none';

    public const string INCONCLUSIVE = 'inconclusive';

    /**
     * @param  list<string>  $candidateIds  every provider meeting id that matched
     */
    public function __construct(
        public string $status,
        public array $candidateIds,
        public bool $exhaustive,
        public string $reason,
    ) {}

    public static function found(string $providerMeetingId): self
    {
        return new self(self::FOUND, [$providerMeetingId], true, 'Exactly one remote meeting carries this booking reference.');
    }

    public static function none(): self
    {
        return new self(self::NONE, [], true, 'No upcoming remote meeting carries this booking reference. This does not prove the original create failed.');
    }

    /** @param  list<string>  $candidateIds */
    public static function multiple(array $candidateIds): self
    {
        return new self(self::INCONCLUSIVE, $candidateIds, true, sprintf('%d remote meetings carry this booking reference; none can be adopted automatically.', count($candidateIds)));
    }

    /** @param  list<string>  $candidateIds */
    public static function truncated(array $candidateIds): self
    {
        return new self(self::INCONCLUSIVE, $candidateIds, false, 'The bounded search did not cover every page of the host\'s upcoming meetings.');
    }

    public function isFound(): bool
    {
        return $this->status === self::FOUND;
    }

    public function singleCandidate(): ?string
    {
        return $this->isFound() ? $this->candidateIds[0] : null;
    }
}
