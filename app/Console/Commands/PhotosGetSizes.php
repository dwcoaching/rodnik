<?php

namespace App\Console\Commands;

use App\Models\Photo;
use Illuminate\Console\Command;
use Intervention\Image\Facades\Image;

class PhotosGetSizes extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'photos:get-sizes';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Run through all uploaded photos and save widths and height to database';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $photos = Photo::whereNull('width')->orWhereNull('height')->get();

        foreach ($photos as $i => $photo) {
            echo "Processing photo id={$photo->id} ({$i} out of {$photos->count()})\n";

            $image = Image::make($photo->fullPath());
            $photo->width = $image->width();
            $photo->height = $image->height();
            $photo->save();
        }
    }
}
