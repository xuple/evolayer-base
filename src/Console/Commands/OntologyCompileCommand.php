<?php

namespace EvoDevOps\Base\Console\Commands;

use EvoDevOps\Base\Support\OntologyCompiler;
use EvoDevOps\Base\Support\OntologyRegistry;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use RuntimeException;

#[Signature('ontology:compile
    {--source= : Compile a single ontology file (legacy mode). Omit to merge all registered package ontologies + the host ontology.}
    {--host-source=ontology.yaml : Host ontology YAML path merged under the "app" namespace when present}
    {--output=bootstrap/cache/ontology.php : Compiled PHP cache path}
    {--types=resources/js/types/ontology.ts : Generated TypeScript contract path}
    {--erd=bootstrap/cache/ontology.mmd : Generated Mermaid ERD path}
    {--no-types : Skip TypeScript contract output}
    {--no-erd : Skip Mermaid ERD output}')]
#[Description('Validate and compile registered ontologies (Base + variants + host) into cache, TypeScript, and ERD artifacts')]
class OntologyCompileCommand extends Command
{
    public function handle(OntologyCompiler $compiler, OntologyRegistry $registry, Filesystem $files): int
    {
        try {
            $output = $this->resolvePath($this->option('output'));

            // Legacy single-file mode when --source is given; otherwise merge
            // every registered package ontology plus the host's own.
            if (is_string($this->option('source')) && $this->option('source') !== '') {
                $compiled = $compiler->compile($this->resolvePath($this->option('source')));
                $warnings = $compiled['warnings'] ?? [];
            } else {
                $hostSource = $this->resolveHostSource($this->option('host-source'));
                $compiled = $compiler->compileAll($registry, $hostSource);
                $warnings = $this->collectMergedWarnings($compiled);
            }

            $files->ensureDirectoryExists(dirname($output));
            $files->put($output, "<?php\n\nreturn ".var_export($compiled, true).";\n");

            if (! $this->option('no-types')) {
                $types = $this->resolvePath($this->option('types'));
                $files->ensureDirectoryExists(dirname($types));
                $files->put($types, $compiler->toTypeScript($compiled));
            }

            if (! $this->option('no-erd')) {
                $erd = $this->resolvePath($this->option('erd'));
                $files->ensureDirectoryExists(dirname($erd));
                $files->put($erd, $compiler->toMermaidErd($compiled));
            }
        } catch (RuntimeException $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }

        foreach ($warnings as $warning) {
            $this->components->warn($warning);
        }

        $this->components->success("Compiled ontology to {$output}");

        return self::SUCCESS;
    }

    /**
     * @param  array<string, mixed>  $compiled
     * @return list<string>
     */
    private function collectMergedWarnings(array $compiled): array
    {
        $warnings = [];

        foreach ($compiled['namespaces'] ?? [] as $namespace => $ns) {
            foreach ($ns['warnings'] ?? [] as $warning) {
                $warnings[] = "[{$namespace}] {$warning}";
            }
        }

        return $warnings;
    }

    private function resolveHostSource(mixed $path): ?string
    {
        if (! is_string($path) || $path === '') {
            return null;
        }

        $resolved = $this->resolvePath($path);

        return is_file($resolved) ? $resolved : null;
    }

    private function resolvePath(mixed $path): string
    {
        if (! is_string($path) || $path === '') {
            throw new RuntimeException('Path options must be non-empty strings.');
        }

        return str_starts_with($path, DIRECTORY_SEPARATOR) ? $path : base_path($path);
    }
}
