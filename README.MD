[![Jikan](https://i.imgur.com/ccx3pxo.png)](#jikan-json-snapshot-exporter)

# Jikan JSON snapshot exporter

This project can scrape the complete discoverable MyAnimeList anime and manga catalogs into one local JSON file. It reuses Jikan's existing scraper and serializer directly; no web server or MongoDB instance is involved in an export.

## Create a snapshot

Install the PHP dependencies, create the normal `.env` file, and run:

```bash
composer install
cp .env.dist .env
php artisan export:json
```

The default output is `storage/app/exports/jikan.json`. Writes are streamed to a temporary file and promoted only after the export finishes, so an interrupted run leaves the previous downloadable snapshot intact.

Useful options:

```bash
# Fast smoke test with ten records from each dataset and no request delay
php artisan export:json --limit=10 --delay=0

# Export just anime to a public/download directory
php artisan export:json --types=anime --output=public/downloads/jikan.json

# Use checked-in or pre-downloaded ID lists instead of downloading them
php artisan export:json --ids-dir=storage/app/id-lists
```

An ID-list directory must contain `anime.json` and/or `manga.json`. Each file may be a flat JSON array or the `{"sfw": [...], "nsfw": [...]}` format used by [mal-id-cache](https://github.com/purarue/mal-id-cache). By default the exporter downloads those lists itself.

The snapshot has a stable top-level envelope:

```json
{
  "metadata": {
    "schema_version": 1,
    "generated_at": "2026-08-09T00:00:00+00:00",
    "datasets": ["anime", "manga"]
  },
  "data": {
    "anime": [],
    "manga": []
  },
  "summary": {
    "exported": {"anime": 0, "manga": 0},
    "failed": 0
  },
  "errors": []
}
```

Individual scrape failures are listed in `errors`; successful records are still published. Add `--fail-on-error` when a scheduler should treat any skipped record as a failed run. The default three-second delay is intentionally conservative. A full first export is large and can take multiple days, so run it on a machine whose scheduler allows long jobs. A weekly cron entry is:

```cron
0 2 * * 0 cd /path/to/jikan-rest && php artisan export:json --output=/path/to/downloads/jikan.json >> storage/logs/export.log 2>&1
```

The built-in Laravel schedule also runs `export:json` at 02:00 each Sunday (UTC with the supplied environment). If you use it, invoke `php artisan schedule:run` from cron each minute as usual. The supplied file cache lets the overlap guard prevent a second export from starting while one is still running.

## GitHub Actions publishing

The `Weekly JSON snapshot` workflow installs PHP and Composer dependencies, runs the exporter, validates the result, and commits `exports/jikan.json` back to the repository through Git LFS. It runs every Sunday at 02:00 UTC and can also be started manually with configurable dataset and delay inputs.

The full scrape is intentionally assigned to a self-hosted Linux x64 runner with the custom `jikan-export` label. A complete rate-limited catalog scrape can exceed the six-hour limit of a GitHub-hosted job. Install Git LFS on that runner and add the `jikan-export` label when registering it.

Create a repository secret named `JSON_SNAPSHOT_TOKEN` containing a fine-grained personal access token with Contents read/write access to this repository. This is required because the automatic `GITHUB_TOKEN` expires after 24 hours, while a complete export may run longer. The token must also be allowed to update the default branch under its branch-protection rules.

The exporter currently covers anime and manga because those are the datasets for which the existing application has complete bulk ID discovery. User-specific and transient endpoint data (for example users, reviews, news, and forum posts) is intentionally not included in the global snapshot.

## Legacy REST API

The existing REST implementation remains in the repository for compatibility, but it is not used by `export:json` and does not need to be hosted to create snapshots.

# Jikan REST API v4 - Unofficial MyAnimeList.net REST API
[![Average time to resolve an issue](http://isitmaintained.com/badge/resolution/jikan-me/jikan-rest.svg)](http://isitmaintained.com/project/jikan-me/jikan-rest "Average time to resolve an issue") [![Percentage of issues still open](http://isitmaintained.com/badge/open/jikan-me/jikan-rest.svg)](http://isitmaintained.com/project/jikan-me/jikan-rest "Percentage of issues still open") [![stable](https://img.shields.io/badge/PHP-^8.1-blue.svg?style=flat)]() [![Discord Server](https://img.shields.io/discord/460491088004907029.svg?style=flat&logo=discord)](http://discord.jikan.moe)

Jikan is a REST API for [MyAnimeList.net](https://myanimelist.net). It scrapes the website to satisfy the need for API functionality that MyAnimeList.net lacks.

The raison d'être of Jikan is to assist developers easily get the data they need for their apps and projects without having to depend on unstable APIs, or sidetracking their projects to develop parsers.

The word _Jikan_ literally translates to _Time_ in Japanese (**時間**). And that's what this API saves you of. ;)

**Notice**: Jikan REST API does not support authenticated requests. You can not update your lists. Use the official MyAnimeList API for this!


## Installation

### NOTICE: v3
This is Jikan REST API **v4** - which has been merged into master branch. If you're looking for deploying a v3 build, view the v3 branch for installation instructions:
https://github.com/jikan-me/jikan-rest/tree/v3

⚠ v3 is discontinued and will no longer receive any updates.

### Manual installation
Please read the [manual installation guide](https://github.com/jikan-me/jikan-rest/wiki).
For any additional help, join our [Discord server](http://discord.jikan.moe/).

### 🐳 Docker Installation
We distribute the app as a container image, so you can just run it:
```bash
docker run -d --name=jikan-rest -p 8080:8080 -v ./.env:/app/.env jikanme/jikan-rest:latest
```

For more information please refer to our [container usage guide](container_usage.md).

## Public REST API
If you don't want to host your instance, there's a public API available.

- *[Apps/Projects using the REST API](https://jikan.moe/showcase)*

### Documentation
Please view the [documentation](https://docs.api.jikan.moe/).
For any additional help, join our [Discord server](http://discord.jikan.moe/).

## Wrappers

| Language   | Wrappers |
|------------|----------|
| JavaScript | [JikanJS](https://github.com/zuritor/jikanjs) by Zuritor<br>🆕 **(v4)** [JikanJS](https://github.com/mateoaranda/jikanjs) by Mateo Aranda |
| Java       | [Jikan4java](https://github.com/Doomsdayrs/Jikan4java) by Doomsdayrs<br>🆕 **(v4)** [reactive-jikan](https://github.com/SandroHc/reactive-jikan) by Sandro Marques<br>🆕 **(v4)** [Jaikan](https://github.com/ShindouMihou/Jaikan) by ShindouMihou |
| Python     | **(v4)** [JikanPy](https://github.com/abhinavk99/jikanpy) by Abhinav Kasamsetty<br>🆕 **(v4)** [jikan4snek](https://github.com/ScathachGrip/jikan4snek) by Indrawan I. |
| Node.js    | [jikan-node](https://github.com/xy137/jikan-node) by xy137<br>[jikan-nodejs](https://github.com/ribeirogab/jikan-nodejs) by ribeirogab<br>🆕 **(v4)** [Jikan4JS](https://github.com/rizzzigit/jikan4.js) by RizzziGit<br>🆕 **(v4)** [jikan-api.js](https://github.com/OpenianDevelopment/jikan-api.js) by OpenianDev |
| TypeScript | [jikants](https://github.com/Julien-Broyard/jikants) by Julien Broyard<br>[jikan-client](https://github.com/javi11/jikan-client) by Javier Blanco<br>🆕 **(v4)** [jikan-ts](https://github.com/tutkli/jikan-ts) by Clara Castillo<br>🆕 **(v4)** [jikan-api-lightweight-client](https://github.com/HRAshton/jikan-api-lightweight-client) by HRAshton |
| PHP        | [jikan-php](https://github.com/janvernieuwe/jikan-jikanPHP) by Jan Vernieuwe |
| .NET       | 🆕 **(v4)** [Jikan.net](https://github.com/Ervie/jikan.net) by Ervie |
| Go         | 🆕 **(v4)** [jikan-go](https://github.com/darenliang/jikan-go) by Daren Liang<br>[jikan2go](https://github.com/nokusukun/jikan2go) by nokusukun |
| Ruby       | [Jikan.rb](https://github.com/Zerocchi/jikan.rb) by Zerocchi |
| Dart       | [jikan-dart](https://github.com/charafau/jikan-dart) by Rafal Wachol |
| Kotlin     | [JikanKt](https://github.com/GSculerlor/JikanKt) by Ganedra Afrasya |
| Clojure    | 🆕 **(v4)** [jikan-clj](https://github.com/AdaiasMagdiel/jikan-clj) by Adaías Magdiel |

[Add your wrapper here](https://github.com/jikan-me/jikan-rest/edit/master/README.MD)

## Running Tests

`php vendor/bin/phpunit tests`

Note: Tests may fail due to rate limit from MyAnimeList (HTTP 429)

---
## Backers

A huge thank you to all our Patrons! 🙏 This project wouldn't be running without your support.

We have a free [REST API service](https://jikan.moe), if you wish to support us you can [become a Patron!](https://patreon.com/jikan)

### Sugoi (すごい) Patrons

- [Jared Allard (jaredallard)](https://github.com/jaredallard)
- [hugonun (hug_onun)](https://twitter.com/hug_onun)

### Patrons

- Aaron Treinish
- Abdelhafid Achtaou
- Aika Fujiwara
- Bobby Williams
- Cesar Irad Mendoza
- CrafterSama
- Fro116
- Jason Weatherly
- Jesse
- Kundan Chintamaneni
- Kururin
- Purplepinapples
- Ryo Ando
- Sakamotodesu
- TeraNovaLP

## Development

|||
|------------|----------|
| ![JetBrain](https://user-images.githubusercontent.com/9166451/126047249-9e5bdc63-ae91-4082-bca5-ffe271b421da.png) | Jikan's development is powered by [JetBrain's Open Source License](https://jb.gg/OpenSource) |

A shoutout to their amazing products and for supporting Jikan since early versions!

---

# DISCLAIMER

- Jikan is not affiliated with MyAnimeList.net
- You are responsible for the usage of this API. Please be respectful towards MyAnimeList's [Terms Of Service](https://myanimelist.net/about/terms_of_use)
