<?php

declare(strict_types=1);

namespace Sifrious\Funes\Acceptance;

interface AcceptanceGateway
{
    public function accept(Submission $submission): AcceptanceResult;

    /**
     * @param  list<Submission>  $submissions
     * @return list<AcceptanceResult>
     */
    public function acceptBatch(array $submissions): array;
}
