# Changelog

All notable changes to `xuple/evolayer-base` are documented here. The format is
based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and this
project aims to follow [Semantic Versioning](https://semver.org/).

## [Unreleased]

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
