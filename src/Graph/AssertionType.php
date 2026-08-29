<?php
declare(strict_types=1);
namespace Sifrious\Funes\Graph;
enum AssertionType: string
{
    case Observed = 'observed';
    case Declared = 'declared';
    case Inferred = 'inferred';
}
