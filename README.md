# EvoDevOps Base

EvoDevOps Base is a composer package that adds the EvoDevOps AI / ontology / blocks layer to a Laravel 13 React Inertia starter. It is the foundation for the EvoDevOps family of sibling packages — **Commerce** (product sales), **SaaS** (subscriptions / tenants), **RLS** (PostgreSQL row-level security, composable).

The package is designed to feel like a clean additive layer for a developer transitioning from `laravel/react-starter-kit`. Installing it adds **zero routes, zero middleware, zero shared props** by default. Each feature is opt-in via a flag.

## Install

```bash
composer require evodevops/base

php artisan vendor:publish --tag=evodevops-base-config
php artisan vendor:publish --tag=evodevops-base-frontend
php artisan vendor:publish --tag=evodevops-base-migrations
php artisan vendor:publish --tag=evodevops-base-patches
php artisan vendor:publish --tag=evodevops-base-npm

php artisan migrate
php artisan db:seed --class="EvoDevOps\\Base\\Database\\Seeders\\AiCapabilitySeeder"
```

## Enable features (opt-in)

All features default to `false`. Enable per-feature with env flags:

```env
# Examples — turn on individual showcase features
EVO_BASE_EXAMPLE_THREAD_STUDIO=true
EVO_BASE_EXAMPLE_PRD_STUDIO=true
EVO_BASE_EXAMPLE_ADMIN_INBOX=true
EVO_BASE_EXAMPLE_CONTACT_AI=true
EVO_BASE_EXAMPLE_VOICE_INPUT=true
EVO_BASE_EXAMPLE_AI_TEXT_FIELD=true

# Capabilities — turn on infrastructural features
EVO_BASE_FEATURE_CONTACT_ATTACHMENTS=true
```

After enabling, run `php artisan route:list` to confirm only the routes you asked for are registered.

## After install — manual steps

The integration points below cannot be cleanly automated. They are stable and small.

### 1. Apply the `laravel/ai` patch

Until upstream lifts the structured-output streaming guard, structured streaming requires patching `vendor/laravel/ai/src/Providers/Concerns/StreamsText.php`. The patch ships at `patches/laravel-ai-structured-streaming.patch` after `vendor:publish --tag=evodevops-base-patches`. Apply it:

```bash
patch -p1 -d vendor/laravel/ai --forward < patches/laravel-ai-structured-streaming.patch
```

The recommended production setup is to declare this patch in your starter template's `composer.json` so it survives every `composer install`. See `patches/README.md` for the revisit policy.

### 2. Install opt-in npm dependencies

The frontend stubs use a small number of npm packages that the starter does not ship. After `vendor:publish --tag=evodevops-base-npm` you'll find `package-json-additions.evodevops.json` at your project root. Merge its `dependencies` block into your own `package.json` and re-install:

```bash
# Add the dependencies from package-json-additions.evodevops.json to package.json, then:
npm install      # or pnpm install
```

Currently: `cmdk@^1.0.0` (used by the command palette).

### 3. Install opt-in Spatie packages (if you want media or tags)

Base requires `spatie/laravel-permission` and `spatie/laravel-activitylog` as core dependencies. The other two Spatie packages are optional and only required when you enable the related features:

| Spatie package | Feature it enables | Env flag |
| --- | --- | --- |
| `spatie/laravel-medialibrary` | Contact form file attachments + AI media analysis | `EVO_BASE_FEATURE_CONTACT_ATTACHMENTS=true` |
| `spatie/laravel-tags` | AI auto-tagging on form submissions during triage | (enabled when `EVO_BASE_EXAMPLE_CONTACT_AI=true` if the package is installed) |

To enable contact attachments:

```bash
composer require spatie/laravel-medialibrary
php artisan vendor:publish --provider="Spatie\\MediaLibrary\\MediaLibraryServiceProvider" --tag="medialibrary-migrations"
php artisan migrate
```

Set `EVO_BASE_FEATURE_CONTACT_ATTACHMENTS=true` in `.env`. The package's `FormSubmission` model loads either way — a compat polyfill in `EvoDevOps\Base\Compat\*` shadows the Spatie interfaces and traits when the Spatie packages aren't installed, throwing only when mutation methods are reached (which the call sites gate on the feature flag).

### 4. Wire EvoDevOps shared props into Inertia

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

For TypeScript autocomplete on `usePage().props.evo.base`, the package publishes `resources/js/types/evodevops.d.ts` with the `EvoSharedProps`, `EvoBaseSharedProps`, `EvoExamples`, and `EvoFeatures` types. Import from `@/types/evodevops`.

### 5. Map EvoDevOps public pages onto `PublicLayout`

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

### 6. Surface EvoDevOps nav entries in your sidebar

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

## Invariant contracts (for variant authors)

These are the stable surfaces that sibling EvoDevOps packages (Commerce, SaaS, RLS) and host apps can depend on. Breaking changes are versioned.

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

Payload shape: `{type: 'success'|'error'|'warning'|'info', message: string, ...}`. Consumers read via the host's flash hook.

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

The compiler reads the registry plus the host's own `ontology.yaml` and produces a namespace-keyed merged output. Each ontology file declares its own `namespace:` and `entities/events/blocks/etc.` are scoped under it.

## Pluggable admin gate

The package ships an `AdminGate` contract with a default `SpatieAdminGate` implementation (`hasRole('admin')`). To plug in a different authorisation model, bind your own implementation in `AppServiceProvider`:

```php
$this->app->singleton(\EvoDevOps\Base\Contracts\AdminGate::class, MyAdminGate::class);
```

## Known constraints (v1)

- Assumes `laravel/fortify` for authentication when using the default `SpatieAdminGate`. Other auth setups require a custom `AdminGate` binding.
- Assumes the host `users` table uses the default Laravel convention (integer PK, table name `users`).
- Structured-output streaming requires the manual `laravel/ai` patch above until upstream lands the fix. Tracked in `patches/README.md`.
- The package does not automatically declare its patch in the host's composer.json — the `cweagans/composer-patches` plugin v2 cannot resolve dependency-relative patch paths at install time. The starter template (or your `composer.json`) handles this.

## Tests

```bash
composer install
composer test
```

`composer install` automatically applies the `laravel/ai` patch to the package's own vendor copy so the test suite has structured streaming available.

The package's own test suite runs against `require-dev` Spatie packages (medialibrary, tags) — meaningful "no Spatie installed" CI verification needs a separate composer install without dev deps. The compat layer's no-op stubs are independently asserted in `tests/Unit/CompatNoopStubsTest.php`.
