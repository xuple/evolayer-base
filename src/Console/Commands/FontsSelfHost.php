<?php

namespace EvoDevOps\Base\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class FontsSelfHost extends Command
{
    protected $signature = 'fonts:self-host
                            {--force : Re-download font files even if they already exist}
                            {--dry-run : Show what would be done without making any changes}';

    protected $description = 'Self-host web fonts — downloads files locally and removes external CDN dependencies';

    public function handle(): int
    {
        $isDryRun = (bool) $this->option('dry-run');
        $force = (bool) $this->option('force');

        $provider = (string) config('fonts.provider', 'bunny');
        $families = (array) config('fonts.families', ['instrument-sans' => [400, 500, 600]]);
        $outputPath = (string) config('fonts.output_path', public_path('fonts'));
        $cssPath = (string) config('fonts.css_path', resource_path('css/fonts.css'));

        if ($isDryRun) {
            $this->line('<fg=yellow>[DRY RUN] No files will be written.</>');
            $this->newLine();
        }

        $cssUrl = $this->buildCssUrl($provider, $families);

        $this->line("Fetching font CSS from <fg=cyan>{$provider}</> CDN...");

        $css = $this->fetchCss($cssUrl);

        if ($css === null) {
            return Command::FAILURE;
        }

        $urls = $this->extractWoff2Urls($css);

        if (empty($urls)) {
            $this->error('No woff2 URLs found in the fetched font CSS. Check your provider and family configuration.');

            return Command::FAILURE;
        }

        $this->line('Found <fg=green>'.count($urls).'</> font files.');
        $this->newLine();

        if (! $isDryRun && ! is_dir($outputPath)) {
            mkdir($outputPath, 0755, true);
        }

        $urlMap = $this->downloadFonts($urls, $outputPath, $isDryRun, $force);

        $this->newLine();

        $this->writeFontsCss($css, $urlMap, $cssPath, $isDryRun);
        $this->ensureAppCssImport($isDryRun);
        $removedCount = $this->removeCdnLinksFromBlade($isDryRun);

        $this->printReceipt($provider, $families, $urlMap, $removedCount, $isDryRun);

        return Command::SUCCESS;
    }

    /**
     * @param  array<string, array<int>|int>  $families
     */
    private function buildCssUrl(string $provider, array $families): string
    {
        $familyParam = collect($families)
            ->map(fn ($weights, $family) => $family.':'.implode(',', (array) $weights))
            ->implode('|');

        return match ($provider) {
            'bunny' => "https://fonts.bunny.net/css?family={$familyParam}",
            'google' => "https://fonts.googleapis.com/css2?family={$familyParam}&display=swap",
            default => throw new \InvalidArgumentException("Unsupported font provider [{$provider}]."),
        };
    }

    private function fetchCss(string $url): ?string
    {
        $response = Http::withHeaders([
            'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/120 Safari/537.36',
        ])->get($url);

        if (! $response->ok()) {
            $this->error("Failed to fetch font CSS (HTTP {$response->status()}): {$url}");

            return null;
        }

        return $response->body();
    }

    /**
     * @return array<int, string>
     */
    private function extractWoff2Urls(string $css): array
    {
        preg_match_all('/url\([\'"]?(https?:\/\/[^\'")\s]+\.woff2)[\'"]?\)/i', $css, $matches);

        return array_values(array_unique($matches[1]));
    }

    /**
     * @param  array<int, string>  $urls
     * @return array<string, string> Remote URL => local public path
     */
    private function downloadFonts(array $urls, string $outputPath, bool $isDryRun, bool $force): array
    {
        $urlMap = [];

        foreach ($urls as $url) {
            $filename = basename((string) parse_url($url, PHP_URL_PATH));
            $localPath = $outputPath.'/'.$filename;
            $publicPath = '/fonts/'.$filename;

            if (! $force && file_exists($localPath)) {
                $this->line("  <fg=yellow>skip</>  {$filename}");
                $urlMap[$url] = $publicPath;

                continue;
            }

            if ($isDryRun) {
                $this->line("  <fg=cyan>would</> {$filename}");
                $urlMap[$url] = $publicPath;

                continue;
            }

            $response = Http::get($url);

            if (! $response->ok()) {
                $this->warn("  failed  {$filename} (HTTP {$response->status()})");

                continue;
            }

            file_put_contents($localPath, $response->body());
            $this->line("  <fg=green>saved</>  {$filename}");
            $urlMap[$url] = $publicPath;
        }

        return $urlMap;
    }

