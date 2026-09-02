<?php

declare(strict_types=1);
use Sifrious\Funes\Graph\AssertionType;
use Sifrious\Funes\Graph\HistoricalRelationDraft;

it('requires confidence and evidence for inferred meaning', function (): void {
    new HistoricalRelationDraft('commit', 'concerns', 'plan', 'kilgore:analysis:1', AssertionType::Inferred);
})->throws(InvalidArgumentException::class);
