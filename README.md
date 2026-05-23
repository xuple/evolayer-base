# EvoDevOps Base

EvoDevOps Base is a composer package that adds the EvoDevOps AI / ontology / blocks layer to a Laravel 13 React Inertia starter. It is the first release in the EvoDevOps family (Commerce Core and RLS follow).

## Install

```bash
composer require evodevops/base
```

The package declares `cweagans/composer-patches` as a soft dependency. Until `laravel/ai` lifts its structured-output streaming guard upstream, the package needs the plugin enabled to patch `Laravel\Ai\Providers\Concerns\StreamsText`:

```bash
composer config allow-plugins.cweagans/composer-patches true
composer require cweagans/composer-patches
composer install
```

Run the installer (publishes config, frontend stubs, migrations, and the vendor patch):

```bash
php artisan vendor:publish --tag=evodevops-base-config
php artisan vendor:publish --tag=evodevops-base-frontend
php artisan vendor:publish --tag=evodevops-base-migrations
php artisan vendor:publish --tag=evodevops-base-patches

php artisan migrate
php artisan db:seed --class="EvoDevOps\\Base\\Database\\Seeders\\AiCapabilitySeeder"
```

## Host-side patches

Three host files need small edits the package cannot publish over (each is a starter convention the host owns):

### `app/Http/Middleware/HandleInertiaRequests.php`

Add the EvoDevOps shared prop to the `share()` array:

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

### `resources/js/app.tsx`

Map EvoDevOps public pages onto `PublicLayout`:

```tsx
import { PublicLayout } from '@/layouts/public-layout';

createInertiaApp({
  resolve: (name) => {
    const pages = import.meta.glob('./pages/**/*.tsx', { eager: true });
    const page = pages[`./pages/${name}.tsx`];

    if (name.startsWith('evodevops/') && !name.startsWith('evodevops/admin/')) {
      page.default.layout = page.default.layout ?? ((p: ReactElement) => <PublicLayout>{p}</PublicLayout>);
    }

    return page;
  },
  // ...
});
```

### `resources/js/components/app-sidebar.tsx`

Add EvoDevOps example nav entries to your sidebar:

```tsx
import { useExampleNavItems } from '@/hooks/use-example-nav-items';

export function AppSidebar() {
  const evoItems = useExampleNavItems();
  const navMain = [...yourOwnItems, ...evoItems];
  // ...
}
```

### `resources/js/types/global.d.ts`

Extend the `evo` shared prop type:

```ts
export type EvoExamples = {
  thread_studio: boolean;
  prd_studio: boolean;
  admin_inbox: boolean;
  contact_ai: boolean;
  voice_input: boolean;
  ai_text_field: boolean;
};
```

## Pluggable admin gate

The package ships an `AdminGate` contract with a default `SpatieAdminGate` implementation (`hasRole('admin')`). To plug in a different authorisation model, bind your own implementation in `AppServiceProvider`:

```php
$this->app->singleton(\EvoDevOps\Base\Contracts\AdminGate::class, MyAdminGate::class);
```

## Known constraints (v1)

- Assumes `laravel/fortify` for authentication and `spatie/laravel-permission` for the `admin` role.
- Assumes the host `users` table uses the default Laravel convention (integer PK, table name `users`).
- Structured-output streaming requires the vendor patch under `patches/` until `laravel/ai` lands the fix upstream. Track status in `patches/README.md`.

## Tests

```bash
composer install
composer test
```
