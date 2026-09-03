<?php

declare(strict_types=1);

namespace Sifrious\Funes\Concern;

use Sifrious\AuthorizationContract\TenantScope;

/**
 * A historical record that belongs to exactly one evidence boundary.
 *
 * The tenant travels with the durable fact rather than being inferred at read time,
 * which is what lets retrieval filter, retention target, and erasure scope a tenant's
 * evidence without consulting the source system. It is not an authorization decision:
 * the caller's authorization context belongs to the acceptance boundary, not the fact.
 */
interface HasTenantScope
{
    /** The tenant whose evidence this record belongs to. */
    public function tenant(): TenantScope;
}
