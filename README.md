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

For correction workflows, resolve `Sifrious\Funes\Correction\CorrectionService` and call `apply($originalObservationId, $correctionDraft)`. The service preserves the original observation and appends a new correction observation that links back with a `corrects` or `supersedes` relationship. Retries are idempotent by correction idempotency key through the existing acceptance gateway contract.

The stronger `caused-by` and `child-of` types require a `RelationshipDeclarationDraft`. The declaration preserves both a namespaced source field locator, such as `github:event/caused_by`, and the non-empty value supplied by that source. The accepted declaration is linked to the observation provenance that carried it. Ordinary `related` or temporal adjacency never becomes causal or hierarchical through inference.

Exact retries reuse the declaration. A later source encounter appends another declaration assertion with its provenance while retaining one relationship fact. Direction is explicit: the source observation was caused by, or is a child of, the target historical event reference.

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

The package-bound `TextProjection` rebuilds adapter-ready text documents entirely from authoritative observations, payloads, and text assertions. The projection can be deleted and recreated without losing history. It deliberately exposes documents rather than implementing full-text ranking or a search engine; those query behaviors belong to the later index/search stage.

This slice deliberately excludes crawling, URL canonicalization policy, payload compression, object storage, search projections, and mutable resource state. Callers decide what a canonical reference means; Funes preserves it without assuming a particular website platform or content domain.
Funes also exposes a durable append-only historical graph under `Sifrious\\Funes\\Graph`. The package-bound `SqlHistoricalAppender` atomically stores stable entity facts, external identifiers, and typed relation assertions with source evidence. Append keys are idempotent: exact replay returns without another effect, while reuse with different facts raises `HistoricalAppendConflict`. Identical facts may participate in multiple append receipts without being duplicated. Inferred relations must carry evidence and confidence, keeping generated meaning distinct from observed history.

`TwinkleHistory` accepts immutable Elwin lifecycle envelopes without importing Elwin or Titan models. It preserves versions, provenance, merge identities, and Twinkle-to-Titan promotion subjects. Exact redelivery is idempotent, conflicting event reuse fails explicitly, and retrieval is chronologically ordered evidence rather than a current-state projection.
