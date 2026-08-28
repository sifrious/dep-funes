<?php

declare(strict_types=1);

namespace Sifrious\Funes\Normalization;

use InvalidArgumentException;

final readonly class NormalizationRule
{
    public function __construct(
        public string $provider,
        public string $providerType,
        public string $normalizedType,
        public string $version,
    ) {
        foreach (['provider' => $provider, 'provider type' => $providerType, 'normalized type' => $normalizedType] as $label => $value) {
            if (preg_match('/^[a-z][a-z0-9._-]*$/', $value) !== 1) {
                throw new InvalidArgumentException("Normalization {$label} values must be stable lowercase identifiers.");
            }
        }

        if ($version === '' || trim($version) !== $version || preg_match('/\s/', $version) === 1) {
            throw new InvalidArgumentException('Normalization versions must be non-empty values without whitespace.');
        }
    }

    public function supports(ProviderEvent $event): bool
    {
        return $this->provider === $event->provider && $this->providerType === $event->type;
    }
}
