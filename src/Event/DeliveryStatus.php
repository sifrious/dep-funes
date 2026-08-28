<?php

declare(strict_types=1);

namespace Sifrious\Funes\Event;

enum DeliveryStatus: string
{
    case Started = 'started';
    case Succeeded = 'succeeded';
    case RetryableFailure = 'retryable-failure';
    case DeadLettered = 'dead-lettered';
}
