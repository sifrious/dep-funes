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
