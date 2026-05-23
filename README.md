# EvoDevOps Base

EvoDevOps Base is a composer package that adds the EvoDevOps AI / ontology / blocks layer to a Laravel 13 React Inertia starter. It is the first release in the EvoDevOps family (Commerce Core and RLS follow).

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

## After install — manual steps

The integration points below cannot be cleanly automated by the package, because they edit host-owned files. They are stable and small.

### 1. Apply the `laravel/ai` patch

Until upstream lifts the structured-output streaming guard, structured streaming requires patching `vendor/laravel/ai/src/Providers/Concerns/StreamsText.php`. The patch ships at `patches/laravel-ai-structured-streaming.patch` after `vendor:publish --tag=evodevops-base-patches`. Apply it:

```bash
patch -p1 -d vendor/laravel/ai --forward < patches/laravel-ai-structured-streaming.patch
```

The recommended production setup is to declare this patch in your starter template's `composer.json` so it survives every `composer install`. See `patches/README.md` for the revisit policy.

### 2. Install the extra npm dependencies

The frontend stubs use a small number of npm packages that the starter does not ship. After `vendor:publish --tag=evodevops-base-npm` you'll find `package-json-additions.evodevops.json` at your project root. Merge its `dependencies` block into your own `package.json` and re-install:

```bash
# Add the dependencies from package-json-additions.evodevops.json to package.json, then:
npm install      # or pnpm install
```

Currently: `cmdk@^1.0.0` (used by the command palette).

### 3. Wire EvoDevOps shared props into Inertia

In `app/Http/Middleware/HandleInertiaRequests.php`:

```php
public function share(Request $request): array
{
    return [
        ...parent::share($request),
        // ...your existing shared props
        'evo' => [
            'examples' => config('evo.examples'),
            'features' => config('evo.features'),
        ],
    ];
}
```

For TypeScript autocomplete on `usePage().props.evo`, the package publishes `resources/js/types/evodevops.d.ts` with the `EvoSharedProps`, `EvoExamples`, and `EvoFeatures` types. Import from `@/types/evodevops`.

### 4. Map EvoDevOps public pages onto `PublicLayout`

In `resources/js/app.tsx`:

```tsx
import PublicLayout from '@/layouts/public-layout';

createInertiaApp({
  resolve: (name) => {
    const pages = import.meta.glob('./pages/**/*.tsx', { eager: true });
    const page = pages[`./pages/${name}.tsx`];

    // EvoDevOps marketing pages render inside PublicLayout
    if (name.startsWith('evodevops/') && !name.startsWith('evodevops/admin/') && name !== 'evodevops/home') {
      page.default.layout = page.default.layout ?? ((p: ReactElement) => <PublicLayout>{p}</PublicLayout>);
    }

    return page;
  },
  // ...
});
```

### 5. Surface EvoDevOps nav entries in your sidebar

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

`useExampleNavItems()` filters items by the `EVO_EXAMPLE_*` flags so disabled features don't appear.

## Route names

All package route names are prefixed with `evodevops.` to avoid collisions with starter or host routes:

| URL                              | Route name                              |
| -------------------------------- | --------------------------------------- |
| `/about`                         | `evodevops.about`                       |
| `/contact` (GET/POST)            | `evodevops.contact[.store]`             |
| `/contact/thank-you`             | `evodevops.contact.thank-you`           |
| `/contact/subject-hints`         | `evodevops.contact.subject-hints`       |
| `/home`                          | `evodevops.home`                        |
| `/admin/inbox[...]`              | `evodevops.admin.inbox.*`               |
| `/admin/prd[...]`                | `evodevops.admin.prd.*`                 |
| `/admin/submissions[...]`        | `evodevops.admin.submissions.*`         |
| `/ai/thread-studio[...]`         | `evodevops.ai.thread-studio.*`          |
| `/ai/voice-input/transcribe`    | `evodevops.ai.voice-input.transcribe`   |
| `/ai/text-assist/stream`         | `evodevops.ai.text-assist.stream`       |

URLs are not prefixed by default. Override the package's route group attributes via `config('evo.route.middleware')` if you need a URL prefix.

## Pluggable admin gate

The package ships an `AdminGate` contract with a default `SpatieAdminGate` implementation (`hasRole('admin')`). To plug in a different authorisation model, bind your own implementation in `AppServiceProvider`:

```php
$this->app->singleton(\EvoDevOps\Base\Contracts\AdminGate::class, MyAdminGate::class);
```

## Known constraints (v1)

- Assumes `laravel/fortify` for authentication and `spatie/laravel-permission` for the `admin` role.
- Assumes the host `users` table uses the default Laravel convention (integer PK, table name `users`).
- Structured-output streaming requires the manual `laravel/ai` patch above until upstream lands the fix. Tracked in `patches/README.md`.
- The package does not automatically declare its patch in the host's composer.json — the `cweagans/composer-patches` plugin v2 cannot resolve dependency-relative patch paths at install time. The starter template (or your `composer.json`) handles this.

## Tests

```bash
composer install
composer test
```

`composer install` automatically applies the `laravel/ai` patch to the package's own vendor copy so the test suite has structured streaming available.
