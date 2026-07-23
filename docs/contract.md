# EvoLayer Framework Contract

This document defines the canonical operating contract for the EvoLayer framework, its surfaces, and its lifecycle. It is the source of truth for both human contributors and AI agents modifying the repository.

## Ownership Boundaries

EvoLayer is a framework distributed via a project template (`xuple/evolayer-base-starter`). The boundary between what the framework owns and what the host application owns is strictly defined:

| Surface | Owner | Lifecycle |
| --- | --- | --- |
| **Substrate** (AI runtime, ontology compiler, `evolayer:*` commands) | **Framework** | Updated by upgrading the `xuple/evolayer-base` package. |
| **Generates** (Wayfinder routes, compiled ontology TS types) | **Generated** | Deterministically rebuilt by CLI commands. Not hand-edited. |
| **Managed Surfaces** (Examples, Demo workflows, Optional blocks) | **Framework (until ejected)** | Updated safely by `php artisan evolayer:resync` as long as they are pristine. |
| **App code** (Your routes, pages, logic, configuration, ejected examples) | **You (the App)** | Never overwritten by the framework. |

## The Lifecycle Commands

The framework interacts with the host application through these lifecycle commands:

### `evolayer:resync`
- **Purpose**: Safely republishes package-managed frontend stubs and updates the resync manifest. It does **not** regenerate Wayfinder routes or ontology types (those are generated artifacts rebuilt by their own commands during verification/build).
- **Rule**: It must **never** overwrite app-owned files or examples that the user has explicitly ejected. It only updates unmodified framework-managed surfaces, skips surfaces disabled by committed profile intent, and restores a re-enabled surface from the current package version.

### `evolayer:eject`
- **Purpose**: The canonical exit hatch. `php artisan evolayer:eject <surface>` transfers ownership of a managed example or block to the host application.
- **Rule**: Once a surface is ejected, it belongs fully to the application. It will no longer receive framework updates via `resync`.

### `evolayer:manifest:inspect` / `evolayer:manifest:adopt`
- **Purpose**: Inspects incomplete legacy provenance and, when explicitly run
  with `--pristine-only`, repairs records whose exact bytes match a reviewed
  public distribution checksum. `inspect --json` is non-mutating and reports
  stable `clean`, `adoptable`, `selection-required`, or `conflict` states.
- **Rule**: Historical checksums are corroboration for descriptor-owned targets,
  never a source of paths. Unknown or modified bytes require ejection,
  restoration, or manual reconciliation. Adoption is all-or-nothing, uses the
  shared mutation lock, and revalidates every evidence fingerprint immediately
  before committing the manifest.

### `evolayer:profile`
- **Purpose**: Applies a registered operational profile such as `demo` or `lean`. Base owns the mechanics and generic profiles; the official Starter owns its host-specific `application` definition and contributor.
- **Rule**: Plans the complete transition before writing. It updates configuration
  flags and may prune disabled framework-managed frontend files only when the
  resync manifest proves they are pristine. Modified, unknown, and ejected files
  are never overwritten or deleted; an unresolved ownership conflict aborts the
  transition before any intent, environment, manifest, or managed file changes.
  Use `--dry-run` to inspect the plan or `--no-env` when deployment configuration
  is projected by process variables or a secret manager rather than a local file.
  Repeatable `--example=key=true|false` and `--feature=key=true|false` options
  record explicit deltas from the registered baseline; unknown, malformed, or
  duplicate overrides fail before planning any mutation. `--json` returns only
  stable operation counts and error codes; it intentionally omits machine paths,
  environment values, and raw conflict messages.

### `evolayer:profile:status`
- **Purpose**: Compares committed intent with effective Laravel configuration and descriptor-constrained managed source. `--json` is the automation surface and omits environment values and machine paths.
- **Rule**: Legacy metadata without an explicit operational profile reports `selection-required`; inspection never writes a guessed schema-v2 profile. Matching intent remains `pending-verification` and exits non-zero until the separate verification phase succeeds. A current hash-bound receipt promotes that state to `verified`; missing, invalid, or stale evidence does not. Generated-contract state is never inferred from an otherwise aligned profile.

### `evolayer:profile:verify`
- **Purpose**: Evaluates current package-owned profile facts and every verification capability required by the selected profile. It does not change committed intent, deployment configuration, or managed source, and it does not roll back a prior apply.
- **Base checks**: Resolvable committed intent; effective profile/example/feature agreement; strict descriptor-constrained manifest validity; no modified, unknown, stale-disabled, or required-absent managed source; enabled managed routes present; disabled managed routes absent; and no unallowlisted managed package/host route collision.
- **Host extension**: A host registers a versioned `ProfileVerificationCheck` with a stable check ID, one provided capability, a deterministic pass/fail result, a redacted corrective action, and canonical input fingerprint material. Missing required capabilities fail closed. The official Starter owns checks for its generated contracts and other host responsibilities. Base never hardcodes npm, Wayfinder, ontology, Vite, Starter file paths, or arbitrary shell commands.
- **Receipt**: Success may write `storage/framework/cache/data/evolayer-profile-verification.json`. This local cache path must remain ignored. The receipt binds intent, effective, manifest, and managed-state hashes; Base and host versions/references; verifier contract version; successful check IDs; and check-input hashes. It contains no values, credentials, absolute paths, full command output, or trusted `verified` boolean. Status recomputes current bindings and treats any mismatch as stale.
- **Machine output**: `--json` emits stable check IDs, error codes, redacted corrective actions, lifecycle status, selected profile, and receipt state. Unexpected failures collapse to `internal-error`; exception text and local paths are not serialized.

