# EvoLayer Base — Port Inventory

> **Archived pre-pivot inventory.** This file records the May 2026 extraction from
> `/opt/projects/evodevops-base-l14-i3` into the package/starter split. It
> intentionally preserves some pre-pivot names such as `EvoDevOps additions`,
> `evo` props, and the original source path as historical extraction context.
> Current public/runtime contracts live in `README.md`, `DECISIONS.md`, and the
> starter docs.

Produced 2026-05-21 against:

- **Source**: `/opt/projects/evodevops-base-l14-i3` @ `feature/ai-provider-platform` (commit `95d2a33`)
- **Target**: `laravel/react-starter-kit` `main` @ `37ec697` ("Fix Teams email verification redirect")

Methodology: walked both file trees (excluding `vendor/`, `node_modules/`, `storage/`, `bootstrap/cache/`, `bootstrap/ssr/` build output, `public/build/`, `public/fonts/`, generated Wayfinder helpers, `.git/`, `.claude/skills/`, `.agents/skills/`). 516 source files in this repo vs 174 in upstream.

## Scoring

| Bucket | Count | Meaning |
| ------ | ----- | ------- |
| Identical to upstream | 97 | Starter files we haven't touched — pure inheritance |
| Modified starter files | 67 | In both repos but we've changed them |
| Upstream-only | 10 | Added to the starter since this repo forked |
| EvoDevOps additions | 187 | Files unique to this repo (after filtering noise) |

## What upstream added since the fork (10 files)

The latest starter ships features this repo doesn't have yet. The package extraction does **not** need these (they're starter responsibilities), but worth knowing the gap:

- **Passkeys** (`@laravel/passkeys`): `database/migrations/2024_01_01_000000_create_passkeys_table.php`, `resources/js/components/{manage-passkeys,passkey-item,passkey-register,passkey-verify,manage-two-factor}.tsx`
- **Chisel installer**: `chisel.php`, `chisel-paths.php`, `app/Console/Commands/InstallFeaturesCommand.php` — Laravel's new install-time scaffolding tool
- **`HandleAppearance` middleware**: `app/Http/Middleware/HandleAppearance.php`

Also: upstream switched to **pnpm** (`pnpm-workspace.yaml`), uses **PHPUnit** (not Pest) by default, dropped `nunomaduro/pao` in favour of `laravel/pao`, added `babel-plugin-react-compiler`.

## Modified starter files (67)

Roughly four categories:

### Auth & settings flow (39 files)
Layout tweaks, Wayfinder helpers, Toast integration, role-aware UI:
- `app/Http/Controllers/Settings/{Profile,Security}Controller.php`
- `app/Providers/FortifyServiceProvider.php`
- `app/Models/User.php` (HasRoles trait, factory states)
- `config/fortify.php`, `config/inertia.php`
- `database/factories/UserFactory.php`, `database/seeders/DatabaseSeeder.php`
- `resources/js/pages/auth/*.tsx` (7 files), `resources/js/pages/settings/*.tsx` (3 files)
- `resources/js/layouts/auth/*.tsx` (3 files), `resources/js/layouts/settings/layout.tsx`
- `tests/Feature/Auth/*.php` (7 files), `tests/Feature/Settings/*.php` (2 files)

### App shell (12 files)
The sidebar/header/branding layer the EvoDevOps shell sits on:
- `resources/js/app.tsx`, `resources/js/layouts/app-layout.tsx`
- `resources/js/components/{app-header,app-logo,app-sidebar,nav-main,two-factor-setup-modal}.tsx`
- `resources/js/hooks/{use-appearance,use-flash-toast}.tsx`
- `resources/js/types/{global.d.ts,auth.ts,index.ts,navigation.ts}`
- `resources/js/pages/{welcome,dashboard}.tsx`
- `resources/views/app.blade.php`, `resources/css/app.css`

### Backend wiring (8 files)
- `app/Http/Middleware/HandleInertiaRequests.php` (shared props: evo, flash, ontology surface)
- `bootstrap/app.php` (middleware registration)
- `routes/{web,settings}.php`
- `database/migrations/0001_01_01_000001_create_cache_table.php`, `0001_01_01_000002_create_jobs_table.php`
- `composer.json`, `package.json`, `vite.config.ts`

### Repo metadata (8 files)
- `.env.example`, `.gitignore`, `eslint.config.js`, `pnpm-workspace.yaml`, `README.md`
- `.github/workflows/{lint,tests}.yml`
- `tests/{Feature/{Dashboard,Example}Test.php,Unit/ExampleTest.php}`

