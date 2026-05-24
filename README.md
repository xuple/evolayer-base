# EvoDevOps Base

EvoDevOps Base is a composer package that adds the EvoDevOps AI / ontology / blocks layer to a Laravel 13 React Inertia starter. It is the foundation for the EvoDevOps family of sibling packages — **Commerce** (product sales), **SaaS** (subscriptions / tenants), **RLS** (PostgreSQL row-level security, composable).

The package is designed to feel like a clean additive layer for a developer transitioning from `laravel/react-starter-kit`. **Installing it adds zero routes, zero middleware, zero shared props by default.** Each feature is opt-in via a flag.

---

## Quick start

```bash
# 1. Install the package
composer require evodevops/base

# 2. Publish stubs to the host
php artisan vendor:publish --tag=evodevops-base-config
php artisan vendor:publish --tag=evodevops-base-frontend
php artisan vendor:publish --tag=evodevops-base-migrations
php artisan vendor:publish --tag=evodevops-base-patches
php artisan vendor:publish --tag=evodevops-base-npm

# 3. Install the npm deps that the published frontend needs
# (cmdk powers the standard command palette — required if you use the
# published frontend at all)
npm install cmdk

# 4. Run package migrations
php artisan migrate
```

After step 4 the package is installed but **does nothing yet** — `php artisan route:list` shows no new routes. You opt in to features via env flags (see below).

---

## Standard features (installed once, always on)

These ship as part of the package's frontend stubs and don't need a flag. They're "table-stakes" UX that every starter should have.

| Feature | What it gives you |
| --- | --- |
| **Command palette (⌘K)** | `cmdk`-powered fast nav and feature discovery; published to `resources/js/components/command-bar.tsx` and `command-palette-dialog.tsx`. Requires `cmdk` (installed at step 3 above). |
| **Block primitives** | `streaming-card`, `ai-triage`, `ai-text-field`, `voice-input`, `semantic-search` — composable React blocks under `resources/js/blocks/`. |
| **Type contracts** | `EvoSharedProps`, `EvoExamples`, `EvoFeatures`, `EvoNavItem` published to `resources/js/types/evodevops.d.ts`. |
| **`useEvoProps()` hook** | Type-safe access to the `evo` shared prop. Pages call this instead of destructuring `usePage().props` directly. |

---

## Opt-in features (enable per env flag)

Every feature defaults to **off**. Set the corresponding env flag to `true` to enable. Each flag also gates the route file for that feature — the routes only appear in `route:list` when their flag is on.

| Env flag | What it enables |
| --- | --- |
| `EVO_BASE_EXAMPLE_THREAD_STUDIO=true` | `/ai/thread-studio` — AI customer-reply composer with structured streaming |
| `EVO_BASE_EXAMPLE_PRD_STUDIO=true` | `/admin/prd` — AI-assisted PRD generator |
| `EVO_BASE_EXAMPLE_ADMIN_INBOX=true` | `/admin/inbox` + `/admin/submissions` — form-submission inbox UI |
| `EVO_BASE_EXAMPLE_CONTACT_AI=true` | `/contact` + AI subject hints + AI triage on submission |
| `EVO_BASE_EXAMPLE_VOICE_INPUT=true` | `/ai/voice-input/transcribe` — speech-to-text endpoint for the `<VoiceInput>` block |
| `EVO_BASE_EXAMPLE_AI_TEXT_FIELD=true` | `/ai/text-assist/stream` — text-suggestion streaming endpoint for the `<AiTextField>` block |
| `EVO_BASE_EXAMPLE_MARKETING_PAGES=true` | `/about` + `/home` — showcase landing pages mapped to the published `evodevops/about.tsx` and `evodevops/home.tsx` |
| `EVO_BASE_FEATURE_CONTACT_ATTACHMENTS=true` | File-upload handling on the contact form. Requires `composer require spatie/laravel-medialibrary` (see "Opt-in extras" below) |

After enabling, run `php artisan route:list` to confirm only the routes you asked for are registered.

---

## Opt-in extras (composer packages)

