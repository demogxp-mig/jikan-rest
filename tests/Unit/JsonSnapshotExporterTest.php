<?php

namespace Tests\Unit;

use App\Services\JsonSnapshotExporter;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class JsonSnapshotExporterTest extends TestCase
{
    private string $outputPath;

    protected function setUp(): void
    {
        parent::setUp();
        $this->outputPath = sys_get_temp_dir().DIRECTORY_SEPARATOR.'jikan-snapshot-'.bin2hex(random_bytes(4)).'.json';
    }

    protected function tearDown(): void
    {
        @unlink($this->outputPath);
        @unlink($this->outputPath.'.previous');
        parent::tearDown();
    }

    public function testItStreamsDatasetsAndRecordsFailuresInValidJson(): void
    {
        $progress = [];
        $result = (new JsonSnapshotExporter())->export(
            $this->outputPath,
            ['anime' => [1, 2], 'manga' => [3]],
            [
                'anime' => static function (int $id): array {
                    if ($id === 2) {
                        throw new RuntimeException('not found');
                    }
                    return ['mal_id' => $id, 'title' => 'Anime'];
                },
                'manga' => static fn (int $id): array => ['mal_id' => $id, 'title' => 'Manga'],
            ],
            0,
            static function (string $dataset, int $position, int $total) use (&$progress): void {
                $progress[] = [$dataset, $position, $total];
            },
            new DateTimeImmutable('2026-08-09T00:00:00+00:00')
        );

        $snapshot = json_decode(file_get_contents($this->outputPath), true, 512, JSON_THROW_ON_ERROR);

        self::assertSame(['exported' => ['anime' => 1, 'manga' => 1], 'failed' => 1], $result);
        self::assertSame(1, $snapshot['metadata']['schema_version']);
        self::assertSame(['anime', 'manga'], $snapshot['metadata']['datasets']);
        self::assertSame([['mal_id' => 1, 'title' => 'Anime']], $snapshot['data']['anime']);
        self::assertSame([['mal_id' => 3, 'title' => 'Manga']], $snapshot['data']['manga']);
        self::assertSame(2, $snapshot['errors'][0]['mal_id']);
        self::assertSame([
            ['anime', 1, 2],
            ['anime', 2, 2],
            ['manga', 1, 1],
        ], $progress);
    }

    public function testItReplacesAnExistingSnapshot(): void
    {
        file_put_contents($this->outputPath, '{"old":true}');

        (new JsonSnapshotExporter())->export(
            $this->outputPath,
            ['anime' => [1]],
            ['anime' => static fn (int $id): array => ['mal_id' => $id]],
            0
        );

        $snapshot = json_decode(file_get_contents($this->outputPath), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame(1, $snapshot['data']['anime'][0]['mal_id']);
        self::assertFileDoesNotExist($this->outputPath.'.previous');
    }

    public function testItPreservesAnExistingSnapshotWhenAWholeDatasetFails(): void
    {
        file_put_contents($this->outputPath, '{"old":true}');

        try {
            (new JsonSnapshotExporter())->export(
                $this->outputPath,
                ['anime' => [1]],
                ['anime' => static function (): array {
                    throw new RuntimeException('upstream unavailable');
                }],
                0
            );
            self::fail('The exporter should reject an entirely failed dataset.');
        } catch (RuntimeException $exception) {
            self::assertStringContainsString('Every anime record failed', $exception->getMessage());
        }

        self::assertSame('{"old":true}', file_get_contents($this->outputPath));
    }
}
