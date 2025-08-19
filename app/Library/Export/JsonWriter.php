<?php

namespace App\Library\Export;

use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Illuminate\Database\Eloquent\Builder;

class JsonWriter
{
    public ?User $user = null;

    public function __construct(
        public Builder $query
    ) {}

    public function forUser(?User $user = null): static
    {
        $this->user = $user;
        return $this;
    }

    public function save(): void
    {
        $allSprings = [];
        
        $this->query->chunk(500, function ($springs) use (&$allSprings) {
            $processedSprings = (new JsonTransformer($springs))->forUser($this->user)->transform();
            $allSprings = array_merge($allSprings, $processedSprings);
            echo "Processed " . count($allSprings) . " springs\n";
        });

        $finalJson = json_encode($allSprings, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        if ($this->user) {
            $filename = 'rodnik-user-' . $this->user->id . '.json';
        } else {
            $filename = 'rodnik.json';
        }
        
        Storage::disk('public')->put($filename, $finalJson);   
    }
}