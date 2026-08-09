<?php

namespace App\Services;

use DateTimeImmutable;
use RuntimeException;
use Throwable;

final class JsonSnapshotExporter
{
    /**
     * @param array<string, iterable<int>> $idsByDataset
     * @param array<string, callable(int): array> $scrapers
     * @param null|callable(string, int, int): void $onProgress
     * @return array{exported: array<string, int>, failed: int}
     */
    public function export(
        string $outputPath,
        array $idsByDataset,
        array $scrapers,
        int $delaySeconds = 3,
        ?callable $onProgress = null,
        ?DateTimeImmutable $generatedAt = null
    ): array {
        if ($delaySeconds < 0) {
            throw new RuntimeException('Delay cannot be negative.');
        }

        $directory = dirname($outputPath);
        if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
            throw new RuntimeException("Unable to create output directory: {$directory}");
        }

        $temporaryPath = $outputPath.'.tmp.'.bin2hex(random_bytes(6));
        $stream = fopen($temporaryPath, 'wb');
        if ($stream === false) {
            throw new RuntimeException("Unable to create temporary snapshot: {$temporaryPath}");
        }

        $errors = [];
        $counts = [];
        $requestsMade = 0;

        try {
            $this->write($stream, '{"metadata":');
            $this->writeJson($stream, [
                'schema_version' => 1,
                'generated_at' => ($generatedAt ?? new DateTimeImmutable())->format(DATE_ATOM),
                'datasets' => array_keys($idsByDataset),
            ]);
            $this->write($stream, ',"data":{');

            $firstDataset = true;
            foreach ($idsByDataset as $dataset => $ids) {
                if (!isset($scrapers[$dataset])) {
                    throw new RuntimeException("No scraper configured for dataset: {$dataset}");
                }

                if (!$firstDataset) {
                    $this->write($stream, ',');
                }
                $firstDataset = false;
                $this->writeJson($stream, $dataset);
                $this->write($stream, ':[');

                $ids = is_array($ids) ? array_values($ids) : iterator_to_array($ids, false);
                $total = count($ids);
                $written = 0;
                foreach ($ids as $position => $id) {
                    if ($onProgress !== null) {
                        $onProgress($dataset, $position + 1, $total);
                    }
                    try {
                        if ($delaySeconds > 0 && $requestsMade > 0) {
                            sleep($delaySeconds);
                        }
                        $requestsMade++;
                        $record = $scrapers[$dataset]((int) $id);
                        $encodedRecord = $this->encodeJson($record);
                    } catch (Throwable $exception) {
                        $errors[] = [
                            'dataset' => $dataset,
                            'mal_id' => (int) $id,
                            'message' => $exception->getMessage(),
                        ];
                        continue;
                    }

                    if ($written > 0) {
                        $this->write($stream, ',');
                    }
                    $this->write($stream, $encodedRecord);
                    $written++;

                }

                if ($total > 0 && $written === 0) {
                    throw new RuntimeException("Every {$dataset} record failed; the previous snapshot was preserved.");
                }

                $counts[$dataset] = $written;
                $this->write($stream, ']');
            }

            $this->write($stream, '},"summary":');
            $this->writeJson($stream, ['exported' => $counts, 'failed' => count($errors)]);
            $this->write($stream, ',"errors":');
            $this->writeJson($stream, $errors);
            $this->write($stream, '}');
            if (!fflush($stream)) {
                throw new RuntimeException('Unable to flush snapshot to disk.');
            }
        } catch (Throwable $exception) {
            fclose($stream);
            @unlink($temporaryPath);
            throw $exception;
        }

        fclose($stream);
        $this->promote($temporaryPath, $outputPath);

        return ['exported' => $counts, 'failed' => count($errors)];
    }

    /** @param resource $stream */
    private function write($stream, string $value): void
    {
        $remaining = $value;
        while ($remaining !== '') {
            $written = fwrite($stream, $remaining);
            if ($written === false || $written === 0) {
                throw new RuntimeException('Unable to write snapshot data.');
            }
            $remaining = substr($remaining, $written);
        }
    }

    /** @param resource $stream */
    private function writeJson($stream, mixed $value): void
    {
        $this->write($stream, $this->encodeJson($value));
    }

    private function encodeJson(mixed $value): string
    {
        return json_encode(
            $value,
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
        );
    }

    private function promote(string $temporaryPath, string $outputPath): void
    {
        $backupPath = $outputPath.'.previous';
        if (is_file($backupPath)) {
            @unlink($backupPath);
        }

        if (is_file($outputPath) && !rename($outputPath, $backupPath)) {
            @unlink($temporaryPath);
            throw new RuntimeException("Unable to preserve existing snapshot: {$outputPath}");
        }

        if (!rename($temporaryPath, $outputPath)) {
            if (is_file($backupPath)) {
                @rename($backupPath, $outputPath);
            }
            @unlink($temporaryPath);
            throw new RuntimeException("Unable to publish snapshot: {$outputPath}");
        }

        if (is_file($backupPath)) {
            @unlink($backupPath);
        }
    }
}
