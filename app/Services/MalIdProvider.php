<?php

namespace App\Services;

use InvalidArgumentException;
use RuntimeException;

final class MalIdProvider
{
    private const URLS = [
        'anime' => 'https://raw.githubusercontent.com/purarue/mal-id-cache/master/cache/anime_cache.json',
        'manga' => 'https://raw.githubusercontent.com/purarue/mal-id-cache/master/cache/manga_cache.json',
    ];

    /** @var callable(string): string */
    private $loader;

    public function __construct(?callable $loader = null)
    {
        $this->loader = $loader ?? static function (string $source): string {
            $contents = @file_get_contents($source);
            if ($contents === false) {
                throw new RuntimeException("Unable to read MAL ID source: {$source}");
            }

            return $contents;
        };
    }

    /**
     * @return list<int>
     */
    public function idsFor(string $type, ?string $idsDirectory = null, int $limit = 0): array
    {
        if (!isset(self::URLS[$type])) {
            throw new InvalidArgumentException("Unsupported media type: {$type}");
        }

        $source = $idsDirectory === null
            ? self::URLS[$type]
            : rtrim($idsDirectory, '\\/').DIRECTORY_SEPARATOR."{$type}.json";

        $decoded = json_decode(($this->loader)($source), true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($decoded)) {
            throw new RuntimeException("MAL ID source is not a JSON array: {$source}");
        }

        if (array_key_exists('sfw', $decoded) || array_key_exists('nsfw', $decoded)) {
            $decoded = array_merge($decoded['sfw'] ?? [], $decoded['nsfw'] ?? []);
        }

        $ids = [];
        foreach ($decoded as $id) {
            if (!is_int($id) && !(is_string($id) && ctype_digit($id))) {
                throw new RuntimeException("MAL ID source contains an invalid ID: {$source}");
            }
            $ids[(int) $id] = (int) $id;
        }

        sort($ids, SORT_NUMERIC);

        if ($ids === []) {
            throw new RuntimeException("MAL ID source contains no IDs: {$source}");
        }

        return $limit > 0 ? array_slice($ids, 0, $limit) : array_values($ids);
    }
}