## EvoDevOps additions (187 files)

### Backend (`app/`, 49 files)
- **`app/Ai/Agents/`** (6) — `ThreadStudioAgent`, `PrdAgent`, `TriageAgent`, `SubjectHintsAgent`, `TextAssistAgent`, `MediaAnalysisAgent`
- **`app/Console/Commands/`** (6) — `OntologyCompileCommand`, `AiProbeCommand`, `AiSmokeTest`, `Ai/AiStreamSmokeTest`, `FontsSelfHost`, `PromoteUserCommand`
- **`app/Http/Controllers/`** (7) — `ContactController`, `Admin/{Inbox,Prd,Submissions}Controller`, `Ai/{ThreadStudio,VoiceInput,AiTextAssist}Controller`
- **`app/Http/Requests/`** (6) — `ContactFormRequest`, `Admin/{GeneratePrd,SearchInbox}Request`, `Ai/{ComposeThreadStudio,StreamTextAssist,TranscribeAudio}Request`
- **`app/Http/Middleware/`** (1) — `EnsureExampleEnabled`
- **`app/Http/Responses/`** (1) — `LogoutResponse`
- **`app/Jobs/`** (2) — `TriageFormSubmissionJob`, `ProcessMediaAttachmentsJob`
- **`app/Models/`** (5) — `FormSubmission`, `AiInvocation`, `AiInvocationAttempt`, `AiCapability`, `ChangeEvent`
- **`app/Support/`** (15) — `ThreadStudio*` (6), `PartialJsonExtractor`, `AiInvocationRecorder`, `AiCapabilityHash`, `AiFeatureConfig`, `OntologyCompiler`, `ChangeEventRecorder`, `FormSubmissionSearch`, `PrdGenerator`, `Appearance`, `Toast`

### Database (16 files)
- 12 migrations (form submissions, ai invocations + attempts, ai capabilities, change events, media library, permissions, tags, triage, embedding column)
- 2 seeders (`AiCapabilitySeeder`, role/permission seeder)
- 2 factories (`FormSubmissionFactory`, etc.)

### Frontend (`resources/js/`, 31 files)
- **`blocks/`** (5) — `streaming-card`, `ai-triage`, `voice-input`, `semantic-search`, `ai-text-field`
- **`pages/`** (8) — `home`, `about`, `contact`, `contact-thank-you`, `admin/{inbox/,prd,submissions/{index,show}}`, `ai/thread-studio`
- **`components/`** (4) — `command-bar`, `command-palette-dialog`, `error-boundary`, `ui/command`
- **`hooks/`** (3) — `use-example-nav-items`, `use-thread-studio-stream`, `use-typewriter`
- **`config/`** (3) — `command-palette`, `docs`, `navigation`
- **`providers/`** (1) — `command-palette-provider`
- **`layouts/`** (1) — `public-layout`
- **`lib/`** (2) — `appearance`, `platform`
- **`types/`** (2) — `layout`, `ontology` (generated)
- **`wayfinder/`** (1) — `index.ts`
- **`css/`** (1) — `fonts.css`

### Tests (43 files)
- `tests/Feature/` (30) — covers contact, contact form, dashboard, form submission, AI streaming, change events, voice input, ontology, capability ledger, inbox, PRD, etc.
- `tests/Unit/` (8) — partial JSON extractor, ontology spec, AI config, capability hash, etc.
- `tests/Browser/` (3), `tests/Postgres/` (2), `tests/Pest.php` (1)

### Tooling, docs, infra (48 files)
- **`scripts/`** (13) — setup, verification, lint, ontology cleanliness, sqlite/writable-paths repair
- **`docs/`** (8) — setup lanes, local nginx + Vite, production-shaped local, etc.
- **`config/`** (6) — `evo.php`, `ai.php`, `permission.php`, `media-library.php`, `activitylog.php`, `tags.php`
- **`patches/`** (2) — vendor patch + README
- **`.codex/`** (2), **`.claude/`** (1) — agent tooling
- Root files: `ontology.yaml`, `Makefile`, `phpunit-pgsql.xml`, `CLAUDE.md`, `AGENTS.md`, `boost.json`, `opencode.json`, `.mcp.json`, `.nvmrc`, `composer.lock`, `package-lock.json`, `patches.lock.json`, `.env`

## Proposed package boundary

### `xuple/evolayer-base` (composer package)

