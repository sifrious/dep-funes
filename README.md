# Funes

> **License:** Copyright © 2026 Sifrious. All rights reserved. This is
> publicly viewable proprietary software, not open-source software. See
> [LICENSE.md](LICENSE.md).

Funes is named after Ireneo Funes, the central figure in Jorge Luis Borges's *Funes the Memorious*. After an accident, Funes remembers every detail of everything he experiences. The name fits a package built to preserve history without flattening it, losing its source, or forgetting how one record relates to another.

Unlike its namesake, the package is not intended to retain information indiscriminately. Its purpose is to make selected history trustworthy, structured, searchable, and useful.

## The problem

Applications accumulate history in incompatible forms:

- conversations are stored as provider-specific transcripts;
- commands and tool calls disappear into logs;
- files and generated artifacts become detached from the work that produced them;
- repository, project-management, and communication activity live in separate systems;
- retries create duplicates;
- updates overwrite earlier observations;
- summaries become separated from their supporting evidence;
- timestamps fail to distinguish when something happened from when it was discovered;
- search results return text without enough provenance to trust or cite it.

This leaves applications with plenty of data but weak memory. Reconstructing what happened requires knowledge of every source system, and later interpretations can easily be mistaken for original facts.

Funes provides a common historical substrate for solving that problem.

## What Funes preserves

Funes is designed to record historical events and the entities involved in them. A record can describe activity involving applications, repositories, users, agents, conversations, files, tasks, tools, or external systems.

Each historical record can preserve:

- a stable identity;
- a provider-independent type;
- the original source and a link back to it;
- when the event occurred;
- when it was observed;
- when it was ingested;
- raw source material or a reference to it;
- normalized text and structured metadata;
- the process that produced the record;
- relationships to other records and entities.

The distinction between occurrence, observation, and ingestion time matters. An event may happen on Monday, be discovered during a synchronization on Wednesday, and enter a local history store on Thursday. Collapsing those moments into one timestamp makes historical reconstruction unreliable.

## Provenance before convenience

Funes treats provenance as part of the record, not optional debugging metadata.

Normalized representations remain connected to their original source material. Derived records such as classifications or summaries remain distinguishable from observed facts and retain references to the evidence used to produce them. When a source is corrected, Funes can preserve the correction without silently rewriting the earlier observation.

The executable record seam uses `HistoricalRecordType::Observed` for `Observation` and `HistoricalRecordType::Derived` for `ExtractionResult`. A derived result always names the evidence observation and exposes a `DerivationProcess` with a non-empty name and version. Observation acceptance accepts only `ObservationDraft`; a derived result cannot be passed through that API as if it came from a source.

This makes it possible to answer not only “What does the history say?” but also:

- Where did this come from?
- Which process observed it?
- Has the source changed since it was first seen?
- Is this an original observation or a later interpretation?
- What other records support or contradict it?

## Safe repeated ingestion

Historical collection is rarely a one-time operation. Imports are retried, source records are encountered through multiple paths, and remote systems return overlapping pages of results.

Funes is intended to support idempotent ingestion and provenance-preserving deduplication. Repeating the same ingestion should not create a second historical effect. Encountering the same material through a genuinely different source should preserve that additional provenance rather than discard it.

Changed source material should become a new observation related to what was previously known. It should not erase the earlier record merely because the current version differs.

## Relationships and identity

History is more useful as a connected record than as a collection of isolated documents.

Funes models stable entities separately from the external identities assigned by individual systems. This allows records from different sources to refer to the same project, repository, person, agent, conversation, file, or task without adopting one provider's identifier as the universal identity.

Portable callers use `EntityReference` with a fixed `EntityKind` and a namespaced opaque identifier such as `github:R_kgDOExample`. Numeric host database IDs and unqualified display names are rejected at the boundary.

Cross-package callers use `CrossPackageReference`, whose versioned serialized form contains the owning package, stable object type, opaque identifier, optional object version, and optional provenance reference. A Funes-backed reference can name a Funes provenance assertion without making Funes the owner of the referenced package's current entity. The serialized value is safe to persist or place in queues, events, and API payloads; equality uses the complete durable representation.

`ReferenceSnapshot` is a copied label and attribute document for display or search. It never replaces the durable reference and is not canonical state. Historical consumers may retain a snapshot when an owner later reports the reference as tombstoned or superseded.

