# Funes

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

`find()` recovers the latest immutable observation, original payload, and known provenance by source and canonical resource reference. Every provenance assertion returns its producer and stable `IngestionRun`. `get()` recovers the same representation for any historical observation by its immutable ID. Acceptance-gateway replay also resolves the accepted observation and provenance. `discoveriesTo()` resolves a discovered resource back to its parent resources and observations.

`recordExtraction()` requires a `ProducerContext` containing producer identity and ingestion-run reference, then appends an idempotent success or failure identified by observation, extractor, and extractor version. Exact producer/run retries reuse the derived result and context; another run producing the same result appends context without duplicating the result. The configured database connection is read from `funes.connection`; `null` uses Laravel's default connection.

This slice deliberately excludes crawling, URL canonicalization policy, payload compression, object storage, search projections, and mutable resource state. Callers decide what a canonical reference means; Funes preserves it without assuming a particular website platform or content domain.
