# Concern register

The ABC Provider Manifesto — the design source governing these types — fixes a vocabulary of
fifteen concerns. Every
provider-neutral historical object must assign each one explicitly: composed, or documented as not
applicable with the reason and the owner that does hold it. Silent omission is not permitted,
because a missing concern and a deliberately excluded one are indistinguishable after the fact.

Concerns are expressed as capability interfaces in `Sifrious\Funes\Concern`. Shared implementation
mechanics — only where the mechanic genuinely repeats — are supplied as stateless traits alongside
them. A concern never declares state: the object owns its own properties, so composition cannot
duplicate what the provider-neutral base class already holds.

## HistoricalAssertion

Contract: `Sifrious\Funes\Assertion\HistoricalAssertionContract` ·
Base: `Sifrious\Funes\Assertion\AbstractHistoricalAssertion`

### Composed

| Concern | Members | Why it applies |
| --- | --- | --- |
| `HasStableIdentity` | `stableIdentity()` | An assertion is referenced by later corrections, supersessions, and evidence links, so its identity must survive re-ingestion and projection rebuilds. |
| `HasProvenance` | `source()`, `provenance()` | A claim is only trustworthy if the original material stays recoverable and the assertion that carried it stays nameable. |
| `HasTemporalCoordinates` | `occurredAt()`, `observedAt()`, `recordedAt()` | Occurrence, observation, and recording are three different moments; collapsing them makes reconstruction unreliable. |
| `HasTenantScope` | `tenant()` | Retrieval filtering, retention, and erasure all target a tenant's evidence, and the boundary must travel with the durable fact rather than be inferred at read time. |
| `HasEvidence` | `evidence()` | An inference must name what supports it, and support must be traversable without duplicating the supporting material. |

### Not applicable

| Concern | Reason | Owner |
| --- | --- | --- |
| `HasProviderIdentity` | The canonical assertion carries no provider name. Provider identity is mapping metadata held by the concrete subclass. | Concrete provider subclasses (MME-2548). |
| `HasSourceLocator` | Not composed separately: an assertion's provenance always includes its source locator, so a second concern exposing the same value would let the two disagree. | `HasProvenance`. |
| `HasActor` | The assertion records a claim, not who made it. The producer and ingestion run belong to the provenance assertion that carried it. | `Provenance`, `Producer`, `IngestionRun`. |
| `HasAuthorizationContext` | An accepted fact is not an authorization decision. The caller's actor and correlation context belong to the acceptance boundary; only the tenant persists on the fact. | `HistoricalAppendAuthorizationContract`. |
| `HasEffectiveInterval` | An assertion is a point claim about an occurrence, not a value that holds across a valid-time interval. | `HistoricalEntityVersion` (MME-2395). |
| `HasImmutableVersion` | The assertion is immutable in whole; a correction is a new assertion, so there is no version sequence to expose. | `HistoricalEntityVersion` (MME-2446). |
| `HasContentHash` | `fingerprint()` already digests the durable fact. A raw-payload content hash addresses stored source material, which an assertion references rather than contains. | `fingerprint()`; `SnapshotManifest` for stored artifacts (MME-2450). |
| `HasConfidence` | The design source assigns confidence to relationship drafts, not to this object. Inferred assertions are constrained by required evidence instead. Recorded as an open question rather than settled. | `HistoricalRelationDraft`. |
| `HasParent` | Assertions do not nest. A relation between two claims is itself a typed, evidenced assertion rather than a structural parent link. | `HistoricalRelationshipAssertion` (MME-2447). |
| `HasExternalReferences` | Mapping a source-owned identifier to a stable entity is an identity operation with its own provenance, not a field on every claim. | `IdentityRegistry`, `ExternalIdentity`. |

### Funes needs represented

- **Indexing** — `fingerprint()`, `subject()`, `predicate()`, `tenant()`, and `occurredAt()` are the
  durable keys retrieval projections build on.
- **Authorization** — `tenant()` scopes every read; the acceptance boundary holds the caller context.
- **Retention and erasure** — `tenant()` and `recordedAt()` make a tenant's evidence targetable
  without consulting the source system.
- **Historical reconstruction** — `source()`, `provenance()`, the three temporal coordinates, and
  `evidence()` together answer where a claim came from, when it was known, and what supports it.

### Invariant ownership

Each invariant has exactly one owner. None is enforced in two places.

| Invariant | Owner |
| --- | --- |
| Assertion ids are non-empty opaque values without whitespace | `AbstractHistoricalAssertion` |
| Predicates are stable lowercase identifiers | `AbstractHistoricalAssertion` |
| Values are JSON-encodable scalars, null, or arrays | `AbstractHistoricalAssertion` |
| Occurrence precedes observation precedes recording | `SerializesTemporalCoordinates` |
| Temporal wire format is microsecond precision with an explicit offset | `SerializesTemporalCoordinates` |
| Evidence entries are durable cross-package references | `AbstractHistoricalAssertion` |
| Inferred assertions carry non-empty evidence | `AbstractHistoricalAssertion` |
| The assertion type is fixed by the class, not by a value | Each concrete subclass |
| Tenant scope kinds are stable lowercase identifiers, and only `unscoped` omits a tenant | `TenantScope` |
| Source locators require a source reference, source name, and resource reference | `SourceLocator` |

## HistoricalAssertion inheritance graph

```text
HistoricalAssertionContract          composes the five concerns above
        ▲
AbstractHistoricalAssertion          identity, invariants, temporal semantics, wire format
        ├── ObservedHistoricalAssertion
        ├── DeclaredHistoricalAssertion
        └── InferredHistoricalAssertion
```

Each concrete class fixes exactly one assertion type and adds nothing else. No concrete class
extends another concrete class, and a test walks `src/Assertion` to keep it that way.

### Why there is no provider family here

The manifesto's canonical shape inserts a provider-family layer —
`Abstract<Object>` ← `AiModel<Object>` ← `Claude<Object>` — and instructs that one be introduced
"only where they add shared semantics." For `HistoricalAssertion` there are none to add. The design
source lists observed, declared, and inferred as the only direct subclasses, and those are
provider-neutral specializations rather than acquisition families.

The reason is structural: assertion type and provider family are independent axes. An
AI-model-sourced claim can be observed, declared, or inferred, so an `AiModelHistoricalAssertion`
placed under any one of them would be wrong for the other two, and placed above them it would
collide with the type axis that D-013 makes a class-level invariant. Provider identity and payload
mapping belong to Aleph's acquisition adapters, which normalize into these canonical types.

Sibling objects differ. `HistoricalEvent`'s design source does list acquisition families —
`ImportedLandingEvent`, `RunnerHistoricalEvent`, `AiModelHistoricalEvent`, `ClaudeHistoricalEvent` —
as direct subclasses, because for an event the acquisition runtime is the shared semantics. The
family layer belongs there, not here.
