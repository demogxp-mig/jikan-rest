<?php

namespace Tests\Unit;

use App\Services\MalIdProvider;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class MalIdProviderTest extends TestCase
{
    public function testItCombinesSortsAndDeduplicatesCacheIds(): void
    {
        $provider = new MalIdProvider(static fn (string $source): string => json_encode([
            'sfw' => [8, 2, 8],
            'nsfw' => ['5'],
        ], JSON_THROW_ON_ERROR));

        self::assertSame([2, 5, 8], $provider->idsFor('anime'));
        self::assertSame([2, 5], $provider->idsFor('anime', null, 2));
    }

    public function testItRejectsUnsupportedDatasets(): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new MalIdProvider(static fn (): string => '[]'))->idsFor('characters');
    }
}
