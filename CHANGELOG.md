# Changelog

All notable changes to `xuple/evolayer-base` are documented here. The format is
based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and this
project aims to follow [Semantic Versioning](https://semver.org/).

## [Unreleased]

### Fixed

- `evolayer:profile` no longer discards `applied_with.starter` from
  `.evolayer/project.json`. The block was recomputed and overwritten on every
  transition, and the starter version is only derivable while the root package
  is still the starter. Once a generated application claims its own Composer
  name — the documented `composer config name app/<app>` step — derivation
  became impossible and the key was silently dropped. The install-time record is
  now preserved when it cannot be re-derived. A side effect of the same bug: a
  renamed application already in its target profile never reported a clean
  `--dry-run`, always showing one pending metadata operation.

### Changed

- Refreshed the `laravel/ai` vendor patch dossier (`patches/README.md`) to the
  `v0.8.1` target and recorded the 2026-10-02 upstream check: the
  structured-streaming guard is still present through `laravel/ai` `v1.0.1`,
  the patch stops applying from `v0.10.0`, and the upstream PR is now
  recommended rather than deferred. Corrected the `scripts/apply-patches.php`
  header, which described hosts shipping a patched vendor file.

## [0.2.0-rc.2]

### Fixed

- Closing the Base-owned command palette with Escape now restores focus to the
  exact visible opener, or the element focused before Ctrl/Cmd+K. Search,
  command execution, reopen, desktop, and mobile behavior remain intact.

### Changed

- Legacy-manifest coverage now records changed managed command-palette bytes
  as stale package source, resyncs to current source, and proves repeated
  resync idempotent.

## [0.2.0-rc.1]

### Added

- Added typed managed-surface descriptors as the canonical source for example
  config keys, route files, publish paths, and ejection policy. Route
  registration, publishing, resync, and ejection now derive from the same map.
- Added a contributor-based profile transition transaction with conflict
  preflight, same-filesystem atomic writes, dry-run support, and exact rollback
  of file contents, existence, and permissions after an apply failure.
- Added versioned profile definitions and contributor capability contracts with
  dependency ordering, duplicate/cycle validation, and Starter-owned extension
  points for the official Starter's `application` profile.
- Added schema-v2 committed profile intent that keeps repository identity,
  operational profile, overrides, applied package versions, and verification
  evidence as separate concerns. Legacy Starter identity is migrated only after
  an operator explicitly selects a profile.
- Added repeatable, strictly validated `--example=key=true|false` and
  `--feature=key=true|false` profile overrides. The command commits only the
  explicit delta from the registered baseline and projects the selected profile
  through `EVOLAYER_BASE_PROFILE` for effective-state drift comparison.
- Added an immutable upgrade fixture extracted from Starter `v0.1.19` with its
  exact Base `v0.1.9` pin. Reviewed annotated tag objects and peeled release
  commits, a complete extracted-file tree hash, key distribution hashes, legacy
  identity, environment defaults, all 28 manifest records, the unrecorded
  Contact page, relevant Starter-owned source, and absent generated outputs bind
  the fixture to the public release. Focused tests now prove inspection,
  pair-bound exact-checksum adoption, explicit lean selection, transactional
  pruning, profile-aware resync, re-enable, bounded verification, idempotence,
  and fail-closed malformed/modified/ejected cases.
- Added `evolayer:profile:status --json` to compare committed intent with
  effective Laravel configuration and descriptor-constrained managed source
  without exposing environment values or machine paths.
- Added bounded `evolayer:profile:verify --json` current-state verification.
  Base verifies committed intent, effective configuration, descriptor-owned
  provenance and source, managed route state, and route collisions; selected
  profiles may require host-provided verification capabilities through a small
  versioned check contract. Successful runs write only a redacted, ignored,
  hash- and version-bound local receipt, which status treats as stale whenever
  any bound input or required check fingerprint changes.
- Added redacted `evolayer:profile --json` plan/apply output with stable schema,
  operation counts, verification state, conflict codes, and rollback-failure
  counts. Machine output never includes local paths or environment values.
- Added `evolayer:manifest:inspect --json` and the explicit
  `evolayer:manifest:adopt --pristine-only` repair path for legacy public
  distributions with incomplete provenance. Adoption accepts only reviewed,
  exact historical checksums and binds that evidence to the manifest
  transaction; similarity never grants managed-file authority.
- Added a shared non-blocking mutation lock plus hostile-manifest, traversal,
  link, hard-link, special-node, concurrent-change, and rollback-failure tests.
- Added method/domain-aware managed route contracts and production collision
  diagnostics. Package routes retain prior host-route evidence in cache-safe
  action metadata; later host overrides are compared with the descriptor
  contract, and only exact reviewed collision IDs may be allowlisted.
- `evolayer:doctor --production --json` now includes committed/effective/managed
  profile drift, route ownership, and the narrow Contact evidence invariant. An
  enabled attachment feature fails production diagnostics when the effective
  medialibrary disk is known-public; broader media delivery redesign remains
  outside the profile-transition release.

### Changed

- `evolayer:profile lean` now removes disabled package-managed frontend files
  only when the resync manifest proves they are pristine, and updates the
  environment and manifest in the same transaction. Modified, unknown, or
  ejected files abort the transition before any changes are made.
- Profile example re-enablement now restores an absent, non-ejected managed
  target from the currently installed package during the same transaction.
  Present downstream files are never adopted or overwritten. Restored
  provenance hashes the exact captured bytes staged for installation, keeping
  target content and manifest evidence internally consistent.
- Resync and ejection now apply the same captured-byte provenance rule: each
  managed source is read once, and the exact staged bytes determine the recorded
  source and installed checksums.
- Resync manifests now fail closed through one strict schema validator. Manifest
  records can corroborate descriptor-owned targets but can never introduce a
  mutation path; unknown, aliased, stale, or surface-mismatched records abort
  profile, resync, and eject without mutation.
- `evolayer:resync` now respects committed profile intent, skips disabled
  surfaces, restores re-enabled surfaces from the current package, and applies
  frontend plus manifest changes through the same preconditioned transaction as
  profile transitions. It refuses to erase unresolved adoption evidence while
  crossing a package version and avoids rewriting a semantically unchanged
  manifest. Ejection uses the same transaction as well.
- Profile application now supports `--no-env`; normal environment projection
  rejects linked files and ambiguous duplicate keys while preserving UTF-8 BOM
  and CRLF/LF newline style.
- Managed mutations are explicitly unsupported on Windows until junction,
  reparse-point, and atomic-replacement behavior is proven in Windows CI.
- Dry-runs no longer create the shared mutation lock. Duplicate contributor
  operations must carry identical preconditions, resync/eject revalidate
  unchanged ownership evidence, environment projection rejects whitespace and
  quoted duplicate keys, and route diagnostics catch both missing enabled
  routes and stale cached routes for disabled surfaces. Matching profile intent
  remains pending rather than claiming verification success.

## [0.1.9] - 2026-07-03

### Changed

- **BREAKING (pre-1.0 route-contract cleanup).** The package no longer owns the
  authenticated `/home` route. `routes/features/marketing_pages.php` now registers
  only the public `/about` explainer; the canonical authenticated launcher is
  **host-owned by the starter** (route named `home`), matching the framework
  contract that EvoLayer does not assume control over host authentication routing.
  This removes a hidden Wayfinder compile-time coupling — a core shell nav item no
  longer depends on a feature-gated package route, so disabling
  `EVOLAYER_BASE_EXAMPLE_MARKETING_PAGES` can no longer break the host build.
- **BREAKING.** The public explainer page is renamed `evolayer/about` →
  `evolayer/base` (component + published stub + `PublishMap`); `/about` and
  `evolayer.base.about` now render `evolayer/base`. The package-owned
  `evolayer/home.tsx` is removed (superseded by the starter's host-owned `home`
  page); `evolayer.base.home` no longer exists.

## [0.1.8] - 2026-06-30

### Added

- Public landing chrome (`PublicLayout`, `about.tsx`) now renders entirely from
  the brand contract (`useBrand()` / `config('evolayer.base.brand')`), removing
  the last `usePage().props.name` reference so a brand change no longer leaves
  stale public nav or title text. `PublicLayout` owns the page `<Head>` (the
  About page no longer emits its own), and the `about.tsx` CTA uses the
  `login()` Wayfinder route helper instead of a hardcoded `/login`.
- ADR-021 (`DECISIONS.md`): AI platform scope is frozen until 0.1 ships — a
  time-boxed scope-discipline decision (no probe-platform expansion, no new
  `conditions` readers, no ledger-surface growth; receipts / `doctor --json` /
  adaptive gating stay deferred), with a one-line pointer in AGENTS.md/CLAUDE.md.
- CONTRIBUTING note recording the accepted package↔starter patch-mechanism
  asymmetry (package `apply-patches.php` vs starter `cweagans/composer-patches`).

### Fixed

- The authenticated `/home` greeting ("Good morning/afternoon/evening") is now
  derived from a server-provided `greetingHour` prop instead of `new Date()` in
  render, eliminating an SSR/hydration mismatch when server and client
  timezones differ. The `/home` route passes `now()->hour`; `home.tsx` reads the
  prop with a client-side fallback for resilience against older consumers.

## [0.1.7] - 2026-06-29

### Added

- Added package-side Prettier tooling and CI coverage for the published
  `resources/js` frontend stubs, matching the starter's Tailwind-aware
  formatting contract.

### Changed

- Normalized the published `resources/js` frontend stubs with the same
  Prettier configuration used by host applications so `evolayer:resync` no
  longer introduces format-only drift.

## [0.1.6] - 2026-06-23

### Changed

- Raised the supported `laravel/ai` dependency line to `^0.8.1`; the package
  test suite passes against Laravel AI v0.8.1. The structured-output streaming
  patch is still required because the patch continues to apply cleanly on
  v0.8.1.

## [0.1.5] - 2026-06-14

### Added

- Added `docs/contract.md` as the canonical EvoLayer Framework Contract for
  package/starter ownership boundaries, generated artifacts, managed surfaces,
  and the `resync` / `eject` / `profile` lifecycle.

### Fixed

- Corrected canonical documentation URLs to
  `evodevops.com/evolayer-base/docs`.

## [0.1.4] - 2026-06-13

### Added

- Added manifest-driven `evolayer:resync` and `evolayer:eject` commands for
  ownership-safe frontend stub updates. Pristine framework-managed files can be
  refreshed, host-modified files are kept by default, `--force` is the explicit
  overwrite path, and ejected surfaces are skipped.
- Added `evolayer:profile {demo|lean}` to toggle bundled
  `EVOLAYER_BASE_EXAMPLE_*` demo surfaces from the host `.env`.
- Added `evolayer.base.brand`, `EvoLayerProps::base()`, and the published
  `useBrand()` hook so the package's home/about surfaces render from shared
  brand config instead of requiring starter-local page overrides.

### Changed

- Centralized frontend publish metadata in `Support\PublishMap` so
  vendor-publish tags, resync, and eject share the same source/target map.

## [0.1.3] - 2026-06-11

### Added

- Added the `evolayer-base-frontend-preserve-overrides` publish tag so host
  apps can force-resync package-owned frontend stubs without overwriting
  host-owned `evolayer/about.tsx` and `evolayer/home.tsx` landing pages.

## [0.1.2] - 2026-06-10

### Changed
- Aligned package docs and agent guidance with the current public package state:
  Packagist publication is live, the current public release line is `v0.1.1`,
  public CI runs on push/PR/workflow dispatch, and the starter consumes Base
  through `^0.1`.
- Clarified that `evolayer:doctor` verifies package/app configuration from the
  CLI runtime and does not prove web-server or PHP-FPM filesystem writability.

## [0.1.1] - 2026-06-09

### Fixed
- Corrected the package PHP floor to `^8.4`, matching the Laravel 13 /
  `spatie/laravel-activitylog` 5.x dependency reality exposed by public CI.
  The package never resolved cleanly on PHP 8.3; this makes the Composer
  contract honest for public installs.

## [0.1.0] - 2026-06-09

First public release — a publicly installable, pre-1.0 **developer preview**
intended for early builders. APIs may change before 1.0 (SemVer once 1.0 ships).

EvoLayer Base — the AI / ontology / blocks layer for the Laravel React Inertia
starter, part of the EvoDevOps starter-kit family. Vendor/namespace: Xuple.

### Added
- Composer package `xuple/evolayer-base` (namespace `Xuple\EvoLayer\Base`),
  extracted from the EvoDevOps development lab.
- Pluggable `AdminGate` and `UserResolver` contracts, with a default
  `SpatieAdminGate`; `evolayer.admin` route middleware delegates to the gate.
- Opt-in example features, each gated by an `EVOLAYER_BASE_EXAMPLE_*` flag and
  loaded from its own `routes/features/*.php` file (zero routes added on install
  until a flag is enabled): ThreadStudio, PRD Studio, Admin Inbox, Contact AI,
  Voice Input, AiTextField block, Marketing pages.
- Per-feature frontend publish tags (`evolayer-base-frontend-*`) plus a `-core`
  tag and an `evolayer-base-frontend` meta-tag.
- AI layer on `laravel/ai`: ThreadStudio / PRD / TextAssist agents, structured
  output, and **structured-output streaming** via a bundled `laravel/ai` patch
  (`patches/`). SSE vocabulary: `field_delta`, `field_complete`, `text_delta`,
  `done`, `error`.
- Ontology compiler (`evolayer:ontology:compile`) with multi-file namespace
  merge; Base registers `evolayer.base`.
- Ontology↔migration drift guard: package-owned ontology entities are now
  tested against their migrated columns, and `morph_to` relations are tested
  against polymorphic `*_type` / `*_id` column pairs so metadata drift is caught
  automatically.
- Console commands: `evolayer:install`, `evolayer:doctor`, `evolayer:user:promote`,
  `evolayer:ontology:compile`, `evolayer:ai:probe`, `evolayer:ai:smoke-test`,
  `evolayer:ai:stream-check`.
- Package-side `AGENTS.md` / `CLAUDE.md` with library-constrained
  Laravel Boost guidance for agent-assisted maintenance. Project-specific
  guidance (package/starter routing rule, ontology contract, AI provider
  scope, Pest-not-PHPUnit hard rule) goes first; Boost's framework block
  follows. `boost.json` declares `agents: [claude_code, codex, opencode]`
  but `mcp: false` and `skills: []` because the package has no `artisan`
  script — full MCP wiring belongs in host apps consuming the package.
- `evolayer:doctor --strict` exit-code mode. Default `evolayer:doctor` stays
  informational and always exits 0 (advisories often depend on which
  host-side features are enabled and shouldn't false-flag legitimate
  installs). `--strict` exits non-zero on any advisory, so CI surfaces
  with a fixed contract — the starter's kitchen-sink workflow,
  pre-release gates — can opt in without the grep-the-summary-line
  wrapper.
- Provider capability model (ADR-018 → ADR-019): the
  `ThreadStudioProviderPolicy` seam (the consumer-facing API for
  ThreadStudio provider eligibility, so callers no longer depend on
  `AiFeatureConfig::runtimeApprovedProviders()` directly) and a nullable
  `evolayer_base_ai_capabilities.conditions` JSON column carrying
  True / False / Unknown capability observations. (The roster change
  itself followed in ADR-020 — see Changed.)
- Shared `AiCapabilityProbe` service (with a `ProbeResult` value object,
  a `Probeable` agent interface, and a `ConditionsBuilder`) extracted
  from the three `evolayer:ai:*` commands, which previously each
  reimplemented the probe. The probe now writes a `StructuredStreaming`
  condition on every recorded probe and derives `probe_passed` from it
  (so the boolean and the conditions array cannot drift); a credentials
  short-circuit records `Unknown`, not a false `False`. The probe→ledger
  write path (creation, 24h cooldown, `--force`, stale-row supersession,
  output_mode preservation) now has feature-test coverage where it had
  none. Two latent bugs fixed at the single chokepoint: `output_mode` is
  no longer hardcoded to `json_schema` on success (hand-maintained catalogue
  modes are preserved on `--force` reprobe), and the persist no-op for
  modelless probes is now an explicit "model required" contract.

### Changed
- **Verified Runtime Strategy (ADR-020):**
  `AiFeatureConfig::runtimeApprovedProviders()` changed from
  `['anthropic', 'gemini', 'nvidia', 'opencode', 'openrouter']` to
  `['gemini', 'openai']`. Runtime-approved now means *directly verified*
  provider-specific structured streaming.
  - **OpenAI** added (matrix-verified) and selectable; OpenAI gains a
    default model via `OPENAI_CHAT_MODEL` (`gpt-4o-mini` default).
  - **Anthropic** removed from runtime-approved support and classified
    **blocked for ThreadStudio runtime / pending re-verification** — its
    structured streaming currently emits no usable `TextDelta` events, so it is
    no longer selectable in ThreadStudio (rejected with a 422 at request
    validation).
  - **NVIDIA / OpenCode / OpenRouter** removed from runtime-approved
    support and reclassified as router-backed diagnostic-eligible probe
    candidates.

  Nothing is deleted — labels, the OpenCode model catalogue, and the
  capability ledger are retained as probe/router infrastructure and for
  future adaptive mode. Smoke/probe diagnostics stay broad (any Lab
  provider), so Anthropic and the routers remain exercisable via
  `evolayer:ai:probe` / `smoke-test` / `stream-check`. **Migration
  note:** a host that set `AI_THREAD_STUDIO_PROVIDER` to anthropic,
  nvidia, opencode, or openrouter will now get a 422 from ThreadStudio;
  switch to `gemini` (default) or `openai`.
- **Explanatory provider rejection.** `ThreadStudioProviderPolicy::explain(provider)`
  returns a `ProviderAvailability` (runtime-approved / blocked / candidate /
  unknown) with a per-provider reason, wired into
  `ComposeThreadStudioRequest` — so a rejected provider gets, e.g.,
  *"Anthropic is diagnostic-eligible but blocked for ThreadStudio runtime
  and pending re-verification because structured streaming currently emits
  no usable TextDelta events."* instead of the framework's generic "selected
  provider is invalid". Provider-level only; model-level capability-ledger
  gating remains future (adaptive mode).

### Fixed
- ThreadStudio UI compose with the default provider (Gemini / OpenAI) no
  longer fails with "Provider unavailable" / the `provider default` model
  sentinel. Root cause: `mergeConfigFrom(evolayer-ai.php, 'ai')` is shallow
  at `providers.*`, so the `laravel/ai` SDK's bare provider blocks (no
  `models`) won the merge and the package's `models.text.default` was
  dropped from `config('ai')`. `AiFeatureConfig::defaultModel()` now reads
  the model default from the package's own `evolayer-ai` namespace (reliably
  populated in the host and registered in the package), falling back to
  `ai`. The CLI `evolayer:ai:stream-check` masked this because it bypasses
  `defaultModel()`. Caught by the 0.1.0 first-hour install rehearsal;
  guarded by `tests/Feature/Ai/ThreadStudioModelResolutionTest.php`.
