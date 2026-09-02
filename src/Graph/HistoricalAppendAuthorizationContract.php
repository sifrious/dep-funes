<?php

declare(strict_types=1);

namespace Sifrious\Funes\Graph;

use JsonSerializable;
use Sifrious\AuthorizationContract\AuthorizationContext;

interface HistoricalAppendAuthorizationContract extends JsonSerializable
{
    public function authorizationContext(): AuthorizationContext;

    /** @return array<string, mixed> */
    public function toArray(): array;
}
