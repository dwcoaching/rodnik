<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\Artisan;

class CreateExports implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 0; // No timeout limit for long-running export

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct()
    {
        //$this->onQueue('exports'); // Use dedicated queue for exports
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        // Run the full export artisan command
        Artisan::call('export:full');
    }
}
