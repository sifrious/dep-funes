<?php

declare(strict_types=1);

namespace Sifrious\Funes\Value;

enum ExtractionDisposition: string
{
    case Succeeded = 'succeeded';
    case Unsupported = 'unsupported';
    case Failed = 'failed';
}
