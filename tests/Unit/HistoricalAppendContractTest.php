<?php
declare(strict_types=1);
use Sifrious\Funes\Graph\AssertionType;
use Sifrious\Funes\Graph\HistoricalAppend;
use Sifrious\Funes\Graph\HistoricalEntityDraft;
use Sifrious\Funes\Graph\HistoricalIdentifierDraft;
use Sifrious\Funes\Graph\HistoricalRelationDraft;
it('represents lifecycle lineage without provider-specific foreign keys', function (): void {
    $append = new HistoricalAppend(
        'logres:run:1:completed',
        [new HistoricalEntityDraft('input:1', 'user_input', 'Original thought'), new HistoricalEntityDraft('commit:1', 'commit', 'abc123')],
        [new HistoricalIdentifierDraft('commit:1', 'git.sha', 'abc123')],
        [new HistoricalRelationDraft('commit:1', 'caused_by', 'input:1', 'logres:run:1', AssertionType::Observed)],
    );
    expect($append->relations[0]->predicate)->toBe('caused_by');
    expect($append->identifiers[0]->namespace)->toBe('git.sha');
});
it('requires confidence and evidence for inferred meaning', function (): void {
    new HistoricalRelationDraft('commit:1', 'concerns', 'concept:auth', 'kilgore:analysis:1', AssertionType::Inferred);
})->throws(InvalidArgumentException::class);
