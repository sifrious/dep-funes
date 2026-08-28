<?php

declare(strict_types=1);

namespace Sifrious\Funes\Value;

enum HistoricalRecordType: string
{
    case Observed = 'observed';
    case Derived = 'derived';
}
