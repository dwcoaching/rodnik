<?php

declare(strict_types=1);

namespace App\Models;

use App\Library\Overpass;
use DateTimeInterface;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\TransferException;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

final class OverpassImport extends Model
{
    use HasFactory;

    public const ENDPOINT = 'https://overpass-api.de/api/interpreter';

    /**
     * Response code we record ourselves when the request never reached the API at all.
     */
    public const TRANSPORT_ERROR = 0;

    /**
     * How often the same area may be fetched before we fall back to grinding it up.
     */
    public const MAXIMUM_ATTEMPTS = 25;

    protected $cachedArea = null;

    public function fetch()
    {
        $guzzle = $this->httpClient();

        $startedAt = now();

        $this->started_at = $startedAt;
        $this->attempts = $this->attempts + 1;

        if (false && config('app.env') !== 'production') {
            $this->fake();
        } else {
            try {
                $result = $guzzle->request('POST', self::ENDPOINT, [
                    'form_params' => [
                        'data' => $this->query, // your Overpass QL query
                    ],
                    'headers' => [
                        'User-Agent' => 'Rodnik.today/1.0 (+https://rodnik.today; kolpavko@hey.com)',
                        'Accept' => '*/*',
                    ],
                    'connect_timeout' => 15,
                    'timeout' => 210,
                    'http_errors' => false,
                ]);

                $this->response_code = $result->getStatusCode();
                $this->response_phrase = $result->getReasonPhrase();
                $this->response = $result->getBody();
            } catch (TransferException $exception) {
                // DNS, TCP and TLS failures bypass `http_errors`, so they have to be caught
                // here or a single unreachable moment kills the whole batch.
                $this->response_code = self::TRANSPORT_ERROR;
                $this->response_phrase = mb_substr($exception->getMessage(), 0, 500);
                $this->response = '';
            }
        }

        $finishedAt = now();

        $this->fetched_at = $finishedAt;
        $this->save();

        $this->recordAttempt($startedAt, $finishedAt);
    }

    /**
     * Keep a permanent record of what this request cost.
     *
     * The import's own columns are overwritten by the next retry, so without this a batch that
     * was refused on a third of its requests still looks like a clean run once every area
     * eventually succeeds. {@see \App\Library\OverpassSeed} plans the next batch from these rows.
     */
    public function recordAttempt(DateTimeInterface $startedAt, DateTimeInterface $finishedAt): void
    {
        $body = (string) $this->response;

        OverpassAttempt::create([
            'overpass_batch_id' => $this->overpass_batch_id,
            'overpass_import_id' => $this->id,
            'attempt' => $this->attempts,
            'latitude_from' => $this->latitude_from,
            'latitude_to' => $this->latitude_to,
            'longitude_from' => $this->longitude_from,
            'longitude_to' => $this->longitude_to,
            'started_at' => $startedAt,
            'duration_seconds' => max(0, $finishedAt->getTimestamp() - $startedAt->getTimestamp()),
            'response_code' => (int) $this->response_code,
            'response_bytes' => mb_strlen($body, '8bit'),
            // Counted by hand rather than by decoding: the query only ever asks for nodes and
            // ways, so these two markers are the elements, and a 15 MB payload does not have to
            // be materialised just to be measured.
            'element_count' => mb_substr_count($body, '"type":"node"') + mb_substr_count($body, '"type":"way"'),
            'congested' => $this->isCongested(),
        ]);
    }

    /**
     * Whether the response is a usable Overpass payload.
     */
    public function succeeded(): bool
    {
        return (int) $this->response_code === 200 && ! $this->responseHasRemarks();
    }

    /**
     * Whether this area should be fetched again unchanged.
     *
     * Anything that is not a success and not a complaint about the area itself is treated as
     * congestion, deliberately: splitting an area up multiplies the traffic that caused the
     * refusal in the first place, so retrying is the safe default for an unrecognised failure.
     * An area that keeps failing still ends up ground up once it runs out of attempts.
     */
    public function isCongested(): bool
    {
        return ! $this->succeeded() && ! $this->needsSmallerArea();
    }

    /**
     * Whether the API complained about this area specifically, which is the only case where
     * grinding it up into smaller areas actually helps.
     */
    public function needsSmallerArea(): bool
    {
        if ((int) $this->response_code === 400) {
            return true;
        }

        if ((int) $this->response_code !== 200) {
            return false;
        }

        $remark = mb_strtolower((string) $this->responseRemark());

        return str_contains($remark, 'timed out') || str_contains($remark, 'out of memory');
    }

