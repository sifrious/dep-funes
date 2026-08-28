<?php

declare(strict_types=1);

namespace Sifrious\Funes\Association;

enum EntityAssociationRole: string
{
    case Subject = 'subject';
    case Actor = 'actor';
    case Context = 'context';
    case Artifact = 'artifact';
    case Target = 'target';
}
