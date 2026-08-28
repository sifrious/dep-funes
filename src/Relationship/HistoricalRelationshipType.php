<?php

declare(strict_types=1);

namespace Sifrious\Funes\Relationship;

enum HistoricalRelationshipType: string
{
    case Related = 'related';
    case References = 'references';
    case RespondsTo = 'responds-to';
    case Corrects = 'corrects';
    case Supersedes = 'supersedes';
}
