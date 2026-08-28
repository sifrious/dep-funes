<?php

declare(strict_types=1);

namespace Sifrious\Funes\Normalization;

use InvalidArgumentException;
use Sifrious\Funes\Event\EventEnvelope;

final readonly class ProviderEventNormalizer
{
    /**
     * @param  list<NormalizationRule>  $rules
     */
    public function __construct(private array $rules)
    {
        $keys = [];

        foreach ($rules as $rule) {
            $key = $rule->provider."\0".$rule->providerType;

            if (isset($keys[$key])) {
                throw new InvalidArgumentException('Normalization rules must be unique by provider and provider type.');
            }

            $keys[$key] = true;
        }
    }

    public function normalize(ProviderEvent $event): EventEnvelope
    {
        $rule = $this->ruleFor($event);

        return new EventEnvelope(
            'normalized_'.hash('sha256', $event->provider."\0".$event->id),
            $rule->normalizedType,
            $event->producer,
            $rule->version,
            $event->occurredAt,
            $event->observedAt,
            $event->recordedAt,
            $event->subjects,
            $event->causationId,
            $event->correlationId,
            $event->provenance,
            [
                'provider' => $event->provider,
                'provider_event_id' => $event->id,
                'provider_event_type' => $event->type,
                'normalization_version' => $rule->version,
            ],
            $event->rawPayload,
            $event->streamPosition,
        );
    }

    private function ruleFor(ProviderEvent $event): NormalizationRule
    {
        foreach ($this->rules as $rule) {
            if ($rule->supports($event)) {
                return $rule;
            }
        }

        throw new UnsupportedProviderEvent("Unsupported provider event {$event->provider}:{$event->type}.");
    }
}