Base requires `spatie/laravel-permission` and `spatie/laravel-activitylog` as core dependencies. The other two Spatie packages are optional and only required when you enable the related features:

| Spatie package | Feature it enables | Required when |
| --- | --- | --- |
| `spatie/laravel-medialibrary` | Contact form file attachments + AI media analysis | `EVO_BASE_FEATURE_CONTACT_ATTACHMENTS=true` |
| `spatie/laravel-tags` | AI auto-tagging on form submissions during triage | `EVO_BASE_EXAMPLE_CONTACT_AI=true` (only if you want auto-tagging) |

To enable contact attachments:

```bash
composer require spatie/laravel-medialibrary
php artisan vendor:publish --provider="Spatie\\MediaLibrary\\MediaLibraryServiceProvider" --tag="medialibrary-migrations"
php artisan migrate
```

Set `EVO_BASE_FEATURE_CONTACT_ATTACHMENTS=true` in `.env`. The package's `FormSubmission` model loads either way — a compat polyfill in `EvoDevOps\Base\Compat\*` shadows the Spatie interfaces and traits when the Spatie packages aren't installed.

---

## Host integration steps

A handful of host-owned files need small edits the package cannot publish over. They are small and stable.

### 1. Apply the `laravel/ai` patch

Until upstream lifts the structured-output streaming guard, structured streaming requires patching `vendor/laravel/ai/src/Providers/Concerns/StreamsText.php`. The patch ships at `patches/laravel-ai-structured-streaming.patch` after `vendor:publish --tag=evodevops-base-patches`:

```bash
patch -p1 -d vendor/laravel/ai --forward < patches/laravel-ai-structured-streaming.patch
```

The recommended production setup is to declare this patch in your starter template's `composer.json` so it survives every `composer install`. See `patches/README.md` for the revisit policy.

### 2. Wire EvoDevOps shared props into Inertia

In `app/Http/Middleware/HandleInertiaRequests.php`:

```php
public function share(Request $request): array
{
    return [
        ...parent::share($request),
        // ...your existing shared props
        'evo' => [
            'base' => [
                'examples' => config('evo.base.examples'),
                'features' => config('evo.base.features'),
            ],
        ],
    ];
}
```

The package's pages access `evo` via the published `useEvoProps()` hook — it throws a helpful error if the shared prop is missing, so misconfigurations surface immediately.

### 3. Map EvoDevOps public pages onto `PublicLayout` (if you enable `MARKETING_PAGES`)

In `resources/js/app.tsx`:

```tsx
import PublicLayout from '@/layouts/public-layout';

createInertiaApp({
  resolve: (name) => {
    const pages = import.meta.glob('./pages/**/*.tsx', { eager: true });
    const page = pages[`./pages/${name}.tsx`];

    if (name.startsWith('evodevops/') && !name.startsWith('evodevops/admin/') && name !== 'evodevops/home') {
      page.default.layout = page.default.layout ?? ((p: ReactElement) => <PublicLayout>{p}</PublicLayout>);
    }

    return page;
  },
  // ...
});
```

### 4. Surface EvoDevOps nav entries in your sidebar

In `resources/js/components/app-sidebar.tsx`:

```tsx
import { useExampleNavItems } from '@/hooks/use-example-nav-items';
import { sidebarPrimaryNavItems } from '@/config/navigation';

export function AppSidebar() {
  const evoItems = useExampleNavItems(sidebarPrimaryNavItems);
  const navMain = [...yourOwnItems, ...evoItems];
  // ...
}
```

`useExampleNavItems()` filters items by the `EVO_BASE_EXAMPLE_*` flags so disabled features don't appear.

---

## Invariant contracts (for variant authors)

These are the stable surfaces sibling EvoDevOps packages (Commerce, SaaS, RLS) and host apps can depend on. Breaking changes are versioned.

### Namespacing convention

