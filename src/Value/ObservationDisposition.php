<?php

declare(strict_types=1);

namespace Sifrious\Funes\Value;

enum ObservationDisposition: string
{
    case First = 'first';
    case Unchanged = 'unchanged';
    case Changed = 'changed';
}
