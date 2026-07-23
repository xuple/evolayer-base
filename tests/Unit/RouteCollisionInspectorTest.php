<?php

use Illuminate\Routing\CompiledRouteCollection;
use Illuminate\Routing\Route;
use Illuminate\Routing\RouteCollection;
use Xuple\EvoLayer\Base\Support\PublishMap;
use Xuple\EvoLayer\Base\Support\RouteCollisionInspector;

function collisionTestRoute(array $methods, string $uri, string $name, string $action = 'HostController@index', ?string $domain = null): Route
{
    $route = new Route($methods, $uri, ['uses' => $action]);
    $route->name($name);

    if ($domain !== null) {
        $route->domain($domain);
    }

    return $route;
}

test('host routes that overlap package methods at the same domain and URI are collisions', function () {
    $routes = new RouteCollection;
    $routes->add(collisionTestRoute(['GET', 'HEAD'], 'contact', 'host.contact'));

    $collisions = (new RouteCollisionInspector)->inspect($routes, app(PublishMap::class));
    $contact = collect($collisions)->firstWhere('package_route', 'evolayer.base.contact');

    expect($contact)->not->toBeNull()
        ->and($contact['type'])->toBe('host-shadows-package')
        ->and($contact['methods'])->toBe(['GET', 'HEAD'])
        ->and($contact['other_route'])->toBe('host.contact')
        ->and($contact['allowed'])->toBeFalse();
});

test('non-overlapping methods and different domains are not false positives', function () {
    $routes = new RouteCollection;
    $routes->add(collisionTestRoute(['DELETE'], 'contact', 'host.contact.delete'));
    $routes->add(collisionTestRoute(['GET', 'HEAD'], 'contact', 'host.contact.domain', domain: 'admin.example.test'));

    $collisions = (new RouteCollisionInspector)->inspect($routes, app(PublishMap::class));

    expect($collisions)->toBe([]);
});

test('an any route overlaps each package method at the same location', function () {
    $routes = new RouteCollection;
    $routes->add(collisionTestRoute(
        ['GET', 'HEAD', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],
        'contact',
        'host.contact.any',
    ));

    $collisions = (new RouteCollisionInspector)->inspect($routes, app(PublishMap::class));

    expect(collect($collisions)->pluck('package_route')->all())->toContain(
        'evolayer.base.contact',
        'evolayer.base.contact.store',
    );
});

test('package routes retain prior host ownership evidence for cached diagnostics', function () {
    $routes = new RouteCollection;
    $host = collisionTestRoute(['GET', 'HEAD'], 'contact', 'host.contact');
    $package = collisionTestRoute(
        ['GET', 'HEAD'],
        'contact',
        'evolayer.base.contact',
        'Xuple\\EvoLayer\\Base\\Http\\Controllers\\ContactController@show',
    );
    $inspector = new RouteCollisionInspector;
    $inspector->markPackageRoute($package, 'contact-ai', [$host]);
    $routes->add($package);

    $collisions = $inspector->inspect($routes, app(PublishMap::class));
    $contact = collect($collisions)->firstWhere('package_route', 'evolayer.base.contact');

    expect($contact['type'])->toBe('package-shadows-host')
        ->and($contact['other_route'])->toBe('host.contact')
        ->and($package->getAction()['evolayer_shadowed_routes'])->toBeArray();
});

test('only an exact stable collision ID is allowlisted', function () {
    $routes = new RouteCollection;
    $routes->add(collisionTestRoute(['GET', 'HEAD'], 'contact', 'host.contact'));
    $inspector = new RouteCollisionInspector;
    $first = $inspector->inspect($routes, app(PublishMap::class));
    $id = collect($first)->firstWhere('package_route', 'evolayer.base.contact')['id'];
    config()->set('evolayer.base.route.collision_allowlist', [$id]);

    $allowed = $inspector->inspect($routes, app(PublishMap::class));

    expect(collect($allowed)->firstWhere('id', $id)['allowed'])->toBeTrue();
});

test('compiled and uncached route collections produce equivalent collision records', function () {
    $routes = new RouteCollection;
    $routes->add(collisionTestRoute(['GET', 'HEAD'], 'contact', 'host.contact'));
    $compiledData = $routes->compile();
    $compiled = new CompiledRouteCollection(
        $compiledData['compiled'],
        $compiledData['attributes'],
    );
    $compiled->setRouter(app('router'));
    $compiled->setContainer(app());
    $inspector = new RouteCollisionInspector;

    expect($inspector->inspect($compiled, app(PublishMap::class)))
        ->toBe($inspector->inspect($routes, app(PublishMap::class)));
});

test('managed route state detects cached routes for disabled surfaces and missing enabled routes', function () {
    $routes = new RouteCollection;
    $package = collisionTestRoute(
        ['GET', 'HEAD'],
        'contact',
        'evolayer.base.contact',
        'Xuple\\EvoLayer\\Base\\Http\\Controllers\\ContactController@show',
    );
    $inspector = new RouteCollisionInspector;
    $inspector->markPackageRoute($package, 'contact-ai', []);
    $routes->add($package);
    config()->set('evolayer.base.examples.contact_ai', false);
    config()->set('evolayer.base.examples.thread_studio', true);

    $findings = $inspector->stateFindings($routes, app(PublishMap::class));

    expect($findings)->toContainEqual([
        'id' => collect($findings)->firstWhere('package_route', 'evolayer.base.contact')['id'],
        'type' => 'disabled-managed-route-present',
        'surface' => 'contact-ai',
        'package_route' => 'evolayer.base.contact',
    ])->and(collect($findings)->contains(
        fn (array $finding): bool => $finding['type'] === 'managed-route-missing'
            && $finding['surface'] === 'thread-studio',
    ))->toBeTrue();
});
