<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('demo:photos {--force : Re-download photos even if they already exist}')]
#[Description('Download dish photos from Wikipedia into public/photos/<tenant>/<slug>.jpg.')]
class DemoDownloadPhotos extends Command
{
    private const USER_AGENT = 'MenuQR-Demo/1.0 (https://example.com; demo seeder)';

    public function handle(): int
    {
        $data = require database_path('seeders/data/menu-data.php');
        $force = (bool) $this->option('force');

        $totals = ['ok' => 0, 'skip' => 0, 'fail' => 0];

        foreach ($data as $tenantSlug => $shop) {
            $dir = public_path("photos/{$tenantSlug}");
            if (! is_dir($dir)) {
                mkdir($dir, 0755, true);
            }

            $this->line('');
            $this->info("[{$tenantSlug}]");

            foreach ($shop['items'] as $item) {
                $dest = "{$dir}/{$item['slug']}.jpg";

                if (file_exists($dest) && ! $force) {
                    $this->line("  [skip] {$item['slug']}");
                    $totals['skip']++;
                    continue;
                }

                $article = $item['wiki_article'] ?? null;
                if (! $article) {
                    $this->line("  [skip] {$item['slug']} (no wiki article configured)");
                    $totals['skip']++;
                    continue;
                }

                $url = $this->resolveWikipediaImage($article);
                if (! $url) {
                    $this->warn("  [fail] {$item['slug']} (no thumbnail for '{$article}')");
                    $totals['fail']++;
                    continue;
                }

                if ($this->downloadTo($url, $dest)) {
                    $this->line("  [ok]   {$item['slug']}");
                    $totals['ok']++;
                } else {
                    $this->warn("  [fail] {$item['slug']} (download failed)");
                    $totals['fail']++;
                }
            }
        }

        $this->line('');
        $this->info("done: {$totals['ok']} downloaded, {$totals['skip']} skipped, {$totals['fail']} failed.");

        return self::SUCCESS;
    }

    private function resolveWikipediaImage(string $article): ?string
    {
        $slug = str_replace(' ', '_', trim($article));
        $url = 'https://en.wikipedia.org/api/rest_v1/page/summary/'.rawurlencode($slug);

        $body = $this->httpGet($url);
        if (! $body) {
            return null;
        }
        $json = json_decode($body, true);
        if (! is_array($json)) {
            return null;
        }
        return $json['originalimage']['source']
            ?? $json['thumbnail']['source']
            ?? null;
    }

    private function httpGet(string $url): ?string
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_USERAGENT => self::USER_AGENT,
            CURLOPT_TIMEOUT => 20,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_HTTPHEADER => ['Accept: application/json'],
        ]);
        $body = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if ($code !== 200 || ! is_string($body)) {
            return null;
        }
        return $body;
    }

    private function downloadTo(string $url, string $dest): bool
    {
        $fp = fopen($dest, 'w');
        if (! $fp) {
            return false;
        }

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_FILE => $fp,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_USERAGENT => self::USER_AGENT,
            CURLOPT_TIMEOUT => 60,
            CURLOPT_CONNECTTIMEOUT => 10,
        ]);
        $ok = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        fclose($fp);

        if (! $ok || $code !== 200 || filesize($dest) < 1024) {
            @unlink($dest);
            return false;
        }
        return true;
    }
}
