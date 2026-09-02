<?php

declare(strict_types=1);

namespace Sifrious\Funes\Graph;

use Sifrious\AuthorizationContract\AuthorizationContext;

abstract readonly class AbstractHistoricalAppendAuthorization implements HistoricalAppendAuthorizationContract
{
    public function __construct(private AuthorizationContext $authorization) {}

    public function authorizationContext(): AuthorizationContext
    {
        return $this->authorization;
    }

    public function toArray(): array
    {
        return $this->authorization->toArray();
    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