| Surface | Convention |
| --- | --- |
| Route names | `evodevops.base.*` (variants use `evodevops.commerce.*`, etc.) |
| Env vars | `EVO_BASE_EXAMPLE_*`, `EVO_BASE_FEATURE_*` (variants use `EVO_COMMERCE_*`, etc.) |
| Shared props | `evo.base.{examples, features}` (variants own `evo.commerce.*`, etc.) |
| Config | `config/evodevops.php`; access via `evo.base.*` |
| Publish tags | `evodevops-base-*` (variants use `evodevops-commerce-*`, etc.) |
| Migration filenames | Include `evodevops_base_` segment (variants include `evodevops_commerce_`, etc.) |

### SSE streaming vocabulary

Stable event names emitted by Base streaming endpoints:

| Event | Payload |
| --- | --- |
| `start` | `{}` |
| `field_delta` | `{name, delta}` (structured streaming per-field accumulation) |
| `field_complete` | `{name}` |
| `text_delta` | `{delta}` (plain text streaming) |
| `done` | `{result?, invocation_id?, duration_ms?, text?}` |
| `error` | `{message, failure_type, provider?, model?, missing_fields?, invalid_fields?}` |

Reserved for future Base / variant use (documented now to prevent collision): `tool_call`, `tool_result`, `thinking_delta`, `usage`.

### Toast / flash contract

```php
use EvoDevOps\Base\Support\Toast;
Toast::success('Done.');
// or directly:
Inertia::flash('toast', ['type' => 'success', 'message' => 'Done.']);
```

Payload shape: `{type: 'success'|'error'|'warning'|'info', message: string, ...}`.

### Schema invariants

The following columns are guaranteed on Base's models for variant/RLS compatibility:

- `ai_invocations`: nullable `subject_type`/`subject_id` polymorph, `tenant_id`, `prompt_tokens`, `completion_tokens`, `cost_cents`, `cost_currency`.
- `change_events`: nullable polymorphic `actor_type`/`actor_id` (User by default; variants may record Customer/Tenant/System), nullable `tenant_id`.

### `AdminGate` contract

```php
interface AdminGate {
    public function isAdmin(?Authenticatable $user): bool;
    public function can(?Authenticatable $user, string $ability, mixed $resource = null): bool;
}
```

Default impl (`SpatieAdminGate`) routes `evodevops.admin` through `hasRole('admin')` and arbitrary abilities through Laravel's `Gate` facade. Replace via container binding to plug in a different model.

### Ontology multi-file architecture

The package's `OntologyCompiler` supports merging multiple ontology files keyed by namespace. Variant packages register their `ontology.yaml` during boot:

```php
// In a variant package's ServiceProvider::boot()
app(\EvoDevOps\Base\Support\OntologyRegistry::class)
    ->register('evo.commerce', __DIR__.'/../ontology.yaml');
```

The compiler reads the registry plus the host's own `ontology.yaml` and produces a namespace-keyed merged output.

---

## Pluggable admin gate

The package ships an `AdminGate` contract with a default `SpatieAdminGate` implementation (`hasRole('admin')`). To plug in a different authorisation model, bind your own implementation in `AppServiceProvider`:

```php
$this->app->singleton(\EvoDevOps\Base\Contracts\AdminGate::class, MyAdminGate::class);
```

---

## Known constraints (v1)

- Assumes `laravel/fortify` for authentication when using the default `SpatieAdminGate`. Other auth setups require a custom `AdminGate` binding.
- Assumes the host `users` table uses the default Laravel convention (integer PK, table name `users`).
- Structured-output streaming requires the manual `laravel/ai` patch above until upstream lands the fix. Tracked in `patches/README.md`.
- The package does not automatically declare its patch in the host's composer.json — the `cweagans/composer-patches` plugin v2 cannot resolve dependency-relative patch paths at install time. The starter template (or your `composer.json`) handles this.

---

## Tests

```bash
composer install
composer test
```

`composer install` automatically applies the `laravel/ai` patch to the package's own vendor copy so the test suite has structured streaming available.

The package's own test suite runs against `require-dev` Spatie packages (medialibrary, tags) — meaningful "no Spatie installed" CI verification needs a separate composer install without dev deps. The compat layer's no-op stubs are independently asserted in `tests/Unit/CompatNoopStubsTest.php`.