    /**
     * The top level `remark` Overpass adds to an otherwise valid response when a query hit a
     * limit. Tags named `remark` on individual elements are not remarks in this sense.
     */
    public function responseRemark(): ?string
    {
        $json = json_decode((string) $this->response);

        return isset($json->remark) ? (string) $json->remark : null;
    }

    public function hasAttemptsLeft(): bool
    {
        return $this->attempts < self::MAXIMUM_ATTEMPTS;
    }

    /**
     * Put this import back in the queue over the very same area.
     */
    public function scheduleRetry(): void
    {
        $this->fetched_at = null;
        $this->has_remarks = null;
        $this->save();
    }

    public function fake()
    {
        $lottery = rand(0, 100);

        if ($lottery >= 10) {
            $this->fakeSuccess();
        } else {
            $this->fakeFailure();
        }
    }

    public function fakeSuccess()
    {
        $this->response_code = 200;
        $this->response_phrase = 'OK';
        $this->response = Storage::disk('local')->get('overpass/responses/4577.json');
    }

    public function fakeFailure()
    {
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
        return 'overpass/responses/'.$this->id.'.json';
    }

    public function getAreaAttribute()
    {
        if (! $this->cachedArea) {
            $this->cachedArea = '('
                .$this->latitude_from
                .','
                .$this->longitude_from
                .','
                .$this->latitude_to
                .','
                .$this->longitude_to
                .');';
        }

        return $this->cachedArea;
    }

    public function getQueryAttribute()
    {
        $area = $this->area;

        $query = "
            [out:json][timeout:180];
            (
              nw[natural=spring]{$area}
              nw[man_made=spring_box]{$area}
              nw[man_made=water_well]{$area}
              nw[man_made=water_tap]{$area}
              nw[amenity=drinking_water]{$area}
              nw[amenity=fountain]{$area}
              nw[amenity=watering_place]{$area}
              nw[man_made=drinking_fountain]{$area}
              nw[amenity=water_point]{$area}
              nw[waterway=water_point]{$area}
              nw[water_point=yes]{$area}
              nw[drinking_water]{$area}
              nw[\"drinking_water:seasonal\"]{$area}
              nw[\"drinking_water:legal\"]{$area}
              nw[natural=hot_spring]{$area}
              nw[natural=geyser]{$area}
            );
            out meta center;
        ";

        return $query;
    }

    public function parse()
    {
        $json = json_decode($this->response);

        $stats = Overpass::parse($json, $this->overpass_batch_id);

        unset($json);

        $this->has_remarks = $this->responseHasRemarks();

        $this->parsed_at = now();
        $this->save();

        echo 'new: '.$stats->new."\n";
        echo 'existing: '.$stats->existing."\n";

        unset($stats);
    }

    public function overpassBatch()
    {
        return $this->belongsTo(OverpassBatch::class);
    }

    public function grindUp()
    {
        if ($this->longitude_to - $this->longitude_from > 1) {
            $this->grindUpLongitudinally();
        } elseif ($this->latitude_to - $this->latitude_from > 1) {
            $this->grindUpLatitudinally();
        } else {
            $this->giveUp();
        }
    }

    /**
     * A 1x1 degree area cannot be split any further, so stop working on it.
     */
    public function giveUp(): void
    {
        echo "Giving up on import id = {$this->id} after {$this->attempts} attempts\n";

        $this->ground_up = true;
        $this->save();
    }

    /**
     * Break a merged block back into the single degree columns it was built from.
     *
     * The step has to stay a whole degree: coverage is tracked against a 1x1 degree grid of
     * {@see OverpassCheck}, and a check only counts as covered by an import that fully contains
     * it, so a fractional column would cover nothing and leave the batch permanently short of
     * 100%. Reverting the merge is also the right answer on its own terms — these columns were
     * put together on the bet that they were jointly cheap, and that bet just lost.
     */
    public function grindUpLongitudinally()
    {
        $step = 1;

        for ($longitude = $this->longitude_from; $longitude < $this->longitude_to; $longitude = $longitude + $step) {
            $overpassImport = new self();
            $overpassImport->latitude_from = $this->latitude_from;
            $overpassImport->latitude_to = $this->latitude_to;
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
        // Latitudes come out of the database as decimal strings, so compare whole degrees.
        $range = (int) round((float) $this->latitude_to - (float) $this->latitude_from);

        $step = match ($range) {
            180 => 60,
            60 => 20,
            20 => 10,
            10 => 5,
            default => 1,
        };

        for ($latitude = $this->latitude_from; $latitude < $this->latitude_to; $latitude = $latitude + $step) {
            $overpassImport = new self();
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

    /**
     * Resolved through the container so tests can bind a client that answers without reaching
     * the network.
     */
    private function httpClient(): Client
    {
        return app(Client::class);
    }
}
