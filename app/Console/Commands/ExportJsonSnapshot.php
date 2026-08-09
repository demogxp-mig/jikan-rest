<?php

namespace App\Console\Commands;

use App\Anime;
use App\Manga;
use App\Services\JsonSnapshotExporter;
use App\Services\MalIdProvider;
use Illuminate\Console\Command;
use Throwable;

final class ExportJsonSnapshot extends Command
{
    protected $signature = 'export:json
        {--types=anime,manga : Comma-separated datasets to export}
        {--output=storage/app/exports/jikan.json : Snapshot file path}
        {--ids-dir= : Directory containing anime.json and manga.json ID lists}
        {--delay=3 : Seconds to wait between MyAnimeList requests}
        {--limit=0 : Limit each dataset (useful for smoke tests)}
        {--fail-on-error : Return a failure status if any record cannot be scraped}';

    protected $description = 'Scrape MyAnimeList into one downloadable JSON snapshot';

    public function handle(JsonSnapshotExporter $exporter, MalIdProvider $idProvider): int
    {
        $types = array_values(array_filter(array_map('trim', explode(',', (string) $this->option('types')))));
        $types = array_values(array_unique($types));
        $supported = ['anime', 'manga'];
        if ($types === [] || array_diff($types, $supported) !== []) {
            $this->error('Types must contain anime, manga, or both.');
            return self::FAILURE;
        }

        $delay = filter_var($this->option('delay'), FILTER_VALIDATE_INT);
        $limit = filter_var($this->option('limit'), FILTER_VALIDATE_INT);
        if ($delay === false || $delay < 0 || $limit === false || $limit < 0) {
            $this->error('Delay and limit must be non-negative integers.');
            return self::FAILURE;
        }

        $output = (string) $this->option('output');
        if (!$this->isAbsolutePath($output)) {
            $output = base_path($output);
        }

        $idsDirectory = $this->option('ids-dir');
        if (is_string($idsDirectory) && $idsDirectory !== '' && !$this->isAbsolutePath($idsDirectory)) {
            $idsDirectory = base_path($idsDirectory);
        }

        try {
            $ids = [];
            foreach ($types as $type) {
                $this->info("Loading {$type} IDs...");
                $ids[$type] = $idProvider->idsFor($type, $idsDirectory ?: null, $limit);
            }

            $scrapers = [
                'anime' => static fn (int $id): array => Anime::scrape($id),
                'manga' => static fn (int $id): array => Manga::scrape($id),
            ];

            $result = $exporter->export(
                $output,
                $ids,
                $scrapers,
                $delay,
                function (string $dataset, int $position, int $total): void {
                    if ($position === 1 || $position === $total || $position % 100 === 0) {
                        $this->line("Scraping {$dataset} {$position}/{$total}");
                    }
                }
            );
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());
            return self::FAILURE;
        }

        foreach ($result['exported'] as $dataset => $count) {
            $this->info("Exported {$count} {$dataset} records.");
        }
        $this->info("Snapshot written to {$output}");

        if ($result['failed'] > 0) {
            $this->warn("{$result['failed']} records failed; details are included in the snapshot.");
        }

        return $this->option('fail-on-error') && $result['failed'] > 0
            ? self::FAILURE
            : self::SUCCESS;
    }

    private function isAbsolutePath(string $path): bool
    {
        return str_starts_with($path, '/') || preg_match('/^[A-Za-z]:[\\\\\/]/', $path) === 1;
    }
}
