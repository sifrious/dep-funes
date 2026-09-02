<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Sifrious\AuthorizationContract\ActorContext;
use Sifrious\AuthorizationContract\ActorKind;
use Sifrious\AuthorizationContract\AuthorizationContext;
use Sifrious\AuthorizationContract\TenantScope;
use Sifrious\Elwin\NamedInputChannel;
use Sifrious\Elwin\PrimaryAskUserInput;
use Sifrious\Elwin\StringInputPart;
use Sifrious\EventContract\EventEnvelope;
use Sifrious\Funes\Graph\HistoricalAppend;
use Sifrious\Funes\Graph\HistoricalAppendAuthorization;
use Sifrious\Funes\Graph\HistoricalAppender;
use Sifrious\Funes\Graph\HistoricalEntityDraft;
use Sifrious\Funes\Persistence\ObservationStore;
use Sifrious\Funes\Value\EntityKind;
use Sifrious\Funes\Value\ExternalIdentityClaim;
use Sifrious\Funes\Value\ObservationDraft;
use Sifrious\Logres\DeliveryChannel;
use Sifrious\Logres\ExecutionConstraints;
use Sifrious\Logres\ExecutionContext;
use Sifrious\Logres\ExecutionPermissions;
use Sifrious\Logres\ExecutionRequest;
use Sifrious\Logres\ExecutionRequestId;
use Sifrious\ReferenceContract\CrossPackageReference;

uses(RefreshDatabase::class);

it('preserves one actor tenant and delegation context from Elwin through Logres into Funes', function (): void {
    $human = new CrossPackageReference('sifrious/zahir', 'account', 'user-a');
    $authorization = new AuthorizationContext(
        new ActorContext(
            new CrossPackageReference('sifrious/zahir', 'service', 'burdgen'),
            ActorKind::Service,
            actingFor: $human,
            originatingService: new CrossPackageReference('sifrious/burdgen', 'application', 'primary-ask'),
            provenance: new CrossPackageReference('sifrious/zahir', 'delegation', 'delegation-01'),
        ),
        TenantScope::forTenant('organization', new CrossPackageReference('sifrious/zahir', 'organization', 'tenant-a')),
        new CrossPackageReference('sifrious/burdgen', 'request', 'request-01'),
    );
    $input = new PrimaryAskUserInput(
        'input-01',
        'submission-01',
        $authorization,
        new NamedInputChannel('burdgen'),
        [new StringInputPart('part-01', 0, 'Create a tenant-safe plan.')],
        '2026-09-02T12:00:00Z',
    );

    $queuedAuthorization = AuthorizationContext::fromArray(json_decode(
        json_encode($input->authorizationContext(), JSON_THROW_ON_ERROR),
        true,
        flags: JSON_THROW_ON_ERROR,
    ));
    $request = new ExecutionRequest(
        new ExecutionRequestId('request:01'),
        $input->stringInputParts()[0]->exactText,
        new ExecutionContext('project:burdgen'),
        'A verified tenant-safe plan.',
        [],
        new ExecutionConstraints(300),
        new ExecutionPermissions(false, false, false),
        $queuedAuthorization,
        DeliveryChannel::Web,
    );

    $observation = app(ObservationStore::class)->accept(new ObservationDraft(
        'mme-2072:fixture',
        'MME-2072 authorization handoff',
        'elwin:input-01',
        'elwin:test-adapter',
        'Elwin test adapter',
        'mme-2072:run-01',
        new DateTimeImmutable('2026-09-02T12:00:00+00:00'),
        $input->stringInputParts()[0]->exactText,
    ))->observation;
    $event = new EventEnvelope(
        'mme-2072:event-01',
        'execution.requested',
        'sifrious/logres',
        '1',
        new DateTimeImmutable('2026-09-02T12:00:00+00:00'),
        null,
        new DateTimeImmutable('2026-09-02T12:01:00+00:00'),
        [new CrossPackageReference('sifrious/elwin', 'user-input', $input->id)],
        null,
        'mme-2072-correlation',
        [],
        null,
        ['authorization_context' => $request->authorization?->toArray()],
    );
    app(HistoricalAppender::class)->append(new HistoricalAppend(
        $event,
        new HistoricalAppendAuthorization($request->authorization),
        [new HistoricalEntityDraft('user-input', new ExternalIdentityClaim(EntityKind::UserInput, 'mme-2072:fixture', $input->id, $observation->provenance[0]->id))],
    ));

    $stored = AuthorizationContext::fromArray(json_decode(
        (string) DB::table('funes_graph_appends')->value('authorization_context'),
        true,
        flags: JSON_THROW_ON_ERROR,
    ));

    expect($input->semanticAuthorReference()->equals($human))->toBeTrue()
        ->and($input->submittingActorReference()->id)->toBe('burdgen')
        ->and($request->authorization?->toArray())->toBe($authorization->toArray())
        ->and($stored->toArray())->toBe($authorization->toArray());
});
