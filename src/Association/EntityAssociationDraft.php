<?php

declare(strict_types=1);

namespace Sifrious\Funes\Association;

use Sifrious\Funes\Reference\CrossPackageReference;

final readonly class EntityAssociationDraft
{
    public function __construct(
        public EntityAssociationRole $role,
        public CrossPackageReference $entity,
    ) {}
}