## Supported `0.1.x` Profile Upgrade

The supported historical package-owned migration baseline is the exact public
pair Starter `v0.1.19` + Base `v0.1.9`. The package fixture is derived from the
immutable release tags, not a simplified reconstruction:

| Evidence | Bound value |
| --- | --- |
| Starter tag commit | `ffa53f4c329c65c37e7b0977942bbb4368185f4e` |
| Base tag commit | `a00984e5a8accff2ed6d35e7ae6f63d71c7cb5e4` |
| Extracted Starter file-tree SHA-256 | `cee6f0c3a426ad58f629684b8cdfcd39c4ae44bcf0ecfa6c5cfe7cf200074cae` |
| Legacy manifest SHA-256 | `f142d18a2453841ed046ccd87ab01c0fd3fae79a6593e1238da887df3eada8a5` |

The fixture's `provenance.json` records the remaining reviewed hashes and the
known Contact provenance gap. Its generated schema-v1 project metadata fixes
only the install-path, suggested-package-name, and timestamp fields that the
released finalizer generated dynamically; the finalizer itself and resulting
fixture metadata are independently hash-bound.

The safe package-owned sequence is:

```text
profile:status / manifest:inspect
→ manifest:adopt --pristine-only
→ explicit profile apply
→ resync current package source
→ regenerate host-owned contracts
→ profile:verify
```

Legacy `mode: application` migrates to repository identity
`kind: generated-application`; it never selects the operational `application`
profile. Exact default `v0.1.19` flags may be proposed as `demo`, while mixed
state remains `selection-required`. Inspection does not persist schema-v2
intent. Adoption additionally requires the exact reviewed Starter/Base version
pair and exact approved bytes; formatting, imports, AST shape, filenames, and
directory placement never prove ownership.

The released manifest records Contact thank-you source but omits Contact source.
Only either reviewed released Contact byte sequence is adoptable. Modified bytes
remain unknown and block incompatible mutation. Resync refuses a package-version
crossing while unresolved adoptable source is present, so it cannot erase the
evidence needed for the explicit adoption decision. Repeated inspection,
adoption, profile application, and resync are no-ops once current.

This package fixture proves Base-owned migration mechanics. The official Starter
application contributor, generated Wayfinder/ontology contracts, npm/type/build/
SSR gates, Packagist archives, and real `composer create-project` distribution
remain Starter/distribution release-gate responsibilities.

## Profile State and Authority

Profile state is intentionally split rather than represented by one overloaded flag:

| State | Authority |
| --- | --- |
| Repository identity | `.evolayer/project.json` `kind`; describes what produced the repository, not its current posture. |
| Operational intent | Versioned `profile` plus explicit overrides in committed project metadata. |
| Effective state | Values resolved through Laravel configuration, including cached configuration. |
| Managed state | Typed package descriptors, corroborated by manifest provenance. |
| Generated state | Wayfinder, ontology, client, and SSR outputs rebuilt after apply. |
| Verification evidence | Current command/CI output or an ignored local receipt; never authoritative committed intent. |

The lifecycle is **plan → transactional apply → verify → status**. Transactional
apply covers supported file contents, existence, and modes. It does not claim to
roll back package-manager commands, generated caches, external systems, inode
identity, ownership, timestamps, ACLs, extended attributes, or crash-time state.

Typed descriptors are the only authority that may introduce managed paths. The
resync manifest is evidence about an exact descriptor target; absolute, aliased,
traversal, stale, or surface-mismatched records fail closed and never become
mutation targets. Profile, resync, and eject share strict parsing, a project lock,
immutable file preconditions, and aggregated rollback reporting.

Managed route contracts are descriptor-owned as well. Production diagnostics
compare each enabled contract by domain, normalized URI, and overlapping HTTP
methods, including GET/HEAD and ANY semantics. Do not use
`route:list --except-vendor` as a collision audit: an overwritten route is absent
from that filtered view. Intentional collisions require review and the exact
stable ID in `evolayer.base.route.collision_allowlist`.

Production diagnostics also compare route presence with effective profile state.
An enabled managed route that is missing and a disabled managed route retained by
a stale route cache are both failures; clear and deliberately rebuild the route
cache before verification.

`evolayer:doctor --production` is a failing deployment diagnostic rather than
the default informational doctor mode. It compares committed profile intent
with effective cached Laravel configuration, reports descriptor-relative
managed-source findings, audits route ownership, and rejects a known-public
medialibrary disk when Contact evidence attachments are enabled. It never prints
raw environment values, secrets, or machine paths.

## Anti-Scope (Do Not Do)
- The framework does **not** assume control over host authentication routing, though it provides examples.
- The framework does **not** mandate billing, multi-tenancy, or row-level security. Those concerns belong to sibling packages (`evolayer-saas`, `evolayer-commerce`, `evolayer-rls`).

*Note: For details on reproducible starter distributions and `composer.lock` handling, see the starter repository documentation.*
