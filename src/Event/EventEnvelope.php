<?php

declare(strict_types=1);

namespace Sifrious\Funes\Event;

/**
 * @deprecated Import Sifrious\EventContract\EventEnvelope directly.
 *
 * This alias preserves the Funes v1 public namespace while the canonical,
 * framework-neutral implementation lives in sifrious/event-contract.
 */
class_alias(\Sifrious\EventContract\EventEnvelope::class, EventEnvelope::class);
