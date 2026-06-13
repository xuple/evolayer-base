# EvoLayer Base

EvoLayer Base is a Composer package that adds an AI / ontology / React-block substrate to a Laravel 13 + React + Inertia application.

**Installing it adds zero routes and no active request surface by default.** Shared props and feature pages are host opt-in via flags and publish tags.

For the strict rules on what the framework owns vs what the host app owns, and how updates work, see the **[EvoLayer Framework Contract](docs/contract.md)**.

EvoLayer Base is also the intended foundation for future EvoLayer sibling packages — **Commerce** (product sales), **SaaS** (subscriptions / tenants), and **RLS** (PostgreSQL row-level security) — but those layers ship as separate packages and are not part of this one. EvoDevOps is the teaching/site brand for the family.

## Start here

| If you are... | Use |
| --- | --- |
| Starting a new application | [`xuple/evolayer-base-starter`](https://github.com/xuple/evolayer-base-starter) — the Laravel React starter with EvoLayer already wired in |
| Adding EvoLayer to an existing Laravel React app | This package (`xuple/evolayer-base`) |
| Building a sibling package | The contracts in [Invariant contracts](#invariant-contracts-for-variant-authors) |

## What you get

- Structured AI streaming built on `laravel/ai`, with ThreadStudio, PRD generation, text assist, `evolayer:ai:smoke-test`, `evolayer:ai:probe`, and `evolayer:ai:stream-check`.
- A small ontology compiler and TypeScript contract generator for describing app entities, routes, and blocks.
- React blocks and example pages that publish into the host app without taking over the starter.
- Admin and lineage primitives (`AdminGate`, `ChangeEvent`, AI invocation ledger) intended for reuse by Commerce, SaaS, and RLS packages.

---

## Quick start

```bash
# 1. Install the package
composer require xuple/evolayer-base

# 2. Publish the always-on bits: config, core frontend, patches, npm deps
php artisan vendor:publish --tag=evolayer-base-config
php artisan vendor:publish --tag=evolayer-base-frontend-core
php artisan vendor:publish --tag=evolayer-base-patches
php artisan vendor:publish --tag=evolayer-base-npm

# 3. Install the npm deps that the published frontend needs
# (cmdk powers the standard command palette — required if you use the
# published frontend at all)
npm install cmdk

# 4. Run package migrations
php artisan migrate
```

EvoLayer migrations auto-load from the package via Laravel's package migration loader. Do **not** publish them by default; `vendor:publish --tag=evolayer-base-migrations` is only for hosts that intentionally want to own and customise the schema.

After step 4 the package is installed but **does nothing yet** — `php artisan route:list` shows no new routes. You opt in to features via env flags + per-feature publish tags (see below).

EvoLayer Base targets the Laravel React starter shape. Existing Inertia apps that have renamed routes, layouts, components, or aliases may need adapter edits after publishing frontend stubs.

For a one-shot install into an existing app, use:

```bash
php artisan evolayer:install
```

Then run `php artisan evolayer:doctor` to verify the package bindings, ontology cache, and AI patch state from the CLI runtime. `doctor` does not prove that a separate web-server or PHP-FPM user can write Laravel cache or storage paths; hosted filesystem permissions belong to the host application or starter deployment docs.

### Enabling a feature (two coupled steps)

Each opt-in feature is two things that must match: an **env flag** (registers its routes) and a **frontend publish tag** (drops its pages). Do both together — a published page whose route isn't registered will fail `tsc`, and an enabled route with no page is a dead link.

```bash
# Example: enable ThreadStudio
echo 'EVOLAYER_BASE_EXAMPLE_THREAD_STUDIO=true' >> .env
php artisan vendor:publish --tag=evolayer-base-frontend-thread-studio
php artisan wayfinder:generate --with-form
```

| Feature | Env flag | Frontend publish tag |
| --- | --- | --- |
| ThreadStudio | `EVOLAYER_BASE_EXAMPLE_THREAD_STUDIO` | `evolayer-base-frontend-thread-studio` |
| PRD Studio | `EVOLAYER_BASE_EXAMPLE_PRD_STUDIO` | `evolayer-base-frontend-prd-studio` |
| Admin Inbox | `EVOLAYER_BASE_EXAMPLE_ADMIN_INBOX` | `evolayer-base-frontend-admin-inbox` |
| Contact + AI | `EVOLAYER_BASE_EXAMPLE_CONTACT_AI` | `evolayer-base-frontend-contact-ai` |
| Marketing pages | `EVOLAYER_BASE_EXAMPLE_MARKETING_PAGES` | `evolayer-base-frontend-marketing-pages` |
| Voice input | `EVOLAYER_BASE_EXAMPLE_VOICE_INPUT` | (block ships in core; no page tag) |
| AI text field | `EVOLAYER_BASE_EXAMPLE_AI_TEXT_FIELD` | (block ships in core; no page tag) |

`voice_input` and `ai_text_field` are blocks consumed by other pages (ThreadStudio, PRD), not standalone pages. Enabling their flags registers their endpoints; the consuming page receives the endpoint URL as a server prop and shows the relevant control only when the flag is on — so no cross-feature compile-time dependency exists.

To publish everything at once (demo / kitchen-sink), use the meta tag `evolayer-base-frontend` and enable all flags.

Host apps that intentionally own the landing pages at
`resources/js/pages/evolayer/about.tsx` and `resources/js/pages/evolayer/home.tsx`
should use `evolayer-base-frontend-preserve-overrides` for forced frontend
resyncs. It publishes core plus the non-marketing feature page sets, but does
not touch those two landing-page overrides. The normal `evolayer-base-frontend`
and `evolayer-base-frontend-marketing-pages` tags remain unchanged for package
consumers that want the package defaults. The starter still keeps its
`_STARTER_OWNED_PAGE_` sentinel check as a defensive guard, not as the primary
resync strategy.

---

## Standard features (installed once, always on)

These ship as part of the package's frontend stubs and don't need a flag. They're "table-stakes" UX that every starter should have.

| Feature | What it gives you |
| --- | --- |
| **Command palette (⌘K)** | `cmdk`-powered fast nav and feature discovery; published to `resources/js/components/command-bar.tsx` and `command-palette-dialog.tsx`. Requires `cmdk` (installed at step 3 above). |
| **Block primitives** | `streaming-card`, `ai-triage`, `ai-text-field`, `voice-input`, `semantic-search` — composable React blocks under `resources/js/blocks/`. |
| **Type contracts** | `EvoLayerSharedProps`, `EvoLayerExamples`, `EvoLayerFeatures`, `EvoLayerNavItem` published to `resources/js/types/evolayer.d.ts`. |
| **`useEvoLayerProps()` hook** | Type-safe access to the `evolayer` shared prop. Pages call this instead of destructuring `usePage().props` directly. |

---

## Opt-in features (enable per env flag)

Every feature defaults to **off**. Set the corresponding env flag to `true` to enable. Each flag also gates the route file for that feature — the routes only appear in `route:list` when their flag is on.

| Env flag | What it enables |
| --- | --- |
| `EVOLAYER_BASE_EXAMPLE_THREAD_STUDIO=true` | `/ai/thread-studio` — AI customer-reply composer with structured streaming |
| `EVOLAYER_BASE_EXAMPLE_PRD_STUDIO=true` | `/admin/prd` — AI-assisted PRD generator |
| `EVOLAYER_BASE_EXAMPLE_ADMIN_INBOX=true` | `/admin/inbox` + `/admin/submissions` — form-submission inbox UI |
| `EVOLAYER_BASE_EXAMPLE_CONTACT_AI=true` | `/contact` + AI subject hints + AI triage on submission |
| `EVOLAYER_BASE_EXAMPLE_VOICE_INPUT=true` | `/ai/voice-input/transcribe` — speech-to-text endpoint for the `<VoiceInput>` block |
| `EVOLAYER_BASE_EXAMPLE_AI_TEXT_FIELD=true` | `/ai/text-assist/stream` — text-suggestion streaming endpoint for the `<AiTextField>` block |
| `EVOLAYER_BASE_EXAMPLE_MARKETING_PAGES=true` | `/about` + `/home` — showcase landing pages mapped to the published `evolayer/about.tsx` and `evolayer/home.tsx` |
| `EVOLAYER_BASE_FEATURE_CONTACT_ATTACHMENTS=true` | File-upload handling on the contact form. Requires `composer require spatie/laravel-medialibrary` (see "Opt-in extras" below) |

After enabling, run `php artisan route:list` to confirm only the routes you asked for are registered.

---

## Opt-in extras (composer packages)

Base requires `spatie/laravel-permission` and `spatie/laravel-activitylog` as core dependencies. The other two Spatie packages are optional and only required when you enable the related features:

| Spatie package | Feature it enables | Required when |
| --- | --- | --- |
| `spatie/laravel-medialibrary` | Contact form file attachments + AI media analysis | `EVOLAYER_BASE_FEATURE_CONTACT_ATTACHMENTS=true` |
| `spatie/laravel-tags` | AI auto-tagging on form submissions during triage | Optional with `EVOLAYER_BASE_EXAMPLE_CONTACT_AI=true`; triage still stores urgency/sentiment/summary when absent and records `tags_skipped=true` |

To enable contact attachments:

```bash
composer require spatie/laravel-medialibrary
php artisan vendor:publish --provider="Spatie\\MediaLibrary\\MediaLibraryServiceProvider" --tag="medialibrary-migrations"
php artisan migrate
```

Set `EVOLAYER_BASE_FEATURE_CONTACT_ATTACHMENTS=true` in `.env`. The package's `FormSubmission` model loads either way — a compat polyfill in `Xuple\EvoLayer\Base\Compat\*` shadows the Spatie interfaces and traits when the Spatie packages aren't installed.

If you use Spatie activitylog, tags, or medialibrary with EvoLayer ULID models, make the related morph id columns ULID/string-compatible before migrating on PostgreSQL:

- `activity_log.subject_id` must support ULID subjects (`nullableUlidMorphs('subject', 'subject')`).
- `taggables.taggable_id` must support ULID taggables (`ulidMorphs('taggable')`).
- `media.model_id` must support ULID media owners (`ulidMorphs('model')`).
- `activity_log.causer_id` may stay integer when causers are integer-keyed users.

The EvoLayer Base starter commits corrected Spatie migrations for this. Package-only installs that publish upstream Spatie migrations must make the same edits before running `migrate` against PostgreSQL.

---

## Host integration steps

A handful of host-owned files need small edits the package cannot publish over. They are small and stable.

If you start from [`xuple/evolayer-base-starter`](https://github.com/xuple/evolayer-base-starter), these edits are already applied.

### 1. Apply the `laravel/ai` patch

Until upstream lifts the structured-output streaming guard, structured streaming requires patching `vendor/laravel/ai/src/Providers/Concerns/StreamsText.php`. The patch ships at `patches/laravel-ai-structured-streaming.patch` after `vendor:publish --tag=evolayer-base-patches`:

```bash
patch -p1 -d vendor/laravel/ai --forward < patches/laravel-ai-structured-streaming.patch
```

The recommended production setup is to declare this patch in your starter template's `composer.json` so it survives every `composer install`. See `patches/README.md` for the revisit policy.

### 2. Wire EvoLayer shared props into Inertia

In `app/Http/Middleware/HandleInertiaRequests.php`:

```php
public function share(Request $request): array
{
    return [
        ...parent::share($request),
        // ...your existing shared props
        'evolayer' => [
            'base' => [
                'examples' => config('evolayer.base.examples'),
                'features' => config('evolayer.base.features'),
            ],
        ],
    ];
}
```

The package's pages access `evolayer` via the published `useEvoLayerProps()` hook — it throws a helpful error if the shared prop is missing, so misconfigurations surface immediately.

### 3. Map EvoLayer public pages onto `PublicLayout` (if you enable `MARKETING_PAGES`)

In `resources/js/app.tsx`:

```tsx
import PublicLayout from '@/layouts/public-layout';

createInertiaApp({
  resolve: (name) => {
    const pages = import.meta.glob('./pages/**/*.tsx', { eager: true });
    const page = pages[`./pages/${name}.tsx`];

    if (name.startsWith('evolayer/') && !name.startsWith('evolayer/admin/') && name !== 'evolayer/home') {
      page.default.layout = page.default.layout ?? ((p: ReactElement) => <PublicLayout>{p}</PublicLayout>);
    }

    return page;
  },
  // ...
});
```

### 4. Surface EvoLayer nav entries in your sidebar

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

`useExampleNavItems()` filters items by the `EVOLAYER_BASE_EXAMPLE_*` flags so disabled features don't appear.

---

## Invariant contracts (for variant authors)

These are the stable surfaces sibling EvoLayer packages (Commerce, SaaS, RLS) and host apps can depend on. Breaking changes are versioned.

### Namespacing convention

| Surface | Convention |
| --- | --- |
| Route names | `evolayer.base.*` (variants use `evolayer.commerce.*`, etc.) |
| Env vars | `EVOLAYER_BASE_EXAMPLE_*`, `EVOLAYER_BASE_FEATURE_*` (variants use `EVOLAYER_COMMERCE_*`, etc.) |
| Shared props | `evolayer.base.{examples, features}` (variants own `evolayer.commerce.*`, etc.) |
| Config | `config/evolayer.php`; access via `evolayer.base.*` |
| Publish tags | `evolayer-base-*` (variants use `evolayer-commerce-*`, etc.) |
| Migration filenames | Include `evolayer_base_` segment (variants include `evolayer_commerce_`, etc.) |

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
use Xuple\EvoLayer\Base\Support\Toast;
Toast::success('Done.');
// or directly:
Inertia::flash('toast', ['type' => 'success', 'message' => 'Done.']);
```

Payload shape: `{type: 'success'|'error'|'warning'|'info', message: string, ...}`.

### Schema invariants

The following columns are guaranteed on Base's prefixed tables/models for variant/RLS compatibility:

- `evolayer_base_ai_invocations` (`AiInvocation`): nullable `subject_type`/`subject_id` polymorph, `tenant_id`, `prompt_tokens`, `completion_tokens`, `cost_cents`, `cost_currency`.
- `evolayer_base_change_events` (`ChangeEvent`): nullable string-compatible polymorphic `actor_type`/`actor_id` (User by default; variants may record Customer/Tenant/System), nullable `tenant_id`.

### `AdminGate` contract

```php
interface AdminGate {
    public function isAdmin(?Authenticatable $user): bool;
    public function can(?Authenticatable $user, string $ability, mixed $resource = null): bool;
}
```

Default impl (`SpatieAdminGate`) routes `evolayer.admin` through `hasRole('admin')` and arbitrary abilities through Laravel's `Gate` facade. Replace via container binding to plug in a different model.

### Ontology multi-file architecture

The package's `OntologyCompiler` supports merging multiple ontology files keyed by namespace. Variant packages register their `ontology.yaml` during boot:

```php
// In a variant package's ServiceProvider::boot()
app(\Xuple\EvoLayer\Base\Support\OntologyRegistry::class)
    ->register('evolayer.commerce', __DIR__.'/../ontology.yaml');
```

The compiler reads the registry plus the host's own `ontology.yaml` and produces a namespace-keyed merged output.

---

## Pluggable admin gate

The package ships an `AdminGate` contract with a default `SpatieAdminGate` implementation (`hasRole('admin')`). To plug in a different authorisation model, bind your own implementation in `AppServiceProvider`:

```php
$this->app->singleton(\Xuple\EvoLayer\Base\Contracts\AdminGate::class, MyAdminGate::class);
```

---

## Known constraints for 0.1

- Assumes `laravel/fortify` for authentication when using the default `SpatieAdminGate`. Other auth setups require a custom `AdminGate` binding.
- Assumes the host `users` table uses the default Laravel convention (integer PK, table name `users`).
- Structured-output streaming requires the manual `laravel/ai` patch above until upstream lands the fix. Tracked in `patches/README.md`.
- The package does not automatically declare its patch in the host's composer.json — the `cweagans/composer-patches` plugin v2 cannot resolve dependency-relative patch paths at install time. The starter template (or your `composer.json`) handles this.

---

## Development

```bash
composer install
composer test
composer validate --strict
```

`composer install` automatically applies the `laravel/ai` patch to the package's own vendor copy so the test suite has structured streaming available.

The package's own test suite runs against `require-dev` Spatie packages (medialibrary, tags) — meaningful "no Spatie installed" CI verification needs a separate composer install without dev deps. The compat layer's no-op stubs are independently asserted in `tests/Unit/CompatNoopStubsTest.php`.

## Support and project status

EvoLayer is pre-1.0. Breaking changes are expected until the first tagged release. See [CHANGELOG.md](CHANGELOG.md) for changes, [RELEASE.md](RELEASE.md) for the current release process, and [DECISIONS.md](DECISIONS.md) for durable architecture decisions.

## License

MIT. See [LICENSE](LICENSE).
