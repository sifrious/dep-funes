<?php

declare(strict_types=1);

namespace Sifrious\Funes\Event;

use InvalidArgumentException;
use JsonSerializable;
use Sifrious\Funes\Reference\CrossPackageReference;

final readonly class EventStreamPosition implements JsonSerializable
{
    public function __construct(
        public CrossPackageReference $stream,
        public int $sequence,
    ) {
        if ($sequence < 1) {
            throw new InvalidArgumentException('Event stream sequences must be positive integers.');
        }
    }

    /**
     * @return array{stream: array<string, mixed>, sequence: int}
     */
    public function toArray(): array
    {
        return [
            'stream' => $this->stream->toArray(),
            'sequence' => $this->sequence,
        ];
    }

    /**
     * @param  array<string, mixed>  $serialized
     */
    public static function fromArray(array $serialized): self
    {
        $stream = $serialized['stream'] ?? null;
        $sequence = $serialized['sequence'] ?? null;

        if (! is_array($stream) || ! is_int($sequence)) {
            throw new InvalidArgumentException('Serialized stream positions require a stream reference and integer sequence.');
        }

        return new self(CrossPackageReference::fromArray($stream), $sequence);
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
