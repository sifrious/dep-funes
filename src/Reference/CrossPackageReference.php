<?php

declare(strict_types=1);

namespace Sifrious\Funes\Reference;

use InvalidArgumentException;
use JsonSerializable;

final readonly class CrossPackageReference implements JsonSerializable
{
    public const CONTRACT = 'sifrious.cross-package-reference';

    public const CONTRACT_VERSION = 1;

    public function __construct(
        public string $owner,
        public string $type,
        public string $id,
        public ?string $objectVersion = null,
        public ?self $provenance = null,
    ) {
        if (preg_match('/^[a-z0-9][a-z0-9._-]*\/[a-z0-9][a-z0-9._-]*$/', $owner) !== 1) {
            throw new InvalidArgumentException('Reference owners must be stable package names.');
        }

        if (preg_match('/^[a-z][a-z0-9._-]*$/', $type) !== 1) {
            throw new InvalidArgumentException('Reference types must be stable lowercase identifiers.');
        }

        if ($id === '' || trim($id) !== $id || preg_match('/\s/', $id) === 1) {
            throw new InvalidArgumentException('Reference identifiers must be non-empty opaque values without whitespace.');
        }

        if ($objectVersion !== null && ($objectVersion === '' || trim($objectVersion) !== $objectVersion)) {
            throw new InvalidArgumentException('Reference object versions must be non-empty when supplied.');
        }
    }

    public function equals(self $other): bool
    {
        return $this->toArray() === $other->toArray();
    }

    public function key(): string
    {
        return hash('sha256', json_encode($this->toArray(), JSON_THROW_ON_ERROR));
    }

    /**
     * @return array{contract: string, contract_version: int, owner: string, type: string, id: string, object_version: ?string, provenance: ?array<string, mixed>}
     */
    public function toArray(): array
    {
        return [
            'contract' => self::CONTRACT,
            'contract_version' => self::CONTRACT_VERSION,
            'owner' => $this->owner,
            'type' => $this->type,
            'id' => $this->id,
            'object_version' => $this->objectVersion,
            'provenance' => $this->provenance?->toArray(),
        ];
    }

    /**
     * @param  array<string, mixed>  $serialized
     */
    public static function fromArray(array $serialized): self
    {
        if (($serialized['contract'] ?? null) !== self::CONTRACT || ($serialized['contract_version'] ?? null) !== self::CONTRACT_VERSION) {
            throw new InvalidArgumentException('Unsupported cross-package reference contract.');
        }

        $owner = $serialized['owner'] ?? null;
        $type = $serialized['type'] ?? null;
        $id = $serialized['id'] ?? null;
        $objectVersion = $serialized['object_version'] ?? null;
        $provenance = $serialized['provenance'] ?? null;

        if (! is_string($owner) || ! is_string($type) || ! is_string($id)) {
            throw new InvalidArgumentException('Serialized references require string owner, type, and id values.');
        }

        if ($objectVersion !== null && ! is_string($objectVersion)) {
            throw new InvalidArgumentException('Serialized object versions must be strings or null.');
        }

        if ($provenance !== null && ! is_array($provenance)) {
            throw new InvalidArgumentException('Serialized provenance references must be objects or null.');
        }

        return new self(
            $owner,
            $type,
            $id,
            $objectVersion,
            $provenance === null ? null : self::fromArray($provenance),
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
