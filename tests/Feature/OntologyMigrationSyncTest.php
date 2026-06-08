<?php

use Illuminate\Support\Facades\Schema;
use Symfony\Component\Yaml\Yaml;

test('package ontology fields match migrated package columns', function () {
    $mismatches = [];

    foreach (packageOntologyEntities() as $entityName => $entity) {
        $table = $entity['table'];
        $columns = Schema::getColumnListing($table);
        $fields = array_keys($entity['fields'] ?? []);

        $missingInOntology = array_values(array_diff($columns, $fields));
        $extraInOntology = array_values(array_diff($fields, $columns));

        if ($missingInOntology !== [] || $extraInOntology !== []) {
            $mismatches[$entityName] = [
                'table' => $table,
                'missing_in_ontology' => $missingInOntology,
                'extra_in_ontology' => $extraInOntology,
            ];
        }
    }

    expect($mismatches)->toBe([]);
});

test('package ontology morph_to relations match polymorphic migration columns', function () {
    $mismatches = [];

    foreach (packageOntologyEntities() as $entityName => $entity) {
        $table = $entity['table'];
        $columns = Schema::getColumnListing($table);
        $relations = $entity['relations'] ?? [];

        foreach (polymorphicColumnPrefixes($columns) as $prefix) {
            if (($relations[$prefix]['type'] ?? null) !== 'morph_to') {
                $mismatches[$entityName]["{$prefix}_relation"] = [
                    'table' => $table,
                    'expected' => 'morph_to',
                    'actual' => $relations[$prefix]['type'] ?? null,
                ];
            }
        }

        foreach ($relations as $relationName => $relation) {
            if (($relation['type'] ?? null) !== 'morph_to') {
                continue;
            }

            if (! in_array("{$relationName}_type", $columns, true) || ! in_array("{$relationName}_id", $columns, true)) {
                $mismatches[$entityName]["{$relationName}_columns"] = [
                    'table' => $table,
                    'missing' => array_values(array_filter([
                        in_array("{$relationName}_type", $columns, true) ? null : "{$relationName}_type",
                        in_array("{$relationName}_id", $columns, true) ? null : "{$relationName}_id",
                    ])),
                ];
            }
        }
    }

    expect($mismatches)->toBe([]);
});

/**
 * @return array<string, array<string, mixed>>
 */
function packageOntologyEntities(): array
{
    $ontology = Yaml::parseFile(__DIR__.'/../../stubs/ontology.yaml');

    return collect($ontology['entities'])
        ->filter(fn (array $entity): bool => str_starts_with((string) ($entity['model'] ?? ''), 'Xuple\\EvoLayer\\Base\\Models\\'))
        ->filter(fn (array $entity): bool => str_starts_with((string) ($entity['table'] ?? ''), 'evolayer_base_'))
        ->all();
}

/**
 * @param  array<int, string>  $columns
 * @return array<int, string>
 */
function polymorphicColumnPrefixes(array $columns): array
{
    return collect($columns)
        ->filter(fn (string $column): bool => str_ends_with($column, '_type'))
        ->map(fn (string $column): string => substr($column, 0, -5))
        ->filter(fn (string $prefix): bool => in_array("{$prefix}_id", $columns, true))
        ->values()
        ->all();
}