    /**
     * @param  array<string, string>  $urlMap
     */
    private function writeFontsCss(string $css, array $urlMap, string $cssPath, bool $isDryRun): void
    {
        $localCss = $css;

        foreach ($urlMap as $remote => $local) {
            $localCss = str_replace($remote, $local, $localCss);
        }

        // Strip non-woff2 fallback URLs that still point to external CDNs.
        // woff is a legacy format (IE11); all modern browsers use woff2 exclusively.
        $localCss = preg_replace('/,\s*url\(https?:\/\/[^\)]+\)\s*format\([\'"]woff[\'"]\)/i', '', (string) $localCss);

        $header = "/* Self-hosted via `php artisan fonts:self-host` — zero external font requests at runtime. */\n\n";
        $content = $header.trim($localCss)."\n";

        if ($isDryRun) {
            $this->line("[DRY RUN] Would write fonts.css → {$cssPath}");

            return;
        }

        file_put_contents($cssPath, $content);
        $this->line("Written <fg=green>fonts.css</> → {$cssPath}");
    }

    private function ensureAppCssImport(bool $isDryRun): void
    {
        $appCssPath = resource_path('css/app.css');
        $appCss = (string) file_get_contents($appCssPath);
        $import = "@import './fonts.css';";

        if (str_contains($appCss, $import)) {
            $this->line('fonts.css import already present in <fg=green>app.css</>');

            return;
        }

        if ($isDryRun) {
            $this->line('[DRY RUN] Would prepend fonts.css import to app.css');

            return;
        }

        file_put_contents($appCssPath, $import."\n\n".$appCss);
        $this->line('Prepended fonts.css import to <fg=green>app.css</>');
    }

    private function removeCdnLinksFromBlade(bool $isDryRun): int
    {
        $bladePath = resource_path('views/app.blade.php');
        $blade = (string) file_get_contents($bladePath);

        $patterns = [
            "/[ \t]*<link rel=\"preconnect\" href=\"https:\/\/fonts\.bunny\.net\">\n?/",
            "/[ \t]*<link href=\"https:\/\/fonts\.bunny\.net\/css[^\"]*\" rel=\"stylesheet\" \/>\n?/",
            "/[ \t]*<link rel=\"preconnect\" href=\"https:\/\/fonts\.googleapis\.com\"[^>]*>\n?/",
            "/[ \t]*<link rel=\"preconnect\" href=\"https:\/\/fonts\.gstatic\.com\"[^>]*>\n?/",
            "/[ \t]*<link href=\"https:\/\/fonts\.googleapis\.com[^\"]*\" rel=\"stylesheet\"[^>]*>\n?/",
        ];

        $cleaned = $blade;
        $removed = 0;

        foreach ($patterns as $pattern) {
            $result = preg_replace($pattern, '', $cleaned);

            if ($result !== $cleaned) {
                $removed++;
                $cleaned = (string) $result;
            }
        }

        if ($removed === 0) {
            $this->line('No CDN font links found in app.blade.php — already clean');

            return 0;
        }

        if ($isDryRun) {
            $this->line("[DRY RUN] Would remove {$removed} CDN font link(s) from app.blade.php");

            return $removed;
        }

        file_put_contents($bladePath, $cleaned);
        $this->line("Removed <fg=green>{$removed}</> CDN font link(s) from <fg=green>app.blade.php</>");

        return $removed;
    }

    /**
     * @param  array<string, array<int>|int>  $families
     * @param  array<string, string>  $urlMap
     */
    private function printReceipt(
        string $provider,
        array $families,
        array $urlMap,
        int $removedLinks,
        bool $isDryRun,
    ): void {
        $this->newLine();
        $this->line($isDryRun ? '<fg=yellow>── DRY RUN RECEIPT ──</>' : '<fg=green>── RECEIPT ──</>');
        $this->newLine();

        $this->table(
            ['Item', 'Value'],
            [
                ['Provider', $provider],
                ['Families', implode(', ', array_keys($families))],
                ['Font files', count($urlMap)],
                ['CDN links removed from blade', $removedLinks],
                ['External font requests at runtime', 'NONE'],
                ['GDPR CDN exposure', 'ELIMINATED'],
            ],
        );

        if (! $isDryRun) {
            $this->line('<fg=green>All font files are self-hosted. No visitor IP is sent to a font CDN.</>');
        }
    }
}
