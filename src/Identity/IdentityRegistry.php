<?php

declare(strict_types=1);

namespace Sifrious\Funes\Identity;

use Sifrious\Funes\Value\EntityKind;
use Sifrious\Funes\Value\EntityReference;
use Sifrious\Funes\Value\ExternalIdentityClaim;
use Sifrious\Funes\Value\StableEntity;

interface IdentityRegistry
{
    public function resolve(ExternalIdentityClaim $claim): StableEntity;

    public function attach(EntityReference $entity, ExternalIdentityClaim $claim): StableEntity;

    public function get(EntityReference $reference): ?StableEntity;

    public function find(EntityKind $kind, string $sourceReference, string $externalIdentifier): ?StableEntity;
}
