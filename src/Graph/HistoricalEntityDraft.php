<?php
declare(strict_types=1);
namespace Sifrious\Funes\Graph;
use InvalidArgumentException;
final readonly class HistoricalEntityDraft
{
    public function __construct(public string $reference, public string $type, public string $displayName)
    {
        if (trim($reference) === '' || trim($type) === '' || trim($displayName) === '') {
            throw new InvalidArgumentException('Historical entity reference, type, and display name are required.');
        }
    }
}
