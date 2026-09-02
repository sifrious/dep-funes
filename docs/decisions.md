# Decisions

## D-001 — Entity references are typed and namespaced

Funes exposes project, identity, repository, organization, and domain as stable `EntityKind`
values. An `EntityReference` combines one kind with a namespaced opaque identifier. It rejects host
database integers and unqualified display names because neither can retain identity across
applications. Funes preserves the identifier without resolving or interpreting its namespace.

## D-002 — Provenance assertions are append-only records

One observation represents one historical effect for a canonical resource and payload. Every
source encounter appends a provenance assertion containing its source locator, producer, temporal
coordinates, and transformation lineage. Exact retries reuse an assertion, while later encounters
of unchanged content add evidence without duplicating the observation.

## D-003 — Source identities resolve deterministically without inferred merging

Entity kind, source reference, and exact external identifier resolve to one stable Funes entity.
Repeated evidence reuses that entity, while later provenance is appended. Identifiers from different
sources remain separate until an explicit, evidence-bearing association is recorded by a later
relationship capability.

## D-004 — Observed and derived records use separate concrete seams

Observations always report the observed historical type. Extraction results always report the
derived type and retain an evidence observation plus a named, versioned derivation process. Funes
does not expose a generic record-acceptance method because it would allow interpretations to enter
the source-observation path.

## D-005 — Producer context combines identity with an ingestion run

Every new observed or derived record requires a stable producer identity and ingestion-run
reference. Repeated runs that produce the same historical effect append producer context without
duplicating that effect. Pre-contract rows receive explicit `funes:legacy-*` surrogate run
references during migration; those values preserve traceability without claiming a recovered
external job identity.

## D-006 — Structured metadata is append-only and versioned outside observation identity

Metadata enters as a namespaced, schema-versioned assertion linked to observation provenance.
Changing metadata does not create or mutate an observation. Exact assertion retries deduplicate;
new versions append. Pre-contract JSON is projected as `funes:legacy` during retrieval instead of
rewriting the original row.

## D-007 — Historical text is authoritative; its projection is disposable

Namespaced text assertions append beside the observation and link to accepting provenance. Textual
raw payloads remain available as source-payload assertions. A separate projection can be deleted
and rebuilt entirely from those authoritative records. Search ranking, tokenization, and engine
selection are not part of this seam.

## D-008 — Cross-package identity travels as a reference, never a foreign model

The durable cross-package representation names an owning package, stable object type, opaque
identifier, optional object version, and optional provenance reference. Display and search copies
are explicitly non-authoritative snapshots. Resolution is batch-only at the shared boundary and
routes to the owner, which retains authorization and returns a complete set of explicit outcomes.
No package uses another package's private tables or model classes as its normal integration seam.

## D-009 — Entity association facts and their evidence have separate identities

An entity association is identified by observation, typed role, and complete cross-package
reference. Its source encounters are separate provenance links. Replaying identical evidence does
not duplicate either record; encountering the same fact through later source provenance appends
evidence without changing the association. Traversal compares the exact durable reference rather
than a display value or foreign table key.

## D-010 — Historical event links are references with evidence, not embedded records

A historical relationship stores its source observation, validated non-causal type, target event
reference, and separate provenance links. Internal Funes observation targets must exist; portable
external event references remain resolvable by their owner. The relation does not copy its target
or infer causal and parent-child semantics that the supplied evidence did not state.

## D-011 — Causal and parent-child types require preserved source declarations

`caused-by` and `child-of` cannot be accepted from type and target alone. Each requires a
namespaced source-field locator and the declared source value, linked to the observation provenance
that carried it. Repeated evidence appends declaration assertions without duplicating the relation.
General relatedness, ordering, or adjacency is never promoted into causality.

## D-012 — Event facts and delivery attempts are separate immutable contracts

A cross-package event carries stable identity, producing package, event type and version, temporal
coordinates, durable subject references, causation, correlation, provenance, source metadata, and
payload under one versioned envelope. Its event ID is the consumer idempotency key; its complete
serialized value supplies a fingerprint that exposes conflicting reuse. Delivery attempts name the
event ID and fingerprint but never embed or mutate the event. The baseline is at-least-once delivery,
so consumers persist accepted event IDs and must return the original effect on exact replay.

Ordering exists only as an optional position within a named durable stream. There is no global
sequence and no comparison across streams. Retryable failure, success, and dead-letter outcomes are
new delivery-attempt facts. Funes may preserve an event and its provenance as history, but transport,
retry scheduling, dead-letter operation, and current execution lifecycle remain with Logres or the
other coordinating package.

## D-013 — The historical assertion taxonomy lives in the class, not in a passed-in field

`HistoricalAssertionContract` and `AbstractHistoricalAssertion` establish one provider-neutral claim
type before any provider-family or concrete subclass exists. The ABC owns identity, invariants,
temporal semantics, and the versioned serialization boundary; a subclass supplies only its assertion
type and provider mapping.

`assertionType()` is abstract rather than a constructor argument. Observed, declared, and inferred
are therefore distinct classes, and an observation cannot become an inference by changing a field.
Inferred assertions require non-empty evidence. Confidence is deliberately absent: the design source
assigns `HasConfidence` to relationship drafts, not to this object, and adding it here would be
speculative.

Alternatives considered: a single concrete assertion class carrying a type enum, rejected because it
makes the taxonomy unenforceable and lets provider mapping leak into canonical semantics; and a
generic `fromArray()` on the base using `new static(...)`, rejected because it silently breaks the
moment a subclass constructor takes provider fields. Instead the base exposes a protected
`decodeState()` that validates the envelope and refuses a payload whose serialized type does not
match the decoding class, and each subclass writes its own `fromArray()`.

The fingerprint covers the durable fact and excludes the assertion's own identity and its observation
and recording times, so a re-encounter of the same claim deduplicates while a different tenant, value,
or assertion type does not.

## D-014 — Concerns are stateless capability interfaces, and every one is assigned explicitly

The manifesto's fifteen-concern vocabulary is a register, not a menu. Each provider-neutral
historical object records every concern as composed or as not applicable with its reason and its real
owner, because a missing concern and a deliberately excluded one are otherwise indistinguishable
later. `docs/concerns.md` holds that register and an invariant-ownership table in which no invariant
is enforced in two places.

`HistoricalAssertion` composes `HasStableIdentity`, `HasProvenance`, `HasTemporalCoordinates`,
`HasTenantScope`, and `HasEvidence`. The remaining ten are excluded with named owners: provider
identity belongs to concrete subclasses, actor and authorization context to the acceptance boundary,
effective interval and immutable version to `HistoricalEntityVersion`, parent semantics to
`HistoricalRelationshipAssertion`, external references to the identity registry, content hash to
`fingerprint()` and the stored-artifact seam, and confidence to relationship drafts.

Concerns are interfaces and declare no state, so composition cannot duplicate what the base class
already owns. Shared mechanics are extracted only where they genuinely repeat: the chronology rule
and the temporal wire format live in `SerializesTemporalCoordinates` because every historical
substrate object needs the same three moments to compare and sort against its siblings. Everything
else stays private to the object that uses it.

`HasStableIdentity` exposes `stableIdentity()` rather than `assertionId()`. A concern shared across
seven objects cannot name one of them. `HasSourceLocator` is not composed separately: an assertion's
provenance always includes its source locator, and two concerns exposing the same value could
disagree.
