<?php

declare(strict_types=1);

namespace Sifrious\Funes\Graph;

use InvalidArgumentException;

abstract readonly class AbstractHistoricalAppendAuthorization implements HistoricalAppendAuthorizationContract
{
    public function __construct(private string $actor, private string $tenant)
    {
        if (trim($actor) === '' || trim($tenant) === '') {
            throw new InvalidArgumentException('Historical appends require actor and tenant authorization references.');
        }
    }

    public function actorReference(): string
    {
        return $this->actor;
    }

    public function tenantReference(): string
    {
        return $this->tenant;
    }

    public function toArray(): array
    {
        return ['actor_reference' => $this->actor, 'tenant_reference' => $this->tenant];
    }

    /** @return array<string, string> */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