**Includes:**
- All of `app/Ai/`, `app/Jobs/`, `app/Models/` (5 EvoDevOps models), `app/Support/` (15 classes), `app/Http/Controllers/{Admin,Ai}/`, `app/Http/Controllers/ContactController`, `app/Http/Requests/{Admin,Ai}/`, `app/Http/Requests/ContactFormRequest`, `app/Http/Middleware/EnsureExampleEnabled`, `app/Console/Commands/{Ai/,OntologyCompileCommand,AiProbeCommand,AiSmokeTest,PromoteUserCommand}`
- All EvoDevOps migrations, seeders, factories
- `config/{evo,ai}.php`
- `patches/` (vendor patch + composer-patches integration)
- `ontology.yaml` schema + compiler — published or shipped as-is
- A service provider that registers routes (under prefix), middleware aliases, console commands, publishable assets
- PHP tests covering the package surface

**Publishes to host (`vendor:publish --tag=evolayer-base-frontend`):**
- All of `resources/js/blocks/`
- The four EvoDevOps pages (`pages/ai/thread-studio.tsx`, `pages/admin/{inbox,prd,submissions}/`, `pages/contact*`, `pages/home`, `pages/about`)
- Hooks (`use-thread-studio-stream`, `use-typewriter`, `use-example-nav-items`)
- Types (`global.d.ts` EvoLayerExamples additions, `ontology.ts` generated)
- Command palette config + provider
- `public-layout.tsx`
- `config/{navigation,docs,command-palette}.ts`

### Stays in the host (starter conventions or app-specific)

- All auth/settings/dashboard pages — these belong to the starter
- App shell (sidebar, header, app-layout) — starter convention, but the host needs to add nav entries from `evo.examples`
- `app.tsx` layout resolver — host wires it up to include `PublicLayout` for EvoDevOps marketing pages
- `bootstrap/app.php`, `routes/web.php`, `routes/settings.php` — host edits these, package documents what to add
- `HandleInertiaRequests` shared props — host adds `evo` / `ontology` blocks, package documents the contract
- All workflow files, docs, env, scripts — host responsibility
- `tests/` covering host-side wiring

## Open scoping questions

1. **Frontend distribution**: Publishable assets (copy into host `resources/js/`) vs an npm sibling package (`@xuple/evolayer-base-blocks`)? Publishable is the Laravel idiom, npm is cleaner long-term. **Recommendation**: publishable for v1, npm later if it gains traction.
2. **Starter version pinning**: Does the package require a minimum `laravel/framework` / `inertia` / `react` version, or does it gracefully detect what's there? **Recommendation**: pin to the same minor as upstream main (`laravel/framework: ^13.7`, `react: ^19.2`).
3. **Auth assumption**: The package assumes Fortify-based auth with an `admin` role. Document this explicitly; do not try to abstract over multiple auth packages.
4. **Database driver matrix**: SQLite + PostgreSQL (with pgvector). Should the package require PostgreSQL features as optional? **Recommendation**: lane detection inside the package (already exists in `ontology.yaml`), graceful degradation on SQLite.
5. **Spatie dependencies**: `laravel-medialibrary`, `laravel-permission`, `laravel-tags`, `laravel-activitylog` are all required by EvoDevOps. Declare as `require` in package composer.json; host gets them transitively.
6. **Tests**: Package ships its own Pest test suite (the 30+ feature tests + 8 unit tests). Host runs its own starter tests independently.

## Recommended phasing

1. **Phase B — Skeleton** (1 session): Create `composer.json`, service provider, namespace, publishable asset tags, empty migration directory, basic test setup. Verify `composer require xuple/evolayer-base` works on a fresh starter.
2. **Phase C1 — Move backend** (2-3 sessions): Migrate `app/Ai/`, `app/Support/`, `app/Models/`, `app/Jobs/`, controllers, requests, middleware with namespace rewrites (`App\` → `Xuple\EvoLayer\Base\`). Move migrations, seeders, factories, config. Run tests.
3. **Phase C2 — Move frontend** (1-2 sessions): Move blocks, pages, hooks, providers as publishable assets. Document the host-side wiring (app.tsx layout resolver, sidebar nav entries, HandleInertiaRequests additions).
4. **Phase C3 — Ontology + patches** (1 session): Move `ontology.yaml`, the compiler, and the composer-patches setup. Decide whether the ontology compiler runs from host or package.
5. **Phase D — Integration test** (1 session): `laravel new evo-test --react` on the latest starter. `composer require xuple/evolayer-base` via path repository. `php artisan evolayer:install`. Verify a full thread-studio compose works end to end.

Estimated total: **6-9 focused sessions** — possibly more depending on what falls out of the modified-starter list.
