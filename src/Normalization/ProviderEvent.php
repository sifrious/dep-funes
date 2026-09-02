<?php

declare(strict_types=1);

namespace Sifrious\Funes\Normalization;

use DateTimeImmutable;
use InvalidArgumentException;
use JsonException;
use Sifrious\Funes\Event\EventStreamPosition;
use Sifrious\ReferenceContract\CrossPackageReference;

final readonly class ProviderEvent
{
    /**
     * @param  list<CrossPackageReference>  $subjects
     * @param  list<CrossPackageReference>  $provenance
     * @param  array<string, mixed>  $rawPayload
     */
    public function __construct(
        public string $provider,
        public string $id,
        public string $type,
        public string $producer,
        public DateTimeImmutable $occurredAt,
        public ?DateTimeImmutable $observedAt,
        public DateTimeImmutable $recordedAt,
        public array $subjects,
        public array $provenance,
        public array $rawPayload,
        public ?string $causationId = null,
        public ?string $correlationId = null,
        public ?EventStreamPosition $streamPosition = null,
    ) {
        foreach (['provider' => $provider, 'type' => $type] as $label => $value) {
            if (preg_match('/^[a-z][a-z0-9._-]*$/', $value) !== 1) {
                throw new InvalidArgumentException("Provider event {$label} values must be stable lowercase identifiers.");
            }
        }

        if ($id === '' || trim($id) !== $id || preg_match('/\s/', $id) === 1) {
            throw new InvalidArgumentException('Provider event ids must be non-empty opaque values without whitespace.');
        }

        if (preg_match('/^[a-z0-9][a-z0-9._-]*\/[a-z0-9][a-z0-9._-]*$/', $producer) !== 1) {
            throw new InvalidArgumentException('Provider event producers must be stable package names.');
        }

        if ($subjects === []) {
            throw new InvalidArgumentException('Provider events require at least one subject.');
        }

        try {
            json_encode($rawPayload, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw new InvalidArgumentException('Provider event payloads must be JSON encodable.');
        }
    }
}
