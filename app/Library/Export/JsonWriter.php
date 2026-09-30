<?php

declare(strict_types=1);

namespace App\Library\Export;

use Illuminate\Support\Facades\File;

final class JsonWriter extends Writer
{
    protected function write(): string
    {
        $allSprings = [];

        $this->query->chunk(500, function ($springs) use (&$allSprings) {
            $processedSprings = (new JsonTransformer($springs))->forUser($this->user)->transform();
            $allSprings = array_merge($allSprings, $processedSprings);
        });

        $finalJson = json_encode($allSprings, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $timestamp = now()->format('Y-m-d_H-i-s');

        if ($this->user) {
            $filename = 'rodnik-user-'.$this->user->id.'-from-'.$timestamp.'.json';
        } else {
            $filename = 'rodnik-from-'.$timestamp.'.json';
        }

        return $this->writeAtomically($filename, function (string $path) use ($finalJson): void {
            File::put($path, $finalJson);
        });
    }
}
