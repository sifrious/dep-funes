<?php

declare(strict_types=1);

namespace Sifrious\Funes\Acceptance;

use Sifrious\Funes\Value\Observation;
use Sifrious\Funes\Value\ObservationDisposition;

final readonly class AcceptanceResult
{
    /**
     * @param  list<string>  $errors
     */
    private function __construct(
        public string $idempotencyKey,
        public AcceptanceOutcome $outcome,
        public ?string $acceptedType,
        public ?string $acceptedId,
        public ?ObservationDisposition $disposition,
        public ?Observation $observation,
        public array $errors,
    ) {}

    public static function accepted(
        string $key,
        string $acceptedId,
        ObservationDisposition $disposition,
        Observation $observation,
    ): self {
        return new self($key, AcceptanceOutcome::Accepted, 'observation', $acceptedId, $disposition, $observation, []);
    }

    public static function replayed(string $key, string $acceptedType, string $acceptedId): self
    {
        return new self($key, AcceptanceOutcome::Replayed, $acceptedType, $acceptedId, null, null, []);
    }

    public static function inFlight(string $key): self
    {
        return new self($key, AcceptanceOutcome::InFlight, null, null, null, null, [
            'Another submission holds this idempotency key and has not completed.',
        ]);
    }

    /**
     * @param  list<string>  $errors
     */
    public static function rejected(string $key, array $errors): self
    {
        return new self($key, AcceptanceOutcome::Rejected, null, null, null, null, $errors);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'idempotency_key' => $this->idempotencyKey,
            'outcome' => $this->outcome->value,
            'accepted_type' => $this->acceptedType,
            'accepted_id' => $this->acceptedId,
            'disposition' => $this->disposition?->value,
            'errors' => $this->errors,
        ];
    }
}
