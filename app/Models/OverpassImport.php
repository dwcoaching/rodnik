<?php

namespace App\Models;

use App\Models\OSMTag;
use App\Models\Spring;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class OverpassImport extends Model
{
    use HasFactory;

    protected $cachedArea = null;

    public function fetch()
    {
        $guzzle = new Client;

        $this->started_at = now();

        $result = $guzzle->request('GET', 'https://overpass-api.de/api/interpreter', [
            'query' => [
                'data' => $this->query
            ]
        ]);

        $this->response = $result->getBody();

        $this->fetched_at = now();
        $this->save();
    }

    public function getResponseAttribute()
    {
        if (Storage::disk('local')->exists($this->responsePath)) {
            return Storage::disk('local')->get($this->responsePath);
        }

        return null;
    }

    public function setResponseAttribute($response)
    {
        Storage::disk('local')->put($this->responsePath, $response);
    }

    public function getResponsePathAttribute()
    {
        return 'overpass/responses/' . $this->id . '.json';
    }

    public function getAreaAttribute()
    {
        if (! $this->cachedArea) {
            $this->cachedArea = '('
                . $this->latitude_from
                . ','
                . $this->longitude_from
                . ','
                . $this->latitude_to
                . ','
                . $this->longitude_to
                . ');';
        }

        return $this->cachedArea;
    }

    public function getQueryAttribute()
    {
        $query = "
            [out:json];

            node
              [natural=spring]
              {$this->area}
            out;
            node
              [man_made=spring_box]
              {$this->area}
            out;
            node
              [man_made=water_well]
              {$this->area}
            out;
            node
              [man_made=water_tap]
              {$this->area}
            out;
            node
              [amenity=drinking_water]
              {$this->area}
            out;
            node
              [amenity=fountain]
              {$this->area}
            out;
            node
              [man_made=drinking_fountain]
              {$this->area}
            out;
            node
              [amenity=water_point]
              {$this->area}
            out;
            node
              [waterway=water_point]
              {$this->area}
            out;
            node
              [water_point=yes]
              {$this->area}
            out;
            node
              [drinking_water]
              {$this->area}
            out;
            node
              [\"drinking_water:seasonal\"]
              {$this->area}
            out;
            node
              [\"drinking_water:legal\"]
              {$this->area}
            out;

            way
              [natural=spring]
              {$this->area}
            out center;
            way
              [man_made=spring_box]
              {$this->area}
            out center;
            way
              [man_made=water_well]
              {$this->area}
            out center;
            way
              [man_made=water_tap]
              {$this->area}
            out center;
            way
              [amenity=drinking_water]
              {$this->area}
            out center;
            way
              [amenity=fountain]
              {$this->area}
            out center;
            way
              [man_made=drinking_fountain]
              {$this->area}
            out center;
            way
              [amenity=water_point]
              {$this->area}
            out center;
            way
              [waterway=water_point]
              {$this->area}
            out center;
            way
              [water_point=yes]
              {$this->area}
            out center;
            way
              [drinking_water]
              {$this->area}
            out center;
            way
              [\"drinking_water:seasonal\"]
              {$this->area}
            out center;
            way
              [\"drinking_water:legal\"]
              {$this->area}
            out center;
        ";

        return $query;
    }

    public function parse()
    {
        $json = json_decode($this->response);

        $existing = 0;
        $new = 0;

        foreach ($json->elements as $element) {

            switch ($element->type) {
                case 'node':
                    $spring = Spring::where('osm_node_id', $element->id)->first();
                    break;
                case 'way':
                    $spring = Spring::where('osm_way_id', $element->id)->first();
                    break;
            }

            if (! $spring) {
                $new = $new + 1;
                $spring = new Spring();

                switch ($element->type) {
                    case 'node':
                        $spring->osm_node_id = $element->id;
                        $spring->latitude = $element->lat;
                        $spring->longitude = $element->lon;
                        break;
                    case 'way':
                        $spring->osm_way_id = $element->id;
                        $spring->latitude = $element->center->lat;
                        $spring->longitude = $element->center->lon;
                        break;
                }
            } else {
                $existing = $existing + 1;
            }

            $spring->save();

            DB::table('osm_tags')->where('spring_id', '=', $spring->id)->delete();

            foreach ($element->tags as $key => $value) {
                $osmTag = new OSMTag();
                $osmTag->key = $key;
                $osmTag->value = $value;
                $osmTag->spring_id = $spring->id;
                $osmTag->save();
            };
        }

        echo 'new: ' . $new . "\n";
        echo 'existing: ' . $existing . "\n";
    }
}
