<?php

namespace App\Models;

use GuzzleHttp\Client;
use App\Library\Overpass;
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

        if (false && config('app.env') !== 'production') {
            $this->fake();
        } else {
            $result = $guzzle->request('GET', 'https://overpass-api.de/api/interpreter', [
                'query' => [
                    'data' => $this->query
                ],
                'http_errors' => false
            ]);

            $this->response_code = $result->getStatusCode();
            $this->response_phrase = $result->getReasonPhrase();
            $this->response = $result->getBody();
        }

        $this->fetched_at = now();
        $this->save();
    }

    public function fake() {
        $lottery = rand(0, 100);

        if ($lottery >= 10) {
            $this->fakeSuccess();
        } else {
            $this->fakeFailure();
        }
    }

    public function fakeSuccess() {
        $this->response_code = 200;
        $this->response_phrase = 'OK';
        $this->response = Storage::disk('local')->get('overpass/responses/4577.json');
    }

    public function fakeFailure() {
        $this->response_code = 200;
        $this->response_phrase = 'OK';
        $this->response = Storage::disk('local')->get('overpass/responses/4578.json');
    }

    public function responseHasRemarks()
    {
        $json = json_decode($this->response);

        return (is_null($json) || isset($json->remark)) ? 1 : 0;
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
            [out:json][timeout:180];

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
              [amenity=watering_place]
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
            [amenity=watering_place]
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

        $stats = Overpass::parse($json);

        $this->has_remarks = $this->responseHasRemarks();

        $this->parsed_at = now();
        $this->save();

        echo 'new: ' . $stats->new . "\n";
        echo 'existing: ' . $stats->existing . "\n";
    }

    public function grindUp()
    {
        if ($this->longitude_to - $this->longitude_from > 1) {
            $this->grindUpLongitudinally();
        } elseif ($this->latitude_to - $this->latitude_from > 1) {
            $this->grindUpLatitudinally();
        } else {
            $this->retry1x1();
            // throw new \Exception('Trying to grind up below 1x1 degree');
        }
    }

    public function retry1x1()
    {
        $overpassImport = new OverpassImport();
        $overpassImport->latitude_from = $this->latitude_from;
        $overpassImport->latitude_to = $this->latitude_to;
        $overpassImport->longitude_from = $this->longitude_from;
        $overpassImport->longitude_to = $this->longitude_to;
        $overpassImport->parent_id = $this->id;
        $overpassImport->overpass_batch_id = $this->overpass_batch_id;
        $overpassImport->save();

        $this->ground_up = true;
        $this->save();
    }

    public function grindUpLongitudinally()
    {
        $range = $this->longitude_to - $this->longitude_from;
        $step = $range / 10;

        for ($longitude = $this->longitude_from; $longitude < $this->longitude_to; $longitude = $longitude + $step) {
            $overpassImport = new OverpassImport();
            $overpassImport->latitude_from = -90;
            $overpassImport->latitude_to = 90;
            $overpassImport->longitude_from = $longitude;
            $overpassImport->longitude_to = $longitude + $step;
            $overpassImport->parent_id = $this->id;
            $overpassImport->overpass_batch_id = $this->overpass_batch_id;
            $overpassImport->save();
        }

        $this->ground_up = true;
        $this->save();
    }

    public function grindUpLatitudinally()
    {
        $range = $this->latitude_to - $this->latitude_from;

        if ($range == 180) {
            $step = 60;
        } elseif ($range == 60) {
            $step = 20;
        } elseif ($range == 20) {
            $step = 10;
        } elseif ($range == 10) {
            $step = 5;
        } else {
            $step = 1;
        }

        for ($latitude = $this->latitude_from; $latitude < $this->latitude_to; $latitude = $latitude + $step) {
            $overpassImport = new OverpassImport();
            $overpassImport->latitude_from = $latitude;
            $overpassImport->latitude_to = $latitude + $step;
            $overpassImport->longitude_from = $this->longitude_from;
            $overpassImport->longitude_to = $this->longitude_to;
            $overpassImport->parent_id = $this->id;
            $overpassImport->overpass_batch_id = $this->overpass_batch_id;
            $overpassImport->save();
        }

        $this->ground_up = true;
        $this->save();
    }

    public function deleteWithArtifacts()
    {
        $this->deleteArtifacts();
        $this->delete();
    }

    public function deleteArtifacts()
    {
        Storage::disk('local')->delete($this->responsePath);
    }
}
