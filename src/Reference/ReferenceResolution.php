<?php

declare(strict_types=1);

namespace Sifrious\Funes\Reference;

use InvalidArgumentException;

final readonly class ReferenceResolution
{
    private function __construct(
        public CrossPackageReference $reference,
        public ReferenceResolutionStatus $status,
        public ?ReferenceSnapshot $snapshot = null,
        public ?CrossPackageReference $supersededBy = null,
    ) {}

    public static function available(ReferenceSnapshot $snapshot): self
    {
        return new self($snapshot->reference, ReferenceResolutionStatus::Available, $snapshot);
    }

    public static function unavailable(CrossPackageReference $reference): self
    {
        return new self($reference, ReferenceResolutionStatus::Unavailable);
    }

    public static function tombstoned(CrossPackageReference $reference, ?ReferenceSnapshot $snapshot = null): self
    {
        self::assertSnapshotIdentity($reference, $snapshot);

        return new self($reference, ReferenceResolutionStatus::Tombstoned, $snapshot);
    }

    public static function superseded(
        CrossPackageReference $reference,
        CrossPackageReference $supersededBy,
        ?ReferenceSnapshot $snapshot = null,
    ): self {
        self::assertSnapshotIdentity($reference, $snapshot);

        return new self($reference, ReferenceResolutionStatus::Superseded, $snapshot, $supersededBy);
    }

    public static function unauthorized(CrossPackageReference $reference): self
    {
        return new self($reference, ReferenceResolutionStatus::Unauthorized);
    }

    private static function assertSnapshotIdentity(
        CrossPackageReference $reference,
        ?ReferenceSnapshot $snapshot,
    ): void {
        if ($snapshot !== null && ! $snapshot->reference->equals($reference)) {
            throw new InvalidArgumentException('Reference snapshots must describe the resolved durable reference.');
        }
    }
}
