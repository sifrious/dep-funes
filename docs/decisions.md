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
