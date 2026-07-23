<?php

namespace Xuple\EvoLayer\Base\Support;

use Illuminate\Routing\AbstractRouteCollection;
use Illuminate\Routing\Route;

final class RouteCollisionInspector
{
    public const OWNER = 'xuple/evolayer-base';

    /**
     * @return list<array{
     *   id: string,
     *   type: string,
     *   surface: string,
     *   domain: ?string,
     *   uri: string,
     *   methods: list<string>,
     *   package_route: string,
     *   other_route: ?string,
     *   other_action: string,
     *   allowed: bool
     * }>
     */
    public function inspect(AbstractRouteCollection $routes, PublishMap $map): array
    {
        $collisions = [];
        $allowlist = array_values(array_filter(
            (array) config('evolayer.base.route.collision_allowlist', []),
            'is_string',
        ));
        $effectiveRoutes = $this->uniqueRoutes($routes);

        foreach ($map->surfaces() as $surface) {
            if (! (bool) config("evolayer.base.examples.{$surface->configKey}")) {
                continue;
            }

            foreach ($surface->routes as $contract) {
                foreach ($effectiveRoutes as $route) {
                    if (! $this->sameLocation($route, $contract)) {
                        continue;
                    }

                    $overlap = $this->overlap($contract->methods, $route->methods());

                    if ($overlap === []) {
                        continue;
                    }

                    if (! $this->isPackageRoute($route, $contract)) {
                        $collisions[] = $this->collision(
                            type: 'host-shadows-package',
                            surface: $surface->id,
                            contract: $contract,
                            methods: $overlap,
                            otherRoute: $route,
                            allowlist: $allowlist,
                        );

                        continue;
                    }

                    foreach ((array) ($route->getAction()['evolayer_shadowed_routes'] ?? []) as $shadowed) {
                        if (! is_array($shadowed)) {
                            continue;
                        }

                        $methods = $this->overlap($overlap, (array) ($shadowed['methods'] ?? []));

                        if ($methods === []) {
                            continue;
                        }

                        $collisions[] = $this->collision(
                            type: 'package-shadows-host',
                            surface: $surface->id,
                            contract: $contract,
                            methods: $methods,
                            otherRoute: null,
                            allowlist: $allowlist,
                            otherName: is_string($shadowed['name'] ?? null) ? $shadowed['name'] : null,
                            otherAction: is_string($shadowed['action'] ?? null) ? $shadowed['action'] : 'unknown',
                        );
                    }
                }
            }
        }

        $unique = [];

        foreach ($collisions as $collision) {
            $unique[$collision['id']] = $collision;
        }

        ksort($unique);

        return array_values($unique);
    }

    /**
     * @return list<array{id: string, type: string, surface: string, package_route: string}>
     */
    public function stateFindings(AbstractRouteCollection $routes, PublishMap $map): array
    {
        $findings = [];

        foreach ($map->surfaces() as $surface) {
            $enabled = (bool) config("evolayer.base.examples.{$surface->configKey}");

            foreach ($surface->routes as $contract) {
                $registered = collect($this->matchingRoutes($routes, $contract))
                    ->contains(fn (Route $route): bool => $this->isPackageRoute($route, $contract));

                if ($registered === $enabled) {
                    continue;
                }

                $type = $enabled ? 'managed-route-missing' : 'disabled-managed-route-present';
                $findings[] = [
                    'id' => 'route-state:'.substr(hash('sha256', implode('|', [
                        $type,
                        $surface->id,
                        $contract->name,
                    ])), 0, 16),
                    'type' => $type,
                    'surface' => $surface->id,
                    'package_route' => $contract->name,
                ];
            }
        }

        return $findings;
    }

    /** @return list<Route> */
    public function matchingRoutes(AbstractRouteCollection $routes, ManagedRoute $contract): array
    {
        return array_values(array_filter(
            $this->uniqueRoutes($routes),
            fn (Route $route): bool => $this->sameLocation($route, $contract)
                && $this->overlap($contract->methods, $route->methods()) !== [],
        ));
    }

    public function markPackageRoute(Route $route, string $surface, array $shadowedRoutes): void
    {
        $action = $route->getAction();
        $action['evolayer_owner'] = self::OWNER;
        $action['evolayer_surface'] = $surface;

        if ($shadowedRoutes !== []) {
            $action['evolayer_shadowed_routes'] = array_map(fn (Route $shadowed): array => [
                'methods' => $shadowed->methods(),
                'name' => $shadowed->getName(),
                'action' => $shadowed->getActionName(),
            ], $shadowedRoutes);
        }

        $route->setAction($action);
    }

    /** @return list<Route> */
    private function uniqueRoutes(AbstractRouteCollection $routes): array
    {
        $unique = [];

        foreach ($routes->getRoutes() as $route) {
            $unique[spl_object_id($route)] = $route;
        }

        return array_values($unique);
    }

    private function sameLocation(Route $route, ManagedRoute $contract): bool
    {
        return $this->normalizeDomain($route->getDomain()) === $this->normalizeDomain($contract->domain)
            && trim($route->uri(), '/') === trim($contract->uri, '/');
    }

    /**
     * @param  array<int, mixed>  $left
     * @param  array<int, mixed>  $right
     * @return list<string>
     */
    private function overlap(array $left, array $right): array
    {
        $left = array_values(array_unique(array_map(
            fn (mixed $method): string => strtoupper((string) $method),
            $left,
        )));
        $right = array_values(array_unique(array_map(
            fn (mixed $method): string => strtoupper((string) $method),
            $right,
        )));
        $methods = array_values(array_intersect($left, $right));
        sort($methods);

        return $methods;
    }

    private function isPackageRoute(Route $route, ManagedRoute $contract): bool
    {
        if (($route->getAction()['evolayer_owner'] ?? null) === self::OWNER) {
            return true;
        }

        return $route->getName() === $contract->name
            && str_starts_with($route->getActionName(), 'Xuple\\EvoLayer\\Base\\');
    }

    private function normalizeDomain(?string $domain): string
    {
        return strtolower(trim((string) $domain));
    }

    /**
     * @param  list<string>  $methods
     * @param  list<string>  $allowlist
     * @return array{id: string, type: string, surface: string, domain: ?string, uri: string, methods: list<string>, package_route: string, other_route: ?string, other_action: string, allowed: bool}
     */
    private function collision(
        string $type,
        string $surface,
        ManagedRoute $contract,
        array $methods,
        ?Route $otherRoute,
        array $allowlist,
        ?string $otherName = null,
        string $otherAction = 'unknown',
    ): array {
        $otherName ??= $otherRoute?->getName();
        $otherAction = $otherRoute?->getActionName() ?? $otherAction;
        $identity = implode('|', [
            $type,
            $this->normalizeDomain($contract->domain),
            trim($contract->uri, '/'),
            implode(',', $methods),
            $contract->name,
            $otherName ?? '',
            $otherAction,
        ]);
        $id = 'route-collision:'.substr(hash('sha256', $identity), 0, 16);

        return [
            'id' => $id,
            'type' => $type,
            'surface' => $surface,
            'domain' => $contract->domain,
            'uri' => trim($contract->uri, '/'),
            'methods' => $methods,
            'package_route' => $contract->name,
            'other_route' => $otherName,
            'other_action' => $otherAction,
            'allowed' => in_array($id, $allowlist, true),
        ];
    }
}
