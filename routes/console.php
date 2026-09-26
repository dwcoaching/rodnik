<?php

declare(strict_types=1);

use App\Models\Track;
use Illuminate\Support\Facades\Schedule;

/*
|--------------------------------------------------------------------------
| Console Routes
|--------------------------------------------------------------------------
|
| This file is where you may define all of your Closure based console
| commands. Each Closure is bound to a command instance allowing a
| simple approach to interacting with each command's IO methods.
|
*/

Schedule::command('export:full')->cron('30 22 * * *');
Schedule::command('model:prune', ['--model' => [Track::class]])->dailyAt('04:00');
