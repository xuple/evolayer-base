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

## Cross-cutting lesson

Almost every painful cascade — namespacing breaking imports, opt-in breaking tests, per-feature routes breaking type-checks, the ontology collision — was caught by **thin Phase D probes on a real fresh starter, not by the package's own test suite** (which was green throughout at 120–129 tests). The package tests prove the code is internally coherent; only an integration run proves it installs and composes. This is the strongest argument for completing a full Phase D and the Phase E starter template before considering Base "done."

---

## Open decisions

- **Full Phase D** — a live ThreadStudio compose round-trip on a fresh starter (the thin probes covered install/build/types, not a live AI call). Blocked on a provider API key.
- **Anthropic structured-streaming verification** — blocked on credits.
- **Upstream `laravel/ai` PR** — deferred; tracked in `patches/README.md`.
- **Next family member** — Commerce Core is slated to follow Base's public release; not yet started.

*Phase E resolved — see ADR-015 and ADR-016. The `xuple/evolayer-base-starter` template is built and verified live.*