- `stubs/ontology.yaml` `change_event` entity caught up to the actual
  migration schema: `relations.actor.type` changed from `belongs_to`
  with `target: user` to `morph_to` with `target: any` (the migration
  uses polymorphic `nullableMorphs('actor')` — actor is User by default
  but variants record Customer / Tenant / system); `tenant_id: string?`
  field added (the migration ships an RLS tenant scope column that the
  ontology never listed). The runtime model (`ChangeEvent::actor()`
  returns `morphTo()`) and recorder (`ChangeEventRecorder` writes
  `actor_type` + `actor_id`) were already correct; only the metadata
  spec was stale. Host apps (the starter included) pick up the
  corrected ontology on `composer update xuple/evolayer-base` plus a
  resync that publishes the `evolayer-base-ontology` tag.
- `stubs/ontology.yaml` now declares the remaining package-owned migration
  columns for `form_submission`, `ai_invocation`, `ai_invocation_attempt`, and
  `ai_capability` (including `honeypot`, AI subject/tenant/cost/duration fields,
  attempt provider metadata, `conditions`, and timestamps). The AI invocation
  lifecycle enums now match runtime values (`started` / `succeeded` / `failed`),
  and provider/model details live on invocation attempts rather than the parent
  invocation entity.
- Spatie compat polyfill (`Compat\{HasMedia,InteractsWithMedia,HasTags}`):
  `permission` + `activitylog` required; `medialibrary` + `tags` opt-in.
- Forward-compatible nullable invariant columns on `evolayer_base_ai_invocations`
  and `evolayer_base_change_events` (subject/actor polymorphs, `tenant_id`, cost
  columns) for future Commerce / SaaS / RLS variants.

### Security
- Admin inbox/submission routes gated behind `evolayer.admin`; all admin
  FormRequests authorize through `AdminGate` (no hardcoded `hasRole`).

### Notes
- DB tables are prefixed `evolayer_base_*`; host and Spatie tables are untouched.
- Identity finalized to EvoLayer pre-release (see `DECISIONS.md` ADR-017);
  EvoStack and EvoKit were rejected for naming collisions.
