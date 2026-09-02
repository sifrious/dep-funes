<?php

declare(strict_types=1);

namespace Sifrious\Funes\Assertion;

use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Query\Builder;
use InvalidArgumentException;
use JsonException;
use Sifrious\AuthorizationContract\AuthorizationContext;
use Sifrious\AuthorizationContract\TenantScope;
use Sifrious\ReferenceContract\CrossPackageReference;
use stdClass;

final readonly class SqlHistoricalAssertionStore implements HistoricalAssertionStore
{
    public function __construct(private ConnectionInterface $connection) {}

    public function append(AbstractHistoricalAssertion $assertion, AuthorizationContext $authorization): AcceptedAssertion
    {
        if (! $assertion->tenant()->equals($authorization->tenant)) {
            throw new UnauthorizedAssertion('An assertion cannot be appended into a tenant the caller does not hold.');
        }

        $fingerprint = $assertion->fingerprint();

        return $this->connection->transaction(function () use ($assertion, $fingerprint): AcceptedAssertion {
            $duplicate = $this->connection->table('funes_historical_assertions')
                ->where('fingerprint', $fingerprint)
                ->lockForUpdate()
                ->first();

            if ($duplicate instanceof stdClass) {
                return new AcceptedAssertion(AssertionDisposition::Duplicate, $this->hydrate($duplicate));
            }

            $existing = $this->connection->table('funes_historical_assertions')
                ->where('id', $assertion->stableIdentity())
                ->first();

            if ($existing instanceof stdClass) {
                throw new AssertionConflict(
                    "Historical assertion [{$assertion->stableIdentity()}] is already held by a different claim.",
                );
            }

            $this->connection->table('funes_historical_assertions')->insert([
                'id' => $assertion->stableIdentity(),
                'fingerprint' => $fingerprint,
                'assertion_type' => $assertion->assertionType()->value,
                'subject_key' => $assertion->subject()->key(),
                'predicate' => $assertion->predicate(),
                'tenant_key' => self::tenantKey($assertion->tenant()),
                'occurred_at' => $assertion->occurredAt() === null ? null : self::stamp($assertion->occurredAt()),
                'observed_at' => self::stamp($assertion->observedAt()),
                'recorded_at' => self::stamp($assertion->recordedAt()),
                'document' => self::encode($assertion->toArray()),
            ]);

            return new AcceptedAssertion(AssertionDisposition::First, $assertion);
        }, 3);
    }

    public function get(string $id, AuthorizationContext $authorization): ?AbstractHistoricalAssertion
    {
        $row = $this->live($authorization)->where('funes_historical_assertions.id', $id)->first();

        return $row instanceof stdClass ? $this->hydrate($row) : null;
    }

    public function forSubject(CrossPackageReference $subject, AuthorizationContext $authorization, ?string $predicate = null): array
    {
        $query = $this->live($authorization)->where('subject_key', $subject->key());

        if ($predicate !== null) {
            $query->where('predicate', $predicate);
        }

        $rows = $query
            ->orderByDesc('recorded_at')
            ->orderByDesc('funes_historical_assertions.id')
            ->get();

        return array_values(array_map(fn (stdClass $row): AbstractHistoricalAssertion => $this->hydrate($row), $rows->all()));
    }

    public function asOf(CrossPackageReference $subject, string $predicate, DateTimeImmutable $knownAt, AuthorizationContext $authorization): ?AbstractHistoricalAssertion
    {
        $row = $this->connection->table('funes_historical_assertions')
            ->leftJoin('funes_assertion_tombstones', 'funes_assertion_tombstones.assertion_id', '=', 'funes_historical_assertions.id')
            ->where('tenant_key', self::tenantKey($authorization->tenant))
            ->where('subject_key', $subject->key())
            ->where('predicate', $predicate)
            ->where('recorded_at', '<=', self::stamp($knownAt))
            ->where(function ($query) use ($knownAt): void {
                $query->whereNull('funes_assertion_tombstones.assertion_id')
                    ->orWhere('funes_assertion_tombstones.tombstoned_at', '>', self::stamp($knownAt));
            })
            ->orderByDesc('recorded_at')
            ->orderByDesc('funes_historical_assertions.id')
            ->select('funes_historical_assertions.*')
            ->first();

        return $row instanceof stdClass ? $this->hydrate($row) : null;
    }

    public function tombstone(string $id, AuthorizationContext $authorization, string $reason): AssertionTombstone
    {
        if (trim($reason) === '') {
            throw new InvalidArgumentException('A tombstone requires a reason, so a withdrawal stays auditable.');
        }

        return $this->connection->transaction(function () use ($id, $authorization, $reason): AssertionTombstone {
            $row = $this->connection->table('funes_historical_assertions')
                ->where('id', $id)
                ->where('tenant_key', self::tenantKey($authorization->tenant))
                ->lockForUpdate()
                ->first();

            if (! $row instanceof stdClass) {
                throw new UnauthorizedAssertion("Historical assertion [{$id}] is not available to this tenant.");
            }

            $existing = $this->readTombstone($id);
            if ($existing instanceof AssertionTombstone) {
                return $existing;
            }

            $tombstone = new AssertionTombstone($id, $reason, $authorization, new DateTimeImmutable);

            $this->connection->table('funes_assertion_tombstones')->insert([
                'assertion_id' => $id,
                'reason' => $reason,
                'authorization_context' => self::encode($authorization->toArray()),
                'tombstoned_at' => self::stamp($tombstone->tombstonedAt),
            ]);

            return $tombstone;
        }, 3);
    }

    public function tombstoneOf(string $id, AuthorizationContext $authorization): ?AssertionTombstone
    {
        $owned = $this->connection->table('funes_historical_assertions')
            ->where('id', $id)
            ->where('tenant_key', self::tenantKey($authorization->tenant))
            ->exists();

        return $owned ? $this->readTombstone($id) : null;
    }

    private function readTombstone(string $id): ?AssertionTombstone
    {
        $row = $this->connection->table('funes_assertion_tombstones')->where('assertion_id', $id)->first();

        if (! $row instanceof stdClass) {
            return null;
        }

        return new AssertionTombstone(
            $id,
            $row->reason,
            AuthorizationContext::fromArray(self::decode($row->authorization_context)),
            new DateTimeImmutable($row->tombstoned_at, new DateTimeZone('UTC')),
        );
    }

    /** Live rows are this tenant's and not tombstoned. */
    private function live(AuthorizationContext $authorization): Builder
    {
        return $this->connection->table('funes_historical_assertions')
            ->leftJoin('funes_assertion_tombstones', 'funes_assertion_tombstones.assertion_id', '=', 'funes_historical_assertions.id')
            ->whereNull('funes_assertion_tombstones.assertion_id')
            ->where('tenant_key', self::tenantKey($authorization->tenant))
            ->select('funes_historical_assertions.*');
    }

    private function hydrate(stdClass $row): AbstractHistoricalAssertion
    {
        return HistoricalAssertionCodec::decode(self::decode($row->document));
    }

    /**
     * Timestamp columns are UTC index values with microsecond precision.
     *
     * The driver's own binding format truncates to whole seconds, which would round
     * away history the canonical document preserves, so times are formatted here
     * instead. Normalizing to UTC keeps the columns lexicographically comparable
     * regardless of the offset a source reported; the original offset survives in the
     * document, which is what retrieval hydrates from.
     */
    private static function stamp(DateTimeInterface $time): string
    {
        return DateTimeImmutable::createFromInterface($time)
            ->setTimezone(new DateTimeZone('UTC'))
            ->format('Y-m-d H:i:s.u');
    }

    private static function tenantKey(TenantScope $tenant): string
    {
        return hash('sha256', self::encode($tenant->toArray()));
    }

    /** @param array<string, mixed> $value */
    private static function encode(array $value): string
    {
        return json_encode($value, JSON_THROW_ON_ERROR);
    }

    /** @return array<string, mixed> */
    private static function decode(string $value): array
    {
        try {
            $decoded = json_decode($value, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw new AssertionConflict('A stored historical assertion document is not decodable JSON.');
        }

        if (! is_array($decoded)) {
            throw new AssertionConflict('A stored historical assertion document must be a JSON object.');
        }

        return $decoded;
    }
}
