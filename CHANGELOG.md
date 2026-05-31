# Changelog

All notable changes to `xuple/evolayer-base` are documented here. The format is
based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and this
project aims to follow [Semantic Versioning](https://semver.org/).

## [Unreleased]

Provisional first release: **0.1.0** (not yet tagged or published — see
`RELEASE.md`).

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
- Console commands: `evolayer:install`, `evolayer:doctor`, `evolayer:user:promote`,
  `evolayer:ontology:compile`, `evolayer:ai:probe`, `evolayer:ai:smoke-test`,
  `evolayer:ai:stream-smoke`.
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
  `AiFeatureConfig::supportedProviders()` directly) and a nullable
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
  no longer hardcoded to `json_schema` on success (curated catalogue
  modes are preserved on `--force` reprobe), and the persist no-op for
  modelless probes is now an explicit "model required" contract.

### Changed
- **Curated ThreadStudio provider roster (ADR-020, D-prime):**
  `AiFeatureConfig::supportedProviders()` changed from
  `['anthropic', 'gemini', 'nvidia', 'opencode', 'openrouter']` to
  `['gemini', 'openai']`. Curated now means *directly verified*
  provider-specific structured streaming.
  - **OpenAI** added (matrix-verified) and selectable; OpenAI gains a
    default model via `OPENAI_CHAT_MODEL` (`gpt-4o-mini` default).
  - **Anthropic** removed from curated support and classified
    **blocked/pending** — its structured streaming currently emits no
    usable `TextDelta` events, so it is no longer selectable in
    ThreadStudio (rejected with a 422 at request validation).
  - **NVIDIA / OpenCode / OpenRouter** removed from curated runtime
    support and reclassified as OpenAI-compatible router / probe
    candidates.

  Nothing is deleted — labels, the OpenCode model catalogue, and the
  capability ledger are retained as probe/router infrastructure and for
  future adaptive mode. Smoke/probe diagnostics stay broad (any Lab
  provider), so Anthropic and the routers remain exercisable via
  `evolayer:ai:probe` / `smoke-test` / `stream-smoke`. **Migration
  note:** a host that set `AI_THREAD_STUDIO_PROVIDER` to anthropic,
  nvidia, opencode, or openrouter will now get a 422 from ThreadStudio;
  switch to `gemini` (default) or `openai`. The explanatory per-provider
  rejection message (`ThreadStudioProviderPolicy::explain()`) is the next
  policy method and is not yet built.

### Fixed
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
