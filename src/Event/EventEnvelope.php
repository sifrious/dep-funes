<?php

declare(strict_types=1);

namespace Sifrious\Funes\Event;

use Sifrious\EventContract\EventEnvelope as PortableEventEnvelope;

/**
 * @deprecated Import Sifrious\EventContract\EventEnvelope directly.
 *
 * This adapter preserves the Funes v1 public namespace while the canonical,
 * framework-neutral implementation lives in sifrious/event-contract.
 */
final readonly class EventEnvelope extends PortableEventEnvelope
{
}
