<?php

namespace App\Models;

use App\Library\Tile;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class SpringTile extends Model
{
    use HasFactory;

    public function generateFile()
    {
        $json = Tile::createJson($this->z, $this->x, $this->y);

        $this->generated_at = now();
        Storage::disk('tiles')->put($this->getPath(), $json);
        $this->save();
    }

    public function getPath()
    {
        return '/' . $this->z . '/' . $this->x . '/' . $this->y . '.json';
    }
}
