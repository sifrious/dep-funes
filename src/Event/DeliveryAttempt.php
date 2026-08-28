<?php

declare(strict_types=1);

namespace Sifrious\Funes\Event;

use DateTimeImmutable;
use InvalidArgumentException;
use JsonSerializable;
use Sifrious\Funes\Reference\CrossPackageReference;

final readonly class DeliveryAttempt implements JsonSerializable
{
    public const CONTRACT = 'sifrious.cross-package-event-delivery';

    public const CONTRACT_VERSION = 1;

    public function __construct(
        public string $id,
        public string $eventId,
        public string $eventFingerprint,
        public string $consumer,
        public int $attempt,
        public DateTimeImmutable $attemptedAt,
        public DeliveryStatus $status,
        public ?string $failureCode = null,
        public ?DateTimeImmutable $retryAt = null,
        public ?CrossPackageReference $deadLetter = null,
    ) {
        foreach (['Delivery attempt ids' => $id, 'Event ids' => $eventId, 'Event fingerprints' => $eventFingerprint] as $label => $value) {
            if ($value === '' || trim($value) !== $value || preg_match('/\s/', $value) === 1) {
                throw new InvalidArgumentException($label.' must be non-empty values without whitespace.');
            }
        }

        if (preg_match('/^[a-z0-9][a-z0-9._-]*\/[a-z0-9][a-z0-9._-]*$/', $consumer) !== 1) {
            throw new InvalidArgumentException('Delivery consumers must be stable package names.');
        }

        if ($attempt < 1) {
            throw new InvalidArgumentException('Delivery attempt numbers must be positive integers.');
        }

        if ($failureCode !== null && ($failureCode === '' || trim($failureCode) !== $failureCode || preg_match('/\s/', $failureCode) === 1)) {
            throw new InvalidArgumentException('Delivery failure codes must be non-empty values without whitespace.');
        }

        $valid = match ($status) {
            DeliveryStatus::Started, DeliveryStatus::Succeeded => $failureCode === null && $retryAt === null && $deadLetter === null,
            DeliveryStatus::RetryableFailure => $failureCode !== null && $retryAt !== null && $retryAt >= $attemptedAt && $deadLetter === null,
            DeliveryStatus::DeadLettered => $failureCode !== null && $retryAt === null && $deadLetter !== null,
        };

        if (! $valid) {
            throw new InvalidArgumentException('Delivery metadata does not match its status.');
        }
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
            'event_id' => $this->eventId,
            'event_fingerprint' => $this->eventFingerprint,
            'consumer' => $this->consumer,
            'attempt' => $this->attempt,
            'attempted_at' => $this->attemptedAt->format('Y-m-d\TH:i:s.uP'),
            'status' => $this->status->value,
            'failure_code' => $this->failureCode,
            'retry_at' => $this->retryAt?->format('Y-m-d\TH:i:s.uP'),
            'dead_letter' => $this->deadLetter?->toArray(),
        ];
    }

    /**
     * @param  array<string, mixed>  $serialized
     */
    public static function fromArray(array $serialized): self
    {
        if (($serialized['contract'] ?? null) !== self::CONTRACT || ($serialized['contract_version'] ?? null) !== self::CONTRACT_VERSION) {
            throw new InvalidArgumentException('Unsupported cross-package event delivery contract.');
        }

        $id = $serialized['id'] ?? null;
        $eventId = $serialized['event_id'] ?? null;
        $eventFingerprint = $serialized['event_fingerprint'] ?? null;
        $consumer = $serialized['consumer'] ?? null;
        $attempt = $serialized['attempt'] ?? null;
        $attemptedAt = $serialized['attempted_at'] ?? null;
        $status = $serialized['status'] ?? null;
        $failureCode = $serialized['failure_code'] ?? null;
        $retryAt = $serialized['retry_at'] ?? null;
        $deadLetter = $serialized['dead_letter'] ?? null;

        if (! is_string($id) || ! is_string($eventId) || ! is_string($eventFingerprint) || ! is_string($consumer) || ! is_int($attempt) || ! is_string($attemptedAt) || ! is_string($status)) {
            throw new InvalidArgumentException('Serialized delivery attempts require identity, consumer, attempt, time, and status fields.');
        }

        if ($failureCode !== null && ! is_string($failureCode)) {
            throw new InvalidArgumentException('Serialized delivery failure codes must be strings or null.');
        }

        if ($retryAt !== null && ! is_string($retryAt)) {
            throw new InvalidArgumentException('Serialized delivery retry times must be strings or null.');
        }

        if ($deadLetter !== null && ! is_array($deadLetter)) {
            throw new InvalidArgumentException('Serialized dead-letter references must be objects or null.');
        }

        $deliveryStatus = DeliveryStatus::tryFrom($status);

        if ($deliveryStatus === null) {
            throw new InvalidArgumentException('Serialized delivery attempts require a supported status.');
        }

        return new self(
            $id,
            $eventId,
            $eventFingerprint,
            $consumer,
            $attempt,
            new DateTimeImmutable($attemptedAt),
            $deliveryStatus,
            $failureCode,
            $retryAt === null ? null : new DateTimeImmutable($retryAt),
            $deadLetter === null ? null : CrossPackageReference::fromArray($deadLetter),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
