<?php

declare(strict_types=1);

namespace Sifrious\Funes\Assertion;

use DateTimeImmutable;
use InvalidArgumentException;
use JsonException;
use Sifrious\AuthorizationContract\TenantScope;
use Sifrious\Funes\Graph\AssertionType;
use Sifrious\Funes\Value\SourceLocator;
use Sifrious\ReferenceContract\CrossPackageReference;

/**
 * The provider-neutral base for every historical assertion.
 *
 * This class owns the canonical identity, invariants, temporal semantics, and
 * serialization boundary. Subclasses supply only their assertion type and any
 * provider mapping; they must not redefine the semantics established here.
 *
 * Construction validates every invariant, so an instance is always a well-formed
 * assertion. Instances are readonly: an assertion is never mutated, and a
 * corrected claim is a new assertion related to the earlier one.
 */
abstract readonly class AbstractHistoricalAssertion implements HistoricalAssertionContract
{
    public const CONTRACT = 'sifrious.historical-assertion';

    public const CONTRACT_VERSION = 1;

    /** @var list<CrossPackageReference> */
    public array $evidence;

    public function __construct(
        public string $id,
        public CrossPackageReference $subject,
        public string $predicate,
        public mixed $value,
        public SourceLocator $source,
        public TenantScope $tenant,
        public ?DateTimeImmutable $occurredAt,
        public DateTimeImmutable $observedAt,
        public DateTimeImmutable $recordedAt,
        public ?CrossPackageReference $provenance = null,
        mixed $evidence = [],
    ) {
        if ($id === '' || trim($id) !== $id || preg_match('/\s/', $id) === 1) {
            throw new InvalidArgumentException('Historical assertion ids must be non-empty opaque values without whitespace.');
        }

        if (preg_match('/^[a-z][a-z0-9._-]*$/', $predicate) !== 1) {
            throw new InvalidArgumentException('Historical assertion predicates must be stable lowercase identifiers.');
        }

        self::requireJsonEncodable($value);

        if ($occurredAt !== null && $occurredAt > $observedAt) {
            throw new InvalidArgumentException('A historical assertion cannot be observed before the fact it reports occurred.');
        }

        if ($observedAt > $recordedAt) {
            throw new InvalidArgumentException('A historical assertion cannot be recorded before it was observed.');
        }

        if (! is_array($evidence)) {
            throw new InvalidArgumentException('Historical assertion evidence must be a list of cross-package references.');
        }

        foreach ($evidence as $reference) {
            if (! $reference instanceof CrossPackageReference) {
                throw new InvalidArgumentException('Historical assertion evidence must be cross-package references.');
            }
        }

        $this->evidence = array_values($evidence);

        if ($this->assertionType() === AssertionType::Inferred && $this->evidence === []) {
            throw new InvalidArgumentException('Inferred historical assertions require supporting evidence.');
        }
    }

    /**
     * Fixed by each direct subclass. This is the seam that keeps the taxonomy
     * meaningful: an observation cannot silently become an inference.
     */
    abstract public function assertionType(): AssertionType;

    public function assertionId(): string
    {
        return $this->id;
    }

    public function subject(): CrossPackageReference
    {
        return $this->subject;
    }

    public function predicate(): string
    {
        return $this->predicate;
    }

    public function value(): mixed
    {
        return $this->value;
    }

    public function source(): SourceLocator
    {
        return $this->source;
    }

    public function provenance(): ?CrossPackageReference
    {
        return $this->provenance;
    }

    public function occurredAt(): ?DateTimeImmutable
    {
        return $this->occurredAt;
    }

    public function observedAt(): DateTimeImmutable
    {
        return $this->observedAt;
    }

    public function recordedAt(): DateTimeImmutable
    {
        return $this->recordedAt;
    }

    public function tenant(): TenantScope
    {
        return $this->tenant;
    }

    /** @return list<CrossPackageReference> */
    public function evidence(): array
    {
        return $this->evidence;
    }

    public function fingerprint(): string
    {
        return hash('sha256', json_encode([
            'contract' => self::CONTRACT,
            'contract_version' => self::CONTRACT_VERSION,
            'assertion_type' => $this->assertionType()->value,
            'subject' => $this->subject->toArray(),
            'predicate' => $this->predicate,
            'value' => $this->value,
            'source' => self::encodeSource($this->source),
            'occurred_at' => $this->occurredAt === null ? null : self::formatTime($this->occurredAt),
            'tenant' => $this->tenant->toArray(),
        ], JSON_THROW_ON_ERROR));
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'contract' => self::CONTRACT,
            'contract_version' => self::CONTRACT_VERSION,
            'id' => $this->id,
            'assertion_type' => $this->assertionType()->value,
            'subject' => $this->subject->toArray(),
            'predicate' => $this->predicate,
            'value' => $this->value,
            'source' => self::encodeSource($this->source),
            'tenant' => $this->tenant->toArray(),
            'occurred_at' => $this->occurredAt === null ? null : self::formatTime($this->occurredAt),
            'observed_at' => self::formatTime($this->observedAt),
            'recorded_at' => self::formatTime($this->recordedAt),
            'provenance' => $this->provenance?->toArray(),
            'evidence' => array_map(fn (CrossPackageReference $reference): array => $reference->toArray(), $this->evidence),
        ];
    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    /**
     * Decode the provider-neutral portion of a serialized assertion.
     *
     * Subclasses implement their own `fromArray()` because each one knows its own
     * constructor. This helper keeps the envelope validation and the canonical
     * decoding in one place so no subclass reinterprets the wire format. The
     * serialized assertion type must match the decoding subclass.
     *
     * @param  array<string, mixed>  $serialized
     * @return array{id: string, subject: CrossPackageReference, predicate: string, value: mixed, source: SourceLocator, tenant: TenantScope, occurred_at: ?DateTimeImmutable, observed_at: DateTimeImmutable, recorded_at: DateTimeImmutable, provenance: ?CrossPackageReference, evidence: list<CrossPackageReference>}
     */
    protected static function decodeState(array $serialized, AssertionType $expected): array
    {
        if (($serialized['contract'] ?? null) !== self::CONTRACT || ($serialized['contract_version'] ?? null) !== self::CONTRACT_VERSION) {
            throw new InvalidArgumentException('Unsupported historical assertion contract.');
        }

        if (($serialized['assertion_type'] ?? null) !== $expected->value) {
            throw new InvalidArgumentException('Serialized historical assertion type does not match the decoding class.');
        }

        return [
            'id' => self::stringValue($serialized, 'id'),
            'subject' => self::referenceValue($serialized, 'subject'),
            'predicate' => self::stringValue($serialized, 'predicate'),
            'value' => $serialized['value'] ?? null,
            'source' => self::decodeSource($serialized),
            'tenant' => self::tenantValue($serialized),
            'occurred_at' => self::optionalTimeValue($serialized, 'occurred_at'),
            'observed_at' => self::timeValue($serialized, 'observed_at'),
            'recorded_at' => self::timeValue($serialized, 'recorded_at'),
            'provenance' => self::optionalReferenceValue($serialized, 'provenance'),
            'evidence' => self::referenceList($serialized, 'evidence'),
        ];
    }

    /** @return array<string, string> */
    private static function encodeSource(SourceLocator $source): array
    {
        return [
            'source_reference' => $source->sourceReference,
            'source_name' => $source->sourceName,
            'resource_reference' => $source->resourceReference,
        ];
    }

    /** @param array<string, mixed> $serialized */
    private static function decodeSource(array $serialized): SourceLocator
    {
        $source = $serialized['source'] ?? null;
        if (! is_array($source)) {
            throw new InvalidArgumentException('Serialized historical assertions require a source locator object.');
        }

        return new SourceLocator(
            self::stringValue($source, 'source_reference'),
            self::stringValue($source, 'source_name'),
            self::stringValue($source, 'resource_reference'),
        );
    }

    /** @param array<string, mixed> $serialized */
    private static function tenantValue(array $serialized): TenantScope
    {
        $tenant = $serialized['tenant'] ?? null;
        if (! is_array($tenant)) {
            throw new InvalidArgumentException('Serialized historical assertions require a tenant scope object.');
        }

        return TenantScope::fromArray($tenant);
    }

    /** @param array<string, mixed> $serialized */
    private static function referenceValue(array $serialized, string $key): CrossPackageReference
    {
        $reference = $serialized[$key] ?? null;
        if (! is_array($reference)) {
            throw new InvalidArgumentException("Serialized historical assertions require a {$key} reference object.");
        }

        return CrossPackageReference::fromArray($reference);
    }

    /** @param array<string, mixed> $serialized */
    private static function optionalReferenceValue(array $serialized, string $key): ?CrossPackageReference
    {
        $reference = $serialized[$key] ?? null;
        if ($reference === null) {
            return null;
        }

        if (! is_array($reference)) {
            throw new InvalidArgumentException("Historical assertion {$key} must be a cross-package reference or null.");
        }

        return CrossPackageReference::fromArray($reference);
    }

    /**
     * @param  array<string, mixed>  $serialized
     * @return list<CrossPackageReference>
     */
    private static function referenceList(array $serialized, string $key): array
    {
        $references = $serialized[$key] ?? [];
        if (! is_array($references) || ! array_is_list($references)) {
            throw new InvalidArgumentException("Historical assertion {$key} must be a list of cross-package references.");
        }

        return array_map(function (mixed $reference): CrossPackageReference {
            if (! is_array($reference)) {
                throw new InvalidArgumentException('Historical assertion evidence entries must be cross-package reference objects.');
            }

            return CrossPackageReference::fromArray($reference);
        }, $references);
    }

    /** @param array<string, mixed> $serialized */
    private static function stringValue(array $serialized, string $key): string
    {
        $value = $serialized[$key] ?? null;
        if (! is_string($value)) {
            throw new InvalidArgumentException("Serialized historical assertions require a string {$key} value.");
        }

        return $value;
    }

    /** @param array<string, mixed> $serialized */
    private static function timeValue(array $serialized, string $key): DateTimeImmutable
    {
        return new DateTimeImmutable(self::stringValue($serialized, $key));
    }

    /** @param array<string, mixed> $serialized */
    private static function optionalTimeValue(array $serialized, string $key): ?DateTimeImmutable
    {
        $value = $serialized[$key] ?? null;
        if ($value === null) {
            return null;
        }

        return self::timeValue($serialized, $key);
    }

    private static function formatTime(DateTimeImmutable $time): string
    {
        return $time->format('Y-m-d\TH:i:s.uP');
    }

    private static function requireJsonEncodable(mixed $value): void
    {
        if (is_object($value) || is_resource($value)) {
            throw new InvalidArgumentException('Historical assertion values must be JSON-encodable scalars, null, or arrays.');
        }

        if (is_array($value)) {
            foreach ($value as $item) {
                self::requireJsonEncodable($item);
            }
        }

        try {
            json_encode($value, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw new InvalidArgumentException('Historical assertion values must be JSON encodable.');
        }
    }
}
