<?php

namespace App\Console;

use App\Console\Commands\CacheRemove;
use App\Console\Commands\ExportJsonSnapshot;
use App\Console\Commands\Indexer;
use Illuminate\Console\Scheduling\Schedule;
use Laravel\Lumen\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * The Artisan commands provided by your application.
     *
     * @var array
     */
    protected $commands = [
        CacheRemove::class,
        ExportJsonSnapshot::class,
        Indexer\CommonIndexer::class,
        Indexer\AnimeScheduleIndexer::class,
        Indexer\CurrentSeasonIndexer::class,
        Indexer\AnimeIndexer::class,
        Indexer\MangaIndexer::class,
        Indexer\GenreIndexer::class,
        Indexer\ProducersIndexer::class,
        Indexer\AnimeSweepIndexer::class,
        Indexer\MangaSweepIndexer::class,
        Indexer\IncrementalIndexer::class
    ];

    /**
     * Define the application's command schedule.
     *
     * @param  \Illuminate\Console\Scheduling\Schedule  $schedule
     * @return void
     */
    protected function schedule(Schedule $schedule)
    {
        $schedule->command('export:json')
            ->weeklyOn(0, '02:00')
            ->withoutOverlapping();
    }
}
