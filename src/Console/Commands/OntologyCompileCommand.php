<?php

namespace EvoDevOps\Base\Console\Commands;

use EvoDevOps\Base\Support\OntologyCompiler;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use RuntimeException;

#[Signature('ontology:compile
    {--source=ontology.yaml : Source ontology YAML path}
    {--output=bootstrap/cache/ontology.php : Compiled PHP cache path}
    {--types=resources/js/types/ontology.ts : Generated TypeScript contract path}
    {--erd=bootstrap/cache/ontology.mmd : Generated Mermaid ERD path}
    {--no-types : Skip TypeScript contract output}
    {--no-erd : Skip Mermaid ERD output}')]
#[Description('Validate and compile the ontology source into cache, TypeScript, and ERD artifacts')]
class OntologyCompileCommand extends Command
{
    public function handle(OntologyCompiler $compiler, Filesystem $files): int
    {
        try {
            $source = $this->resolvePath($this->option('source'));
            $output = $this->resolvePath($this->option('output'));

            $compiled = $compiler->compile($source);

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

        $this->components->success("Compiled ontology to {$output}");

        return self::SUCCESS;
    }

    private function resolvePath(mixed $path): string
    {
        if (! is_string($path) || $path === '') {
            throw new RuntimeException('Path options must be non-empty strings.');
        }

        return str_starts_with($path, DIRECTORY_SEPARATOR) ? $path : base_path($path);
    }
}
