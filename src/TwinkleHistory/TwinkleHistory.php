<?php
declare(strict_types=1);
namespace Sifrious\Funes\TwinkleHistory;
use Sifrious\Funes\Event\EventEnvelope;
use Sifrious\Funes\Reference\CrossPackageReference;
interface TwinkleHistory
{
    public function accept(EventEnvelope $event): bool;
    /** @return list<EventEnvelope> */
    public function forTwinkle(CrossPackageReference $twinkle): array;
}
