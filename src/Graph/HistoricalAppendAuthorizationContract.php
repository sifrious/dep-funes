<?php

declare(strict_types=1);

namespace Sifrious\Funes\Graph;

use JsonSerializable;

interface HistoricalAppendAuthorizationContract extends JsonSerializable
{
    public function actorReference(): string;

    public function tenantReference(): string;

    /** @return array<string, string> */
    public function toArray(): array;
}