Resolution occurs only through the owning package's `ReferenceOwnerResolver`. The shared `ReferenceDirectory` groups a mixed batch by owner and calls each owner once. Owners receive the caller's `ReferenceAccess`, enforce their own authorization, and return an explicit `available`, `unavailable`, `tombstoned`, `superseded`, or `unauthorized` outcome for every reference. Missing or extra outcomes fail the batch rather than becoming `null`. Unknown owners resolve explicitly as unavailable. Direct joins against another package's private tables are not part of this contract.

The contract tests exercise two real Landing graph boundaries: Aleph-owned observations and artifacts, including Funes provenance, and Kilgore-owned interpretations used as secondary-package relations. The fixtures use only the public reference and resolver contract; Funes imports neither package's model or storage classes.

The package-bound `IdentityRegistry` resolves an `ExternalIdentityClaim` to a stable Funes entity reference. A claim combines an entity kind, source reference, opaque external identifier, and provenance assertion. Repeating the same claim returns the same `funes:` reference and does not duplicate identity evidence. A later observation of the same source identifier adds provenance to that entity. Different external identifiers remain different entities; Funes does not infer cross-source equivalence.

`IdentityRegistry::find()` queries by kind, source, and exact external identifier. `get()` queries by a stable Funes `EntityReference`. Both return preserved external identifiers and their provenance evidence.

Typed relationships can preserve facts such as:

- one message replying to another;
- an event belonging to a project;
- a command occurring during an agent run;
- an artifact being produced by a task;
- one record correcting or superseding another;
- a parent, child, or causal relationship explicitly reported by a source.

Relationships are retained with their own provenance so inferred connections do not become indistinguishable from source-reported facts.

Entity associations are the first executable relationship seam. An `EntityAssociationDraft` assigns a validated `subject`, `actor`, `context`, `artifact`, or `target` role to a durable `CrossPackageReference`. The reference's stable object type supports projects, repositories, users, agents, conversations, files, tasks, and additional owner-defined entities without importing their models.

Association facts are immutable and idempotent by observation, role, and complete reference. Repeated source evidence appends association-provenance links instead of duplicating the fact. Observations return their associations, filter them by role and optional entity type, and `ObservationStore::associationsTo()` traverses from an exact durable entity reference back to every associated historical observation. A source retry or owner-side entity change cannot rewrite the association that was observed.

Historical event relationships use a separate seam. An observation exposes its own versioned `sifrious/funes` reference and may retain `related`, `references`, `responds-to`, `corrects`, or `supersedes` links to another historical event reference. The relationship stores only the target reference, never a duplicated event. Internal Funes observation targets must already exist; external historical event references remain portable and owner-resolvable.

Relationship facts deduplicate by source observation, type, and target reference. Their provenance links append independently as later source encounters confirm the same relation. `ObservationStore::relationshipsTo()` provides deterministic incoming traversal while `Observation::related()` filters outgoing relations. These types describe supplied relationships but do not claim causality or parentage; those stronger semantics require the later causal relationship contract and evidence.

The stronger `caused-by` and `child-of` types require a `RelationshipDeclarationDraft`. The declaration preserves both a namespaced source field locator, such as `github:event/caused_by`, and the non-empty value supplied by that source. The accepted declaration is linked to the observation provenance that carried it. Ordinary `related` or temporal adjacency never becomes causal or hierarchical through inference.

Exact retries reuse the declaration. A later source encounter appends another declaration assertion with its provenance while retaining one relationship fact. Direction is explicit: the source observation was caused by, or is a child of, the target historical event reference.

## Historical assertions

`HistoricalAssertionContract` is the provider-neutral contract for a single durable claim: a durable
subject reference, a stable lowercase predicate, a JSON-encodable value, and the evidentiary and
temporal circumstances under which the claim was made. Consumers depend on it without knowing which
model, IDE, source-control host, or storage vendor supplied the material.

`AbstractHistoricalAssertion` is the only direct parent for provider-family abstractions. It owns
canonical identity, invariants, temporal semantics, and the versioned serialization boundary;
subclasses supply their assertion type and provider mapping and never redefine those semantics.
Instances are readonly, so a corrected claim becomes a new assertion related to the earlier one
rather than an edit to it.

The assertion type is fixed by the subclass rather than passed in, which is what keeps the taxonomy
meaningful: an observation cannot silently become an inference. Inferred assertions require
non-empty evidence. Occurrence, observation, and recording times stay distinct and must be
chronological, and occurrence may be unknown.

