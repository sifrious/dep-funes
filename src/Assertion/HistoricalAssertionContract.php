<?php

declare(strict_types=1);

namespace Sifrious\Funes\Assertion;

use DateTimeImmutable;
use JsonSerializable;
use Sifrious\AuthorizationContract\TenantScope;
use Sifrious\Funes\Graph\AssertionType;
use Sifrious\Funes\Value\SourceLocator;
use Sifrious\ReferenceContract\CrossPackageReference;

/**
 * A single durable historical claim: a subject, a predicate, a value, and the
 * evidentiary and temporal circumstances under which the claim was made.
 *
 * Consumers depend on this interface without knowing which provider supplied the
 * material. No member of this contract may name a provider, a storage vendor, a
 * transport, or a framework.
 */
interface HistoricalAssertionContract extends JsonSerializable
{
    /** The stable, opaque identity of this assertion. */
    public function assertionId(): string;

    /** Whether the claim was observed at a source, declared by a source, or inferred. */
    public function assertionType(): AssertionType;

    /** The durable reference the claim is about. */
    public function subject(): CrossPackageReference;

    /** The stable lowercase name of the claimed property or relation. */
    public function predicate(): string;

    /** The claimed value. Always JSON-encodable; never an object or resource. */
    public function value(): mixed;

    /** Where the claim came from, in terms the owning source can resolve. */
    public function source(): SourceLocator;

    /** The provenance assertion that carried this claim, when one is recorded. */
    public function provenance(): ?CrossPackageReference;

    /** When the claimed fact held, when the source reports it. */
    public function occurredAt(): ?DateTimeImmutable;

    /** When the claim was observed at the source. */
    public function observedAt(): DateTimeImmutable;

    /** When the claim entered this history store. */
    public function recordedAt(): DateTimeImmutable;

    /** The tenant whose evidence this assertion belongs to. */
    public function tenant(): TenantScope;

    /**
     * Supporting evidence. Non-empty for inferred assertions.
     *
     * @return list<CrossPackageReference>
     */
    public function evidence(): array;

    /**
     * A digest of the durable fact — type, subject, predicate, value, source,
     * occurrence, and tenant — excluding this assertion's own identity and its
     * observation and recording times. Two encounters of the same claim share a
     * fingerprint, which is what makes repeated ingestion idempotent.
     */
    public function fingerprint(): string;

    /** @return array<string, mixed> */
    public function toArray(): array;
}
