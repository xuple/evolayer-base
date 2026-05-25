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