`fingerprint()` digests the durable fact — type, subject, predicate, value, source locator,
occurrence, and tenant — and deliberately excludes the assertion's own identity and its observation
and recording times. Two encounters of the same claim therefore share a fingerprint, which is what
makes repeated ingestion idempotent, while an inference, a different tenant, or a different value
fingerprints differently.

`toArray()` emits a `sifrious.historical-assertion` document at contract version 1. Each subclass
implements its own `fromArray()` over the shared `decodeState()` helper, so no subclass reinterprets
the wire format and a serialized assertion cannot be decoded by a class of a different type.

The contract is composed from five capability interfaces in `Sifrious\Funes\Concern` —
`HasStableIdentity`, `HasProvenance`, `HasTemporalCoordinates`, `HasTenantScope`, and `HasEvidence` —
which the other historical substrate objects share rather than redeclare. Concerns never declare
state, so composition cannot duplicate what an object already owns; where a mechanic genuinely
repeats, such as the chronology rule and the temporal wire format, it is supplied by a stateless
trait alongside the interface.

Concerns deliberately not composed are recorded with their reasons and their real owners in
[docs/concerns.md](docs/concerns.md), alongside the invariant-ownership table. A missing concern and
a deliberately excluded one are otherwise indistinguishable after the fact.

Three concrete classes complete the assertion taxonomy: `ObservedHistoricalAssertion` for what a
source showed, `DeclaredHistoricalAssertion` for what a source explicitly stated, and
`InferredHistoricalAssertion` for what a later process reasoned its way to. Each fixes one type and
adds nothing else, so the three are freely substitutable and produce the same observable contract
behavior. There is no provider-family layer on this object: assertion type and provider family are
independent axes, and an AI-model-sourced claim may be observed, declared, or inferred. Provider
identity and payload mapping stay in Aleph's acquisition adapters, which normalize into these types.
The inheritance graph and that reasoning are in [docs/concerns.md](docs/concerns.md).

### Storing assertions

`HistoricalAssertionStore` is the durable system of record. Every method takes the caller's
authorization context and scopes its work to that context's tenant. Reads never cross a tenant
boundary and never reveal that they could have: another tenant's assertion is absent, not forbidden,
so existence does not leak through an error. Appending into a tenant the caller does not hold fails
explicitly, because that is the caller overreaching rather than a reader being scoped.

Nothing mutates or deletes. An append is idempotent by assertion fingerprint and returns an explicit
`first` or `duplicate` disposition; a duplicate returns the assertion already stored, so a retrying
caller learns the identity of record. Reusing one identity for a different claim is a conflict rather
than an overwrite. The same claim held by two tenants stays two separate facts.

`asOf()` reconstructs by transaction time: the latest assertion recorded at or before a given moment,
ignoring everything the store learned afterwards. A tombstone applies only from the moment it was
recorded, so a claim withdrawn today is still returned for a moment before the withdrawal — which is
the point of asking what was known then rather than what is believed now. Valid-time reconstruction
over an effective interval is not this object's concern; an assertion is a point claim.

A withdrawal is a tombstone, never a delete. It requires a reason, records the withdrawing
authorization context and time, hides the claim from the live view, and leaves the assertion row
intact. Repeating a tombstone preserves the original withdrawal rather than restamping it. Destroying
the underlying material is erasure, which is a separate concern.

Timestamp columns are written through `StoredTimestamp`, described below.

## How a moment is stored

`Sifrious\\Funes\\Time\\StoredTimestamp` is the single authority for writing a moment to a Funes
column and reading it back.

A database driver's own date binding formats a value in whatever timezone it arrives in, and to whole
seconds. Both losses matter here. Truncating microseconds discards ordering this package promises to
preserve. Dropping the offset is worse than imprecise: a source reporting noon at `+02:00` and one
reporting noon at UTC describe instants two hours apart, and stored as bare wall-clock text they
become indistinguishable from two instants two hours apart in the other direction. Comparison,
ordering, and every point-in-time reconstruction built on them are then quietly wrong.

So a stored moment is normalized to UTC and written at microsecond precision, which makes the text
lexicographically comparable no matter what offset a source reported — what lets an index range-scan a
timeline. The offset a source actually used is not discarded: it survives in the canonical document
or value object that retrieval hydrates from. These columns exist to filter and order, not to be the
record of what a source said.

