<?php

declare(strict_types=1);

namespace Sifrious\Funes\Assertion;

use JsonSerializable;
use Sifrious\Funes\Concern\HasEvidence;
use Sifrious\Funes\Concern\HasProvenance;
use Sifrious\Funes\Concern\HasStableIdentity;
use Sifrious\Funes\Concern\HasTemporalCoordinates;
use Sifrious\Funes\Concern\HasTenantScope;
use Sifrious\Funes\Graph\AssertionType;
use Sifrious\ReferenceContract\CrossPackageReference;

/**
 * A single durable historical claim: a subject, a predicate, a value, and the
 * evidentiary and temporal circumstances under which the claim was made.
 *
 * Consumers depend on this interface without knowing which provider supplied the
 * material. No member of this contract may name a provider, a storage vendor, a
 * transport, or a framework.
 *
 * Identity, provenance, temporal coordinates, tenant scope, and evidence are the
 * five concerns this object composes; they are shared with the other historical
 * substrate objects rather than redeclared here. The members below are the ones
 * specific to an assertion. Concerns deliberately not composed — provider identity,
 * actor, authorization context, effective interval, immutable version, content hash,
 * confidence, parent, and external references — are recorded with their reasons in
 * `docs/concerns.md`.
 */
interface HistoricalAssertionContract extends HasEvidence, HasProvenance, HasStableIdentity, HasTemporalCoordinates, HasTenantScope, JsonSerializable
{
    /** Whether the claim was observed at a source, declared by a source, or inferred. */
    public function assertionType(): AssertionType;

    /** The durable reference the claim is about. */
    public function subject(): CrossPackageReference;

    /** The stable lowercase name of the claimed property or relation. */
    public function predicate(): string;

    /** The claimed value. Always JSON-encodable; never an object or resource. */
    public function value(): mixed;

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
