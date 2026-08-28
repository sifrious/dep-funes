<?php

declare(strict_types=1);

namespace Sifrious\Funes\Value;

enum EntityKind: string
{
    case Project = 'project';
    case Site = 'site';
    case Identity = 'identity';
    case Repository = 'repository';
    case Organization = 'organization';
    case Domain = 'domain';
}