Drafts that carry a moment as text go through `StoredTimestamp::normalize()`, which parses and
reformats. A value that is not a usable moment fails there rather than becoming a column that cannot
be compared.

A test asserts that no `Sql*` store binds a raw date or value to a `*_at` column, so the driver
cannot quietly reintroduce the truncation.

## Cross-package events and delivery

Package-owned behavior crosses boundaries through `EventEnvelope`. The versioned serialized contract
requires one stable event ID, event type, producing package, event contract version, occurrence and
recording times, at least one durable subject reference, and a payload. Observation time, causation,
correlation, provenance references, source metadata, and a subject-owned stream position remain
explicit when applicable. Aleph observations, Titan work transitions, and Logres runtime events use
the same envelope without importing each other's models.

The event ID is the idempotency key at every consumer boundary. Consumers retain the event fingerprint
with their accepted result: exact redelivery returns the original result without a second effect, while
the same ID with different immutable content is a conflict. At-least-once delivery is the baseline.
No global ordering is implied; an optional `EventStreamPosition` orders events only within its exact
durable stream reference.

`DeliveryAttempt` is a separate immutable contract naming the event ID and fingerprint. A started,
succeeded, retryable-failure, or dead-lettered attempt cannot alter the original event. Retryable
failures require a later retry time; dead-lettered attempts require a durable dead-letter reference.
Transport adapters own dispatch, retry scheduling, and dead-letter operation. Funes may preserve the
event, subjects, payload, and provenance as history, while Logres or another coordinator retains
authority over current execution state.

## Retrieval and long-term memory

Funes is intended to support structured, textual, and optional semantic retrieval over preserved history. Consumers should be able to search by entity, project, agent, source, time range, record type, tags, and metadata; reconstruct the history surrounding a moment; produce timelines; and traverse relationships.

Retrieved context should carry citations that resolve back to preserved records and their original sources. Search indexes are treated as rebuildable projections, not the authoritative history itself.

This makes Funes suitable as long-term memory for applications, automation, agents, and other systems that need historical context without depending on the storage conventions of every original provider.

## Design principles

Funes is guided by a small set of constraints:

1. Preserve source material and provenance.
2. Normalize without destroying provider-specific evidence.
3. Store essential historical facts; derive counts, timelines, display states, and search projections.
4. Prefer append-oriented observations and explicit corrections over destructive rewrites.
5. Make repeated ingestion safe.
6. Keep observed facts distinct from interpretations.
7. Keep persistence and search replaceable at genuine implementation boundaries.
8. Avoid assumptions about the application using the package.

## Crawler persistence

The first executable slice is a database-backed observation store. Run the package migrations, then resolve `Sifrious\Funes\Persistence\ObservationStore` from the Laravel container.

Landing is the master application schema and the source of truth for shared database vocabulary. Funes appends only `funes_*` history tables. Its overlapping storage names follow landing: raw payload length is `byte_size`, the received header is `content_type`, and local acceptance time is `ingested_at`.

`accept()` takes an `ObservationDraft` containing a source reference and name, canonical resource reference, producer reference and name, ingestion-run reference, observation time, raw payload, and optional occurrence time, transformation lineage, metadata, and discoveries. Source, resource, producer, and ingestion-run references must be non-empty. Occurrence time cannot be later than observation time.

It returns an `AcceptedObservation` with the stable observation, an explicit `first`, `unchanged`, or `changed` disposition, and every known `Provenance` assertion. Each assertion contains a resolvable `SourceLocator`, a stable `Producer`, separate occurrence, observation, and recording times, and transformation lineage. Acceptance is transactional and idempotent by canonical resource and payload hash. An exact retry does not duplicate its provenance assertion. A later observation of identical content preserves an additional provenance assertion without creating another historical effect. Changed content appends a new immutable observation.

Structured attributes enter as `MetadataDraft` values with a namespaced identifier, schema version, and JSON-encodable attributes. Funes stores them as append-only `MetadataAssertion` values linked to the accepting provenance assertion. Metadata changes never change observation identity: the observation returns every assertion, and `metadata()` filters deterministically by namespace and optional schema version. Pre-contract JSON remains readable under the explicit `funes:legacy` namespace rather than being rewritten.

`find()` recovers the latest immutable observation, original payload, and known provenance by source and canonical resource reference. Every provenance assertion returns its producer and stable `IngestionRun`. `get()` recovers the same representation for any historical observation by its immutable ID. Acceptance-gateway replay also resolves the accepted observation and provenance. `discoveriesTo()` resolves a discovered resource back to its parent resources and observations.

