<?php

declare(strict_types=1);

namespace Sifrious\Funes\Event;

use DateTimeImmutable;
use InvalidArgumentException;
use JsonException;
use JsonSerializable;
use Sifrious\Funes\Reference\CrossPackageReference;

final readonly class EventEnvelope implements JsonSerializable
{
    public const CONTRACT = 'sifrious.cross-package-event';

    public const CONTRACT_VERSION = 1;

    /**
     * @param  list<CrossPackageReference>  $subjects
     * @param  list<CrossPackageReference>  $provenance
     * @param  array<string, mixed>|null  $sourceMetadata
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public string $id,
        public string $type,
        public string $producer,
        public string $eventVersion,
        public DateTimeImmutable $occurredAt,
        public ?DateTimeImmutable $observedAt,
        public DateTimeImmutable $recordedAt,
        public array $subjects,
        public ?string $causationId,
        public ?string $correlationId,
        public array $provenance,
        public ?array $sourceMetadata,
        public array $payload,
        public ?EventStreamPosition $streamPosition = null,
    ) {
        self::requireOpaqueId($id, 'Event ids');
        self::requirePackageName($producer);

        if (preg_match('/^[a-z][a-z0-9._-]*$/', $type) !== 1) {
            throw new InvalidArgumentException('Event types must be stable lowercase identifiers.');
        }

        if ($eventVersion === '' || trim($eventVersion) !== $eventVersion || preg_match('/\s/', $eventVersion) === 1) {
            throw new InvalidArgumentException('Event versions must be non-empty values without whitespace.');
        }

        if ($subjects === []) {
            throw new InvalidArgumentException('Events require a non-empty subject reference list.');
        }

        if ($occurredAt > $recordedAt || ($observedAt !== null && ($occurredAt > $observedAt || $observedAt > $recordedAt))) {
            throw new InvalidArgumentException('Event occurrence, observation, and recording times must be chronological.');
        }

        if ($causationId !== null) {
            self::requireOpaqueId($causationId, 'Causation ids');
        }

        if ($correlationId !== null) {
            self::requireOpaqueId($correlationId, 'Correlation ids');
        }

        if ($sourceMetadata !== null) {
            self::requireJsonObject($sourceMetadata, 'Source metadata');
        }
        self::requireJsonObject($payload, 'Event payloads');
    }

    public function fingerprint(): string
    {
        return hash('sha256', json_encode($this->toArray(), JSON_THROW_ON_ERROR));
    }

    public function idempotencyKey(): string
    {
        return $this->id;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'contract' => self::CONTRACT,
            'contract_version' => self::CONTRACT_VERSION,
            'id' => $this->id,
            'type' => $this->type,
            'producer' => $this->producer,
            'event_version' => $this->eventVersion,
            'occurred_at' => self::formatTime($this->occurredAt),
            'observed_at' => $this->observedAt === null ? null : self::formatTime($this->observedAt),
            'recorded_at' => self::formatTime($this->recordedAt),
            'subjects' => array_map(
                fn (CrossPackageReference $reference): array => $reference->toArray(),
                $this->subjects,
            ),
            'causation_id' => $this->causationId,
            'correlation_id' => $this->correlationId,
            'provenance' => array_map(
                fn (CrossPackageReference $reference): array => $reference->toArray(),
                $this->provenance,
            ),
            'source_metadata' => $this->sourceMetadata,
            'payload' => $this->payload,
            'stream_position' => $this->streamPosition?->toArray(),
        ];
    }

    /**
     * @param  array<string, mixed>  $serialized
     */
    public static function fromArray(array $serialized): self
    {
        if (($serialized['contract'] ?? null) !== self::CONTRACT || ($serialized['contract_version'] ?? null) !== self::CONTRACT_VERSION) {
            throw new InvalidArgumentException('Unsupported cross-package event contract.');
        }

        $id = self::stringValue($serialized, 'id');
        $type = self::stringValue($serialized, 'type');
        $producer = self::stringValue($serialized, 'producer');
        $eventVersion = self::stringValue($serialized, 'event_version');
        $occurredAt = self::timeValue($serialized, 'occurred_at');
        $observedAt = self::optionalTimeValue($serialized, 'observed_at');
        $recordedAt = self::timeValue($serialized, 'recorded_at');
        $subjects = self::referenceList($serialized, 'subjects');
        $causationId = self::optionalStringValue($serialized, 'causation_id');
        $correlationId = self::optionalStringValue($serialized, 'correlation_id');
        $provenance = self::referenceList($serialized, 'provenance');
        $sourceMetadata = self::optionalObjectValue($serialized, 'source_metadata');
        $payload = self::objectValue($serialized, 'payload');
        $streamPosition = $serialized['stream_position'] ?? null;

        if ($streamPosition !== null && ! is_array($streamPosition)) {
            throw new InvalidArgumentException('Serialized event stream positions must be objects or null.');
        }

        return new self(
            $id,
            $type,
            $producer,
            $eventVersion,
            $occurredAt,
            $observedAt,
            $recordedAt,
            $subjects,
            $causationId,
            $correlationId,
            $provenance,
            $sourceMetadata,
            $payload,
            $streamPosition === null ? null : EventStreamPosition::fromArray($streamPosition),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    private static function requirePackageName(string $package): void
    {
        if (preg_match('/^[a-z0-9][a-z0-9._-]*\/[a-z0-9][a-z0-9._-]*$/', $package) !== 1) {
            throw new InvalidArgumentException('Event producers must be stable package names.');
        }
    }

    private static function requireOpaqueId(string $id, string $label): void
    {
        if ($id === '' || trim($id) !== $id || preg_match('/\s/', $id) === 1) {
            throw new InvalidArgumentException($label.' must be non-empty opaque values without whitespace.');
        }
    }

    /**
     * @param  array<string, mixed>  $value
     */
    private static function requireJsonObject(array $value, string $label): void
    {
        try {
            json_encode($value, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw new InvalidArgumentException($label.' must be JSON encodable.');
        }
    }

    private static function formatTime(DateTimeImmutable $time): string
    {
        return $time->format('Y-m-d\TH:i:s.uP');
    }

    /**
     * @param  array<string, mixed>  $serialized
     */
    private static function stringValue(array $serialized, string $key): string
    {
        $value = $serialized[$key] ?? null;

        if (! is_string($value)) {
            throw new InvalidArgumentException("Serialized events require a string {$key} value.");
        }

        return $value;
    }

    /**
     * @param  array<string, mixed>  $serialized
     */
    private static function optionalStringValue(array $serialized, string $key): ?string
    {
        $value = $serialized[$key] ?? null;

        if ($value !== null && ! is_string($value)) {
            throw new InvalidArgumentException("Serialized event {$key} values must be strings or null.");
        }

        return $value;
    }

    /**
     * @param  array<string, mixed>  $serialized
     */
    private static function timeValue(array $serialized, string $key): DateTimeImmutable
    {
        return new DateTimeImmutable(self::stringValue($serialized, $key));
    }

    /**
     * @param  array<string, mixed>  $serialized
     */
    private static function optionalTimeValue(array $serialized, string $key): ?DateTimeImmutable
    {
        $value = self::optionalStringValue($serialized, $key);

        return $value === null ? null : new DateTimeImmutable($value);
    }

    /**
     * @param  array<string, mixed>  $serialized
     * @return list<CrossPackageReference>
     */
    private static function referenceList(array $serialized, string $key): array
    {
        $values = $serialized[$key] ?? null;

        if (! is_array($values) || ! array_is_list($values)) {
            throw new InvalidArgumentException("Serialized event {$key} values must be reference lists.");
        }

        return array_map(function (mixed $value) use ($key): CrossPackageReference {
            if (! is_array($value)) {
                throw new InvalidArgumentException("Serialized event {$key} values must be references.");
            }

            return CrossPackageReference::fromArray($value);
        }, $values);
    }

    /**
     * @param  array<string, mixed>  $serialized
     * @return array<string, mixed>
     */
    private static function objectValue(array $serialized, string $key): array
    {
        $value = $serialized[$key] ?? null;

        if (! is_array($value) || (array_is_list($value) && $value !== [])) {
            throw new InvalidArgumentException("Serialized event {$key} values must be objects.");
        }

        return $value;
    }

    /**
     * @param  array<string, mixed>  $serialized
     * @return array<string, mixed>|null
     */
    private static function optionalObjectValue(array $serialized, string $key): ?array
    {
        $value = $serialized[$key] ?? null;

        if ($value === null) {
            return null;
        }

        return self::objectValue($serialized, $key);
    }
}
