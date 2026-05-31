# EvoLayer Base — Decision Record

ADR-style log of the significant decisions behind extracting EvoLayer Base into a composer package and shaping it as the foundation for a family of starters (Commerce, SaaS, RLS). Each entry: the decision, the alternatives, the choice, and the side effects it set in motion.

Status legend: **Accepted** · **Superseded** · **Open**

---

## ADR-001 — Extract to a package rather than rebase or fork

**Status:** Accepted

**Context.** EvoLayer Base lived as a long-running fork of `laravel/react-starter-kit`. Upstream had moved on (Chisel, Passkeys, pnpm, PHPUnit). Three commercial variants (Commerce, SaaS, RLS) are planned, plus paid client projects deliberately scoped to receive EvoDevOps as a clean dependency.

**Alternatives.**
- **In-place rebase** of the source repo onto upstream/main — rejected: 67 modified-starter files = high conflict surface, and it welds one repo to one starter snapshot.
- **Greenfield** fresh starter + manual port — partially absorbed.
- **Hybrid: extract a composer package first** — chosen.

**Decision.** Build `xuple/evolayer-base` as a composer library in a sibling directory (`/opt/projects/evodevops-base-pkg`), namespace `Xuple\EvoLayer\Base\`.

**Side effects.**
- Created the package repo + `PORT_INVENTORY.md` discovery doc.
- Only justifiable because of the *family* — for a single app it would be over-engineering; for siblings it's the structural prerequisite (composition over forking, namespace isolation, shared-contract seams).

---

## ADR-002 — Source repo retires as a dev lab; no atomic cutover

**Status:** Accepted (supersedes the original cutover plan)

**Context.** The first plan was "additive build, then atomic cutover" — the source repo would delete its EvoDevOps files and depend on the package.

**Decision.** The source repo retires as a development lab / reference. The public release is **the package + a thin starter template**, not this repo. No cutover.

**Side effects.**
- Eliminated the entire cutover failure-mode class (broken-repo-mid-migration, atomicity, rollback drills).
- The package build is pure-additive; if anything goes wrong we discard and retry — the source repo is never at risk.
- Redefined "Base is done": not "it runs" but "a variant can cleanly compose on it."
- The source repo has stayed untouched on `feature/ai-provider-platform` through all package work.

---

## ADR-003 — Frontend distributed as publishable composer assets

**Status:** Accepted

**Alternatives.** npm sibling package; hybrid.

**Decision.** Ship React stubs inside the package; host runs `vendor:publish`. No npm package for v1.

**Side effects.**
- Host must `vendor:publish` + apply manual host-side patches.
- **Cascaded** into the per-feature publish-tag model (ADR-012) once single-feature installs proved to break type-checks.

---

## ADR-004 — Pluggable `AdminGate` contract, not hardcoded Spatie

**Status:** Accepted

**Decision.** Define `Xuple\EvoLayer\Base\Contracts\AdminGate`; ship `SpatieAdminGate` as the default. No hardcoded `hasRole('admin')` in package logic; routes use an `evolayer.admin` middleware that delegates to the bound gate.

**Side effects.**
- Enabled "clients without Spatie permission" — which forced ADR-005 and the compat polyfill.
- Later widened with `can($user, $ability, $resource)` (ADR-009).

---

## ADR-005 — Spatie deps: core required + media opt-in

**Status:** Accepted (superseded an interim "hard-require all")

**Context.** Initial SWOT picked "core required (permission + activitylog) + media/tags opt-in". This was then talked into "hard-require everything" for simplicity — **wrong**, because the actual client projects don't use Spatie permission/tags at all.

**Decision.** `require` only `spatie/laravel-permission` + `spatie/laravel-activitylog`. `spatie/laravel-medialibrary` + `spatie/laravel-tags` move to `suggest` (+ `require-dev` for the package's own tests).

**Side effects.**
- Built the `Xuple\EvoLayer\Base\Compat\{HasMedia, InteractsWithMedia, HasTags}` polyfill (the complexity earlier deferred) — aliased to Spatie when present, no-op otherwise.
- No-op traits throw on mutation methods so a missed feature-flag gate surfaces loudly.
- `FormSubmission` imports from `Compat\*` so it autoloads identically in both modes.

---

## ADR-006 — Variant family: Base + Commerce + SaaS + RLS

**Status:** Accepted (revised twice)

**Context.** Progressed through "Base + Commerce Core + RLS" → "SaaS + RLS only, Commerce deferred" → final. The key realisation: product-sales commerce and subscription SaaS are *distinct verticals* with orthogonal domain models, not one "Commerce Core".

**Decision.** Four siblings — Base (substrate), Commerce (product sales), SaaS (subscriptions/tenants), RLS (horizontal multi-tenancy).

**Side effects.**
- Strengthened the case for RLS as a standalone package (ADR-007) — two potential consumers, not one.
- Drove the namespacing reservations (ADR-010): `evodevops.commerce.*`, `evo.saas.*`, etc.

---

## ADR-007 — RLS is a standalone horizontal package (Option A)

**Status:** Accepted (chosen over the recommended Option C)

**Alternatives (SWOT'd).**
- B — RLS lives inside SaaS.
- C — `TenantResolver` contract in Base, RLS implements (recommended).
- **A — standalone horizontal package; SaaS/Commerce depend on RLS directly** (chosen).

**Decision.** RLS owns its own tenant primitive, policies, middleware, model traits. No `TenantResolver` contract in Base.

**Side effects.**
- Base ships **forward-compatible nullable `tenant_id` columns** anyway (ADR-008) so RLS can populate them later without a migration.
- Base stays neutral; tenant enforcement is entirely RLS's concern.

---

## ADR-008 — Lock invariant schema additions now (Phase F)

**Status:** Accepted

**Context.** Cost asymmetry: a wrong opt-in default is a flag flip; a wrong invariant contract is a version bump + migrations across every variant. The user controls all consumers today — the cheapest moment to get invariants right.

**Decision.** Add nullable, additive columns now:
- `ai_invocations`: `subject_type`/`subject_id` polymorph, `tenant_id`, `prompt_tokens`, `completion_tokens`, `cost_cents`, `cost_currency`.
- `change_events`: polymorphic `actor_type`/`actor_id` (replacing `actor_user_id`), `tenant_id`.

**Side effects.**
- `ChangeEventRecorder` now takes `Authenticatable` and associates the actor polymorphically (Customer/Tenant/System possible).
- Pre-release, so migrations were edited in place rather than chasing add-column migrations.
- Columns are unused by Base today — forward compatibility for variants, not live features.

---

## ADR-009 — Widen `AdminGate` with `can()`

**Status:** Accepted

**Decision.** Add `can(?Authenticatable, string $ability, mixed $resource = null): bool`. `isAdmin()` becomes a convenience alias for `can($user, 'evolayer.admin')`. Default routes the canonical ability through `hasRole`, arbitrary abilities through Laravel's Gate facade.

**Side effects.**
- Commerce (per-resource auth) and SaaS (per-tenant auth) have a seam without re-versioning the contract later.
- The `evolayer.admin` ability is special-cased (not routed through Gate) to stay loadable for users that don't implement `Authorizable` — a documented, deliberate non-overridable.

---

## ADR-010 — Namespacing: `evolayer.base.*` / `EVOLAYER_BASE_*` / `evolayer.base.*`

**Status:** Accepted

**Decision.** Reserve clean room for variants: route names `evolayer.base.*`, env vars `EVOLAYER_BASE_*`, shared props `evolayer.base.{examples,features}`, config under `evolayer.base.*`, migration filenames include `evolayer_base_`.

**Side effects (the big cascade).**
- Wayfinder regenerated controllers at new paths → published-page `@/routes` imports broke → page rewrites.
- `use-example-nav-items` had to read `evolayer.base.examples`.
- Shared-prop types re-nested: `EvoLayerSharedProps` wraps `EvoLayerBaseSharedProps`.

---

## ADR-011 — Features opt-in, default off

**Status:** Accepted

**Context.** Product principle: a dev coming from `laravel/react-starter-kit` should not get features wired in they didn't ask for. `route:list` should not change on install.

**Decision.** Every `EVOLAYER_BASE_EXAMPLE_*` flag defaults `false`. Routes are split into per-feature files loaded only when their flag is on.

**Side effects.**
- Broke ~35 tests that assumed default-true → the test environment now explicitly enables all flags.
- Surfaced (via the thin Phase D probe) that routes had been registering unconditionally — fixed by per-feature route files.

---

## ADR-012 — Per-feature publish tags + cross-feature URL decoupling

**Status:** Accepted

**Context.** Thin Phase D on a *real* fresh starter (not Testbench) found that a single-feature install failed `tsc`: published pages imported controllers from features whose routes weren't registered, so Wayfinder hadn't generated them.

**Decision.**
- Split frontend publish into `evolayer-base-frontend-core` + per-feature tags mirroring the route files (+ a meta tag for demos).
- Decouple cross-feature usage: `ThreadStudio`/`PRD` receive `voiceInputUrl`/`aiTextAssistUrl` as **server props** (`Route::has()`-guarded, null when disabled) instead of importing the other feature's Wayfinder controller.
- `navigation.ts` (core) uses stable string URLs, not feature-controller imports, so core compiles regardless of enabled features.

**Side effects.**
- Established the install UX: a feature = **env flag + publish tag, always paired**.
- `useEvoLayerProps()` hook introduced to centralise the shared-prop cast (chosen over per-page casts or forced host type-augmentation).

---

## ADR-013 — Ontology validation: design-time hard, runtime advisory

**Status:** Accepted

**Context.** The ontology describes the full Base design surface, but the compiler validated against live routes/files — which depend on which features are toggled on and whether the frontend is published.

**Decision.** Two-tier validation: structural integrity (required keys, entity/event/workflow cross-refs, `class_exists`) hard-fails; environmental references (`Route::has`, `is_file`) become advisory warnings.

**Side effects.**
- `evolayer:ontology:compile` succeeds on a partially-enabled host, reporting disabled features as warnings rather than failing.
- The multi-file `OntologyRegistry` (variants register their own `ontology.yaml` by namespace) became live, wired into the compile command.

---

## ADR-014 — Host ontology overrides registered package namespace

**Status:** Accepted

**Context.** `evolayer:install` publishes `ontology.yaml` to the host root; `evolayer:ontology:compile` then picks it up as `--host-source`, whose declared `namespace: evolayer.base` collided with the registered package ontology and threw.

**Decision.** A host ontology whose declared namespace matches a registered package **overrides** that package's copy (publishing-to-customise is the intended workflow). A novel namespace (`app`) merges additively.

**Side effects.** Surfaced and fixed by the install command's own test — an example of integration tests catching what unit tests didn't.

---

## ADR-015 — Migrations auto-load; `evolayer:install` does not publish them

**Status:** Accepted

**Context.** The service provider both `loadMigrationsFrom()` the package *and* offered an `evolayer-base-migrations` publish tag, and `InstallCommand` published that tag by default. Publishing copied the same-named migration files into the host's `database/migrations`, so the schema was registered from two paths. Laravel dedupes by basename so `migrate` still ran, but the duplicate files shadowed the vendor copies — fragile (path order decides which wins) and noisy in a committed starter.

**Decision.** Migrations auto-load from the package via `loadMigrationsFrom()` and are **not** published by `evolayer:install`. The `evolayer-base-migrations` tag remains available for hosts who explicitly want to own and customise the schema (`vendor:publish --tag=evolayer-base-migrations`).

**Side effects.** Surfaced while building the Phase E starter (the duplicate files appeared in the host's `database/migrations`). The created app now depends on the pinned package version for its EvoDevOps schema, which `composer.lock` makes deterministic. Variant packages (Commerce, SaaS) should follow the same convention. Full package suite stayed green (129 tests / 487 assertions).

---

## ADR-016 — Starter posture: kitchen-sink + committed frontend, minimal post-create

**Status:** Accepted

**Context.** `xuple/evolayer-base-starter` is the `composer create-project` target. Two axes had to be settled: (1) which features are on — the user chose **kitchen-sink** (every `EVOLAYER_BASE_EXAMPLE_*` flag on) so the template shows the full surface; (2) whether the published React frontend is committed to the starter repo or republished on create. The original plan sketched publish-on-create (a thin starter), but publishing into directories that also hold host files (`resources/js/components`, `hooks`, …) is fragile, and `--force` re-publishing on create can clobber a user's edits.

**Decision.** Commit the published EvoDevOps frontend, the Spatie config + migrations, and the host-side patches — so the repo is clone-and-build (verified: `npm install && npm run build`, `types:check` 0 errors). Keep only genuinely generated artifacts out of git (Wayfinder `actions`/`routes`, `bootstrap/cache/*`, `resources/js/types/ontology.ts`), regenerated by a **minimal** post-create hook: `key:generate`, sqlite, `migrate --seed`, `wayfinder:generate`, `evolayer:ontology:compile`. No re-publish at create-time. `composer evolayer:resync` re-publishes the frontend to a newer package version on demand.

**Side effects.** This is the conventional starter-kit posture (matches `laravel/react-starter-kit`, Jetstream). Future family starters (Commerce, SaaS) should mirror it. Host-side integration that can't be published is pre-applied: the `evolayer` shared prop, `User` `HasRoles` (so `SpatieAdminGate` resolves admin), `useExampleNavItems()` in the sidebar, the `EvoLayer*` prop types, the `|` title separator, and a `DatabaseSeeder` that seeds the AI ledger + an admin demo user. The laravel/ai patch ships committed to the starter root and applies via `cweagans/composer-patches` (`extra.patches`, root-relative) — Option 1, chosen because composer-patches cannot resolve dependency-relative paths at install time. Verified live end-to-end: install applies the patch, migrate+seed, admin gate resolves, build, `evolayer:doctor` all-green, 39 upstream tests pass.

---

## ADR-017 — Final public identity: `Xuple\EvoLayer\Base` (`xuple/evolayer-base`)

**Status:** Accepted (supersedes the pre-release `EvoDevOps\Base` working identity and ADR-010's `evodevops.*` scheme)

**Context.** The package was built under the working identity `EvoDevOps\Base` / `evodevops/base`. Before release the product family needed a name distinct from the **EvoDevOps** teaching/site brand and the **Xuple** legal/vendor entity. Two candidates were tried and rejected as **fatal collisions**: **EvoStack** (`github.com/evothings/evostack`) and **EvoKit** (the `evokit` npm package is a React-blocks UI library — a head-on collision in this product's exact domain). A vetted slate of 8+ names was collision-checked across GitHub / npm / Packagist / web; **EvoLayer** was the only candidate clean across all axes (and "layer" is semantically apt — the package is an additive AI/ontology/blocks layer).

**Decision.** Final naming matrix:
- Teaching/site brand: **EvoDevOps** (unchanged) — appears in docs/marketing only.
- Vendor/legal: **Xuple** — Composer vendor, PHP namespace root, copyright.
- Product family: **EvoLayer**.
- Composer: `xuple/evolayer-base`, `xuple/evolayer-base-starter`. Namespace `Xuple\EvoLayer\Base`.
- Runtime: commands `evolayer:*` (incl. `evolayer:ontology:compile`, `evolayer:ai:*`); routes `evolayer.base.*`; middleware/ability `evolayer.admin`; config `config/evolayer{,-ai}.php` merged under `evolayer` (AI keeps the SDK's `ai` key); env `EVOLAYER_BASE_*`; publish tags `evolayer-base-*`; ontology namespace `evolayer.base`.
- Frontend: `pages/evolayer/*`, `types/evolayer.d.ts`, `useEvoLayerProps()`, shared prop `page.props.evolayer.base.*`, types `EvoLayer*`, Wayfinder imports `@/actions/Xuple/EvoLayer/Base` + `@/routes/evolayer`.
- **DB tables prefixed `evolayer_base_*`** (user-approved widening of ADR-008/ADR-010 — table *names* now namespaced, not just migration filenames; Spatie/host tables untouched). Models carry explicit `$table`.

**Retained old names (deliberate).** Bare **EvoDevOps** as the teaching brand in docs/marketing; the physical repo directories `/opt/projects/evodevops-base-{pkg,starter}` and the starter's `../evodevops-base-pkg` path-repo URL (filesystem ≠ package identity); the `laravel/ai` SDK's `ai` config key.

**Side effects.**
- Executed as a phased migration (backend namespace → runtime tokens + tables → frontend → docs → starter) gated by a **stale-name audit loop** with an expanded term list (the originally-proposed audit list missed `props.evo`, the `'evo'` shared-prop key, and the `Evo*` type names).
- Two classes of bug surfaced only at the end and were caught by the loop + `tsc`: a bash single-quote-escaping failure left route-import *bindings* un-renamed (path fixed, binding not), and the over-broad `evo.base`→`evolayer.base` rule orphaned a local `const evo` variable. Both fixed.
- 142 package tests green; starter `tsc` clean, build OK, 39 tests pass, `evolayer:doctor` all-green, 20 `evolayer.base.*` routes registered.

---

## ADR-018 — AI provider tiering: smoke ≠ endorsement; ThreadStudio stays curated

**Status:** Superseded by ADR-019 (kept for history — the five-tier framing, smoke-≠-endorsement, and structural-inference rules carry forward; ADR-019 names the abstractions, adds conditions-lite vocabulary, adds the no-runtime-fallback guardrail, and mandates the `ThreadStudioProviderPolicy` seam).

**Context.** The package exposes AI providers across several surfaces with implicit-but-mismatched eligibility rules:

- `evolayer:ai:stream-smoke {provider}` accepts any `Laravel\Ai\Enums\Lab` value (the SDK's provider enum). Its `#[Signature]` docstring explicitly says "Provider key (gemini, openai, anthropic, ...)".
- `patches/README.md` carries a hand-maintained structured-streaming verification matrix. At HEAD, only **Gemini** and **OpenAI** have ✅ rows. **Anthropic** is explicitly recorded as failing (zero `TextDelta` events, empty final payload).
- `Xuple\EvoLayer\Base\Support\AiFeatureConfig::supportedProviders()` returns `['anthropic', 'gemini', 'nvidia', 'opencode', 'openrouter']` — the curated set used by `ComposeThreadStudioRequest` validation (`Rule::in(...)`), the `AiSmokeTest` probe loop, and the `AiProbeCommand` capability scan. This list was last set when `config/evolayer-ai.php`'s rationale comment was written, which says NVIDIA / OpenCode / OpenRouter are present to "prove OpenAI-compatible Chat Completions and model-router style paths."
- The capability ledger (`evolayer_base_ai_capabilities` via `AiProbeCommand`) records per-agent probe results but does not currently gate ThreadStudio eligibility at request time.

Three real tensions follow from this surface:

1. **OpenAI passes structured streaming** (matrix ✅) **but is not in `supportedProviders()`** — a host setting `AI_THREAD_STUDIO_PROVIDER=openai` is rejected at request validation, while `evolayer:ai:stream-smoke openai` works.
2. **Anthropic is in `supportedProviders()` despite failing structured streaming** — a host setting `AI_THREAD_STUDIO_PROVIDER=anthropic` is accepted by validation and then degrades silently in production.
3. **NVIDIA / OpenCode / OpenRouter sit in `supportedProviders()` without their own matrix rows.** Their inclusion is **structural inference**: they share OpenAI's Chat-Completions code path, so if OpenAI streams structured output they should too. Defensible but not empirically verified per-provider.

**Decision.** Treat AI-provider eligibility as **five concentric tiers**, with explicit promotion rules between them. This ADR documents the tiers and the policy; the roster change (which providers actually live in `supportedProviders()`) is a separate decision tracked under "Open decisions" below.

| Tier | Source of truth | What it means | Where consumed |
| --- | --- | --- | --- |
| 1. SDK-known providers | `Laravel\Ai\Enums\Lab` | Anything the upstream SDK can route to. | `evolayer:ai:stream-smoke {provider}` accepts these. |
| 2. Diagnostic smoke providers | `evolayer:ai:stream-smoke` argument set (= Tier 1 today) | Anyone can run the smoke against any Lab provider for diagnostic purposes only. **Smoke is not endorsement.** | The smoke command. Nothing else. |
| 3. Structured-streaming verified providers | `patches/README.md` matrix | Live-tested end-to-end with the patched `laravel/ai` flow. Currently Gemini and OpenAI. | Documentation only. Membership here is *eligibility for* Tier 4 consideration, not automatic promotion. |
| 4. Curated ThreadStudio providers | `AiFeatureConfig::supportedProviders()` | Allowed as `AI_THREAD_STUDIO_PROVIDER`. Pass `ComposeThreadStudioRequest` validation. Iterated by `AiSmokeTest` and `AiProbeCommand`. **Curation is a separate decision from streaming verification.** | Request validation, probe loops, host UI selection. |
| 5. Locally verified / adaptive providers (future) | The capability ledger (`evolayer_base_ai_capabilities`) | Future: hosts could opt into runtime gating where ThreadStudio rejects a configured provider if its most recent probe failed. Out of scope until the capability ledger is wired into request validation. | Future runtime gate. |

**Policy rules from this tiering:**

- **Smoke stays broad.** The diagnostic surface should accept any Lab provider so contributors can test new providers before promotion. The smoke command will never gate on `supportedProviders()`.
- **ThreadStudio stays curated.** Whatever's in `supportedProviders()` is the contract a host sees. The default `AI_THREAD_STUDIO_PROVIDER=gemini` env stays the lowest-friction first-run path.
- **Passing structured streaming = eligibility for ThreadStudio consideration, not automatic promotion.** A provider can pass the matrix and still not be curated (e.g. because of cost, quota, model-availability, or contributor capacity to maintain its UX surface). Promotion to Tier 4 is a deliberate roster decision.
- **Demotion follows verification.** A provider currently in `supportedProviders()` should be removed if its verification status moves from passing to failing — presenting a "supported" UI selection for a provider known to fail in production is a user-visible bug.
- **Structural inference is acceptable but documented.** Router-style providers (currently NVIDIA, OpenCode, OpenRouter) may live in `supportedProviders()` on the basis of sharing OpenAI's verified code path, but this ADR records that the inheritance is structural (same SDK code path) not empirical (no per-provider matrix run). If a router provider's behavior diverges from OpenAI's (e.g. a router strips schema, or rate-limits the streaming endpoint), it loses inheritance and needs its own matrix row.
- **The capability ledger is informational today, gating tomorrow.** Tier 5 is a placeholder: until hosts can opt into runtime gating, the curated list is the only contract that matters at request time.

**Side effects.**

- This ADR is **policy-only**. `AiFeatureConfig::supportedProviders()` is NOT changed by this commit. The current list (`anthropic`, `gemini`, `nvidia`, `opencode`, `openrouter`) carries forward unchanged, with the three tensions above explicitly catalogued as open issues until the roster proposal is reviewed.
- The roster change (whether to add OpenAI, whether to remove Anthropic, whether router-style providers stay under structural inference or require empirical matrix runs) is a separate follow-up. A proposal with explicit options — A: keep as-is, B: remove Anthropic only, C: add OpenAI + remove Anthropic, D: require ThreadStudio probes for all curated providers, E: introduce adaptive / verified runtime mode — will follow this ADR for human review.
- No verification-gated code changes. No tests added for `supportedProviders()` shape. The capability ledger continues to be informational.
- A future ADR-019+ will record the chosen roster after the proposal is reviewed.

---

## ADR-019 — Provider capability model: abstractions before roster

**Status:** Accepted (supersedes/extends ADR-018; ships the policy seam + conditions-lite vocabulary; the roster change is still a separate follow-up — see "Open decisions")

**Context.** ADR-018 fixed the *tiering policy* (five tiers, smoke ≠ endorsement, demotion follows verification) but stopped at policy. After review, the leak is wider than tiering alone:

- `AiFeatureConfig::supportedProviders()` is a single method called from at least four places — `ComposeThreadStudioRequest` (request validation), `AiSmokeTest::runAllProviders` (smoke iteration), `AiProbeCommand::runAllProviders` (probe iteration), and `ThreadStudioAiConfig` (metadata loop) — plus the `patches/README.md` matrix as a documentation consumer. One method name carries five distinct meanings: SDK-known / diagnostically smokable / probe-iteration default / curated product surface / verified.
- `AiCapability` already exists as an observations-shaped table (`provider`, `model`, `agent_class`, `schema_hash`, `status`, `output_mode`, `probe_passed: boolean`, `failure_reason`, `latency_ms`, `note`, `probed_at`, `superseded_at`) with `status ∈ {supported, experimental, blocked, unknown}` and `output_mode ∈ {json_schema, json_object, prompt_json, unsupported, unknown}`. The schema is already multi-state; only `probe_passed` is a boolean that can't express `Unknown`.
- `AiStreamSmokeTest` accepts any `Lab` provider (already decoupled from `supportedProviders()`), establishing precedent that the diagnostic surface is broader than the curated surface.
- Pre-0.1 is the cheapest window to surface the right abstractions. After public release, the `AiCapability` schema, the `status` enum values, and the public-method name `supportedProviders()` all become compatibility commitments for downstream hosts that have started persisting capability rows or reading the method.

ADR-018's policy framing is correct but incomplete: it names the tiers but doesn't name the seams. Future surfaces (`doctor --json`, receipts, admin probing UI, agent-readable failure messages, the starter's first-run readiness screen) will all need vocabulary for "provider exists / provider tested / provider untested / provider blocked / provider stale." Defining that vocabulary now is cheaper than refactoring after each surface invents its own.

**Decision.** Introduce a named abstraction model with seven boundaries; document it; ship the minimum policy seam now; leave most implementation for follow-up ADRs and commits.

### The seven abstraction boundaries

1. **Provider registry** — static knowledge: labels, SDK-known providers (`Laravel\Ai\Enums\Lab`), provider families, diagnostic eligibility. Today this lives implicitly in `AiFeatureConfig` (labels + `supportedProviders()`) and in the SDK's `Lab` enum (SDK-known). No new class today; the boundary is documented for future extraction.
2. **Capability ledger** — observed facts only. The existing `evolayer_base_ai_capabilities` table + `AiCapability` model is the ledger. The ledger does NOT decide product policy; it records what was probed and what happened.
3. **Conditions-lite vocabulary** — observations are expressed as `(type, status, reason, message, schema_hash, observed_at)` tuples with `status ∈ {True, False, Unknown}`. Adapted from the Kubernetes API conditions pattern (without the full controller / `observedGeneration` / `lastTransitionTime` machinery — those are valuable when there are multiple producers and runtime reconciliation; we have neither). The trichotomy is the load-bearing piece: it lets the ledger express "router provider untested" without conflating it with "provider tested and failed."
4. **`ThreadStudioProviderPolicy`** — feature-specific product policy. Decides what ThreadStudio allows, why, and what rejection message to show. Wraps `AiFeatureConfig::supportedProviders()` today; can grow to consult the capability ledger when adaptive mode lands (ADR-020+). **Mandatory now**, not optional. New consumers (e.g. an admin UI's "why is this provider greyed out?") read the policy, not the underlying list.
5. **Runtime selection** — no silent fallback across providers. The runtime uses the explicit configured `provider` + `model` for the request. Adaptivity happens at configuration time, validation time, and probe time — never inside the live request path. (A `try-Gemini-then-OpenAI-then-Anthropic` runtime loop would create hidden cost, privacy, and debuggability problems and is explicitly forbidden.)
6. **Feature flags** — `EVOLAYER_BASE_EXAMPLE_THREAD_STUDIO=true` exposes the ThreadStudio surface. It does NOT prove AI provider capability. Future readiness screens compose feature-enabled AND provider-capable as separate predicates — a feature can be visible but report "no usable provider configured."
7. **Probe entrypoints** — Artisan commands (`evolayer:ai:probe`, `evolayer:ai:smoke-test`, `evolayer:ai:stream-smoke`) and future in-app / admin probing must share probe logic. If/when an admin UI ships, it doesn't reimplement probing — it queues the same probe service the CLI uses. Today this is one shared codebase; the future-extracted `AiCapabilityProbe` service is the named seam.

### Conditions-lite vocabulary

A capability row records observations as a collection of typed conditions. First-cut types:

| Type | What it observes |
| --- | --- |
| `SdkKnown` | Provider exists in `Laravel\Ai\Enums\Lab` |
| `CredentialsConfigured` | Provider API key resolves at request time |
| `PatchPresent` | `vendor/laravel/ai/.../StreamsText.php` contains the `JsonSchemaTypeFactory` marker |
| `StructuredStreaming` | The patched flow emits `TextDelta` events end-to-end |
| `ThreadStudioSchemaValid` | The current `ThreadStudioAgent` schema hash passed structured streaming against this provider/model |

`ThreadStudioReady` is **not** a stored ledger condition — it is a **policy-derived availability result** computed by `ThreadStudioProviderPolicy` from observed conditions plus the curated roster. Keeping it out of the table preserves the ledger-records-observations / policy-decides rule: a row records what a probe saw, not whether the product currently allows the provider. `ThreadStudioReady` may surface in future doctor/receipt output, computed on demand.

Each condition is `(type, status ∈ {True, False, Unknown}, reason, message, schema_hash?, observed_at)`. `Unknown` is the load-bearing value — it distinguishes "untested" from "tested and failed." A provider with no probe row is Unknown; a probe row with `probe_passed=false` is False; a probe row with `probe_passed=true` is True for the conditions its probe exercised.

Conditions stored on `AiCapability` describe the probe context for that `provider × model × agent × schema_hash` row — what was true at probe time. Some condition *types* are environment-wide (`PatchPresent`, `CredentialsConfigured`) rather than provider-specific; when stored on a capability row they record what was observed *during that probe*, not universal system truth. Environment-wide health may also surface separately in future doctor/receipt output, but the capability row is always the per-probe snapshot.

The column and vocabulary landed first (separately from any producer). The producer now exists: `AiCapabilityProbe` (the shared service extracted from the three `evolayer:ai:*` commands) synthesises a `StructuredStreaming` condition via `ConditionsBuilder` and `persist()` writes it on every recorded probe, with `probe_passed` derived from that condition's status so the boolean and the conditions array cannot drift. Only the `StructuredStreaming` condition is synthesised today; future probes add the remaining types (`CredentialsConfigured`, `PatchPresent`, `ThreadStudioSchemaValid`). The `Unknown` vs `False` distinction is enforced at the producer: the credentials short-circuit records `Unknown` (the agent was never run), never `False`.

### Current provider classification (no roster change)

| Provider | Classification |
| --- | --- |
| Gemini | Curated default; structured-streaming verified (matrix ✅). |
| OpenAI | Structured-streaming verified (matrix ✅); eligible for ThreadStudio consideration; **not** automatically curated. |
| Anthropic | Diagnostic-known; ThreadStudio pending/blocked until structured streaming emits TextDelta events. **Still in `supportedProviders()` today** — roster change deferred per the explicit "no roster change in this commit" rule. |
| NVIDIA, OpenCode, OpenRouter | Structural / router candidates (share OpenAI's Chat-Completions code path); **Unknown** until directly probed for structured streaming on the actual model the router resolves to. |

This is classification only — `AiFeatureConfig::supportedProviders()` carries forward unchanged. The roster change (whether to add OpenAI, whether to remove Anthropic, whether the router providers stay under structural inference or require their own matrix runs) is the next decision and will be presented as options A-E in a follow-up message.

### Audit — `AiSmokeTest` and `AiProbeCommand` use of `supportedProviders()`

- `AiSmokeTest::runAllProviders` (`src/Console/Commands/AiSmokeTest.php:45`): iterates the curated list. **Defensible today** — the intent matches "smoke the providers the package endorses." A separate diagnostic-eligibility list could replace it later if needed, but no urgent fix.
- `AiProbeCommand::runAllProviders` (`src/Console/Commands/AiProbeCommand.php:157`): iterates the curated list to probe each. **Direction-of-flow mismatch** — when the probe becomes operational against arbitrary providers (currently in-progress per the `TODO (Step 2+)` comment), the probe should iterate diagnostic-eligible providers and write conditions; the curated list should be the *output* of probing (or a deliberate policy decision on top of probe results), not the input. Today's coupling is acceptable because the probe is still wiring up persistence; flag as known-issue for follow-up.

No code change to either today. The behaviour-preserved `ThreadStudioProviderPolicy` seam (this ADR's mandated commit) only re-routes `ComposeThreadStudioRequest`; the iteration callers stay on the same underlying list, with the docblock on `supportedProviders()` clarifying what that list currently means.

### What this ADR ships

- **DECISIONS.md**: this ADR (ADR-019), supersedes ADR-018 with an explicit pointer.
- **`Xuple\EvoLayer\Base\Support\ThreadStudioProviderPolicy`** (new class): wraps `AiFeatureConfig::supportedProviders()` for now. First public method is `curatedProviders(): array`; the class is the future home for `explain($provider, $model): ProviderAvailability` and capability-ledger consultation, neither of which lands in this ADR's commit.
- **`ComposeThreadStudioRequest`**: rewired to depend on `ThreadStudioProviderPolicy::curatedProviders()` instead of calling `AiFeatureConfig::supportedProviders()` directly. Behaviour identical.
- **`AiFeatureConfig::supportedProviders()`**: gets a clarifying docblock — "Returns the curated ThreadStudio provider list. Not all SDK-known or diagnostic providers; see `ThreadStudioProviderPolicy` for the consumer-facing API."
- **`AiCapability` migration**: nullable `json('conditions')` column added (additive; preserves `probe_passed` as a backwards-compat projection). Cost: ~30 lines per the evaluation. No consumer writes conditions yet; the column is forward infrastructure for the probe's future evolution.
- **`AiCapability` model**: adds `'conditions' => 'array'` cast.
- **`AGENTS.md` / `CLAUDE.md`**: a new short rule above the Boost-generated block — "Observed capability is not product policy" — cross-linking ADR-019.
- **Tests**: a unit test for `ThreadStudioProviderPolicy` (delegates to `AiFeatureConfig` with current behaviour); a unit test for `AiCapability` conditions cast (nullable insert + array roundtrip).

### Non-goals

This ADR explicitly does NOT:

- Change `AiFeatureConfig::supportedProviders()`. Anthropic stays, OpenAI is not added.
- Implement adaptive mode (capability-ledger-driven ThreadStudio gating).
- Build admin / in-app probing UI.
- Build `doctor --json` or receipts.
- Extract `ProviderRegistry`, `AiCapabilityProbe`, or `AiCapabilityLedger` as separate named classes. They remain implicit inside `AiFeatureConfig`, `AiProbeCommand`, and the `AiCapability` model respectively. The boundaries are documented; extraction is future work that happens when a second consumer needs the seam.
- Rename `supportedProviders()`. Renaming with a compatibility alias is a clean future change; deferred to avoid scope creep here.
- Wire `AiProbeCommand` to write conditions. The column lands; the probe-to-conditions write path is the next probe-evolution commit.
- Change the `.env.example` defaults from the package side (the starter owns those).

### Side effects

- This ADR is policy-first but ships a small amount of code: one new class, one rewire, one nullable column, one model cast, two tests, plus the agents-doc update. The diff is bounded (~150 lines including the ADR itself).
- Pest stays green throughout (149 / 531 baseline at HEAD).
- Future ADR-020+ will record the chosen roster (the A-E follow-up), the policy's `explain()` method, and adaptive mode.

### Update — probe-evolution follow-up (landed after the original ADR commit)

The two non-goals scoped as "next probe-evolution commit" subsequently landed, behaviour-preservingly, as their own commits:

- **`AiCapabilityProbe` extracted as a named class** (`src/Ai/AiCapabilityProbe.php`), with a `ProbeResult` value object, a `Probeable` agent interface, and a `ConditionsBuilder`. This was promoted from "future work when a second consumer needs the seam" to "now" because the three `evolayer:ai:*` commands were already that second/third consumer — the duplication was real, not hypothetical. `AiProbeCommand` and `AiSmokeTest` now delegate; `AiStreamSmokeTest` stays separate.
- **The conditions producer is wired**: `persist()` writes the `StructuredStreaming` condition and derives `probe_passed` from it. The probe→ledger write path — previously untested — now has feature coverage (creation, cooldown, `--force`, supersession, conditions tuple, output_mode preservation).
- Two latent bugs were fixed at the single chokepoint: `output_mode` is no longer hardcoded to `json_schema` on success (curated catalogue modes are preserved), and the persist no-op is now an explicit "model required" contract.

Still deferred to ADR-020+: the roster change (A-E), the policy's `explain()`/`availability()` method, adaptive mode, `doctor --json`/receipts, and enabling `--persist` for non-OpenCode providers in the all-providers path (roster-coupled).

---

## Cross-cutting lesson

Almost every painful cascade — namespacing breaking imports, opt-in breaking tests, per-feature routes breaking type-checks, the ontology collision — was caught by **thin Phase D probes on a real fresh starter, not by the package's own test suite** (which was green throughout at 120–129 tests). The package tests prove the code is internally coherent; only an integration run proves it installs and composes. This is the strongest argument for completing a full Phase D and the Phase E starter template before considering Base "done."

---

## Open decisions

- **Full Phase D** — a live ThreadStudio compose round-trip on a fresh starter (the thin probes covered install/build/types, not a live AI call). Blocked on a provider API key.
- **Anthropic structured-streaming verification** — blocked on credits.
- **Upstream `laravel/ai` PR** — deferred; tracked in `patches/README.md`.
- **AI provider roster** — ADR-018 fixed the *tiering policy*; ADR-019 ships the abstractions and the `ThreadStudioProviderPolicy` seam. The *roster change* (Anthropic / OpenAI / router-tier decisions) is the next follow-up. Options A–E remain on the table for human review before any change to `AiFeatureConfig::supportedProviders()`.
- **`AiProbeCommand` iteration direction** — still iterates the curated list (`supportedProviders()`) in the all-providers path. The `TODO (Step 2+)` comment is gone (the probe logic is now the shared `AiCapabilityProbe` service and the persist no-op is an explicit contract), but the direction-of-flow point stands: the probe should iterate diagnostic-eligible providers and write conditions, with curation as the *output* of probing, not the input. Coupled to enabling `--persist` for non-OpenCode providers — both wait on the roster decision.
- **Next family member** — Commerce Core is slated to follow Base's public release; not yet started.

*Phase E resolved — see ADR-015 and ADR-016. The `xuple/evolayer-base-starter` template is built and verified live.*
