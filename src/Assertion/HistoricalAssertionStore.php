<?php

declare(strict_types=1);

namespace Sifrious\Funes\Assertion;

use DateTimeImmutable;
use Sifrious\AuthorizationContract\AuthorizationContext;
use Sifrious\ReferenceContract\CrossPackageReference;

/**
 * The durable system of record for canonical historical assertions.
 *
 * Every method takes the caller's authorization context and scopes its work to that
 * context's tenant. Reads never cross a tenant boundary and never reveal that they
 * could have: an assertion belonging to another tenant is absent, not forbidden, so
 * existence does not leak through an error. Appending into a tenant the caller does
 * not hold is a different matter and fails explicitly.
 *
 * Nothing here mutates or deletes. An append is idempotent by the assertion
 * fingerprint, and a withdrawal is a tombstone that hides a claim while preserving it.
 */
interface HistoricalAssertionStore
{
    /**
     * Append one assertion, or return the one already stored for the same claim.
     *
     * @throws UnauthorizedAssertion when the assertion's tenant is not the caller's
     * @throws AssertionConflict when the assertion's identity is already held by a different claim
     */
    public function append(AbstractHistoricalAssertion $assertion, AuthorizationContext $authorization): AcceptedAssertion;

    /** The assertion with this identity, or null when absent, tombstoned, or another tenant's. */
    public function get(string $id, AuthorizationContext $authorization): ?AbstractHistoricalAssertion;

    /**
     * Every live assertion about a subject, most recently recorded first.
     *
     * @return list<AbstractHistoricalAssertion>
     */
    public function forSubject(CrossPackageReference $subject, AuthorizationContext $authorization, ?string $predicate = null): array;

    /**
     * What the store knew about one subject and predicate at a past moment.
     *
     * This reconstructs by transaction time: the latest assertion recorded at or
     * before `$knownAt`, ignoring anything the store learned afterwards. A tombstone
     * applies only from the moment it was recorded, so a claim withdrawn today is
     * still returned for a moment before the withdrawal — which is the point of
     * asking what was known then rather than what is believed now.
     *
     * Valid-time reconstruction over an effective interval is not this object's
     * concern; an assertion is a point claim. That belongs to HistoricalEntityVersion.
     */
    public function asOf(CrossPackageReference $subject, string $predicate, DateTimeImmutable $knownAt, AuthorizationContext $authorization): ?AbstractHistoricalAssertion;

    /**
     * Withdraw an assertion from the live view without destroying it.
     *
     * Repeating a tombstone is idempotent and preserves the original withdrawal.
     *
     * @throws UnauthorizedAssertion when the assertion is not the caller's tenant's
     */
    public function tombstone(string $id, AuthorizationContext $authorization, string $reason): AssertionTombstone;

    /** The withdrawal record for an assertion, or null when it is not tombstoned. */
    public function tombstoneOf(string $id, AuthorizationContext $authorization): ?AssertionTombstone;
}