`recordExtraction()` requires a `ProducerContext` containing producer identity and ingestion-run reference, then appends an idempotent success or failure identified by observation, extractor, and extractor version. Exact producer/run retries reuse the derived result and context; another run producing the same result appends context without duplicating the result. The configured database connection is read from `funes.connection`; `null` uses Laravel's default connection.

## Offline sentence diagrams (small local slice)

The package now exposes a compact local sentence diagram capability:

- call `Sifrious\Funes\diagram($sentence)` to get `{source, grammar_graph, svg, timings, warnings, provenance}`;
- the normal path is offline only (`provenance.mode = offline`, `provenance.llm_used = false`);
- grammar parsing, Reed-Kellogg-ish transformation, and SVG rendering are separate adapters;
- no English grammar theory is persisted in Funes domain tables.

To preserve diagram representations through Funes history, resolve `Sifrious\Funes\Diagram\SentenceDiagramService` from the container and call `diagramAndRecord($observationId, $producerContext)`. It records `sentence-diagram` extractions with incrementing versions (`1`, `2`, ...) so re-runs append new derived records instead of overwriting source or earlier versions.

### Fixture command and manual Mac timing step

Run one small fixture set and print timings:

`composer diagram:fixtures`

This prints per-sentence `total_ms`, `parse_ms`, `transform_ms`, and `render_ms`.

Manual verification on a specific Mac remains a human step using the same command above.

Historical text enters as a namespaced `TextDraft` with content type, optional language, and immutable content. Retrieval returns append-only `TextAssertion` values linked to observation provenance. A textual raw payload is also exposed as `funes:source-payload`, retaining its original payload hash as text identity. Changed attached text appends without changing the observation; exact retry reuses the assertion.

The package-bound `TextProjection` rebuilds adapter-ready text documents entirely from authoritative observations, payloads, and text assertions. The projection can be deleted and recreated without losing history. It exposes documents rather than ranking them; querying and ranking belong to `FullTextSearch` below.

This slice deliberately excludes crawling, URL canonicalization policy, payload compression, object storage, semantic retrieval, and mutable resource state. Callers decide what a canonical reference means; Funes preserves it without assuming a particular website platform or content domain.
Funes also exposes a durable append-only historical graph under `Sifrious\\Funes\\Graph`. The package-bound `SqlHistoricalAppender` atomically stores stable entity facts, external identifiers, and typed relation assertions with source evidence. Append keys are idempotent: exact replay returns without another effect, while reuse with different facts raises `HistoricalAppendConflict`. Identical facts may participate in multiple append receipts without being duplicated. Inferred relations must carry evidence and confidence, keeping generated meaning distinct from observed history.

### Full-text search

`Sifrious\\Funes\\Search\\FullTextSearch` is the discovery path for history someone can only describe. Resolve it from the container, call `rebuild()` to build the index from stored assertions, and call `search(new SearchQuery('losing the instant'), $authorization)` to read it.

A `SearchQuery` carries the text plus optional `subjectTypes`, `predicates`, and `sourceReferences` filters, so a caller can ask for a commit whose message says something rather than for the phrase anywhere. Every normalized term must match, at a token boundary. When the text looks like an identifier — a git object name, a key such as `MME-1887`, or an already-namespaced reference — the query reports it through `identifierCandidate()`, and the caller should offer it to identity resolution before scoring anything.

`SearchResults` carries the ranked page, the authorized `total`, whether the match set exceeded the ranked window, and that identifier candidate. Each `SearchHit` hands back the canonical assertion itself — stable subject reference, source locator, provenance, evidence, and temporal coordinates — alongside the matched field and a snippet, so a result is citable and explains why it matched.

The index is a projection and never an authority. It is rebuilt in full from stored assertions, nothing in the seam writes a claim, and a destroyed index costs a rebuild and no history. Reads are scoped to the caller's tenant in SQL before anything is fetched, scored, or counted, so another tenant's history is absent from the hits and from the total alike; a withdrawn assertion leaves search at once rather than at the next rebuild.

`TwinkleHistory` accepts immutable Elwin lifecycle envelopes without importing Elwin or Titan models. It preserves versions, provenance, merge identities, and Twinkle-to-Titan promotion subjects. Exact redelivery is idempotent, conflicting event reuse fails explicitly, and retrieval is chronologically ordered evidence rather than a current-state projection.
