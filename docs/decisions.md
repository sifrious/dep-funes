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
