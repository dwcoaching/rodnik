<?php

declare(strict_types=1);

namespace App\Models;

use App\Jobs\CleanupOSMSprings;
use App\Jobs\ParseOverpassBatchImports;
use App\Jobs\PruneMissingOSMSprings;
use App\Jobs\RemoveOlderOverpassArtifacts;
use App\Library\OverpassGate;
use App\Library\OverpassSeed;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

final class OverpassBatch extends Model
{
    use HasFactory;

    /**
     * Backstop for {@see self::fetchImports()} so a permanently unhappy API cannot spin forever.
     */
    public const MAXIMUM_FETCH_PASSES = 200;

    /**
     * @return HasMany<OverpassImport, $this>
     */
    public function overpassImports(): HasMany
    {
        return $this->hasMany(OverpassImport::class);
    }

    /**
     * @return HasMany<OverpassCheck, $this>
     */
    public function overpassChecks(): HasMany
    {
        return $this->hasMany(OverpassCheck::class);
    }

    public function createImports()
    {
        $segments = OverpassSeed::segments();

        foreach ($segments as $segment) {
            $overpassImport = new OverpassImport();
            $overpassImport->latitude_from = $segment['latitude_from'];
            $overpassImport->latitude_to = $segment['latitude_to'];
            $overpassImport->longitude_from = $segment['longitude_from'];
            $overpassImport->longitude_to = $segment['longitude_to'];
            $overpassImport->overpass_batch_id = $this->id;
            $overpassImport->save();
        }

        echo 'Seeded '.count($segments)." areas\n";

        $this->imports_status = 'created';
        $this->save();
    }

    public function createChecks()
    {
        $this->checks_status = 'creating';
        $this->save();

        for ($latitude = -90; $latitude <= 89; $latitude = $latitude + 1) {
            for ($longitude = -180; $longitude <= 179; $longitude = $longitude + 1) {
                $overpassCheck = new OverpassCheck();
                $overpassCheck->latitude_from = $latitude;
                $overpassCheck->latitude_to = $latitude + 1;
                $overpassCheck->longitude_from = $longitude;
                $overpassCheck->longitude_to = $longitude + 1;
                $overpassCheck->overpass_batch_id = $this->id;
                $overpassCheck->save();

                echo "{$latitude}, {$longitude} check has been created\n";
            }
        }

        $this->checks_status = 'created';
        $this->save();
    }

    public function updateCoverage()
    {
        DB::statement('
            UPDATE overpass_checks c
            JOIN overpass_imports i
              ON i.overpass_batch_id = c.overpass_batch_id
             AND i.has_remarks = 0
             AND i.latitude_from  <= c.latitude_from
             AND i.latitude_to    >= c.latitude_to
             AND i.longitude_from <= c.longitude_from
             AND i.longitude_to   >= c.longitude_to
            SET c.covered_by = i.id
            WHERE c.overpass_batch_id = ?
              AND c.covered_by IS NULL
        ', [$this->id]);

        $covered = $this->overpassChecks()->whereNotNull('covered_by')->count();
        $coverage = floor(round($covered / 64800, 5) * 100000) / 100000;
        $this->coverage = $coverage * 100;

        if ($coverage === 1.0) {
            $this->fetch_status = 'fetched';

            ParseOverpassBatchImports::dispatch($this);
        } else {
            $this->fetch_status = 'fetching';
        }

        $this->save();
    }

    public function updateParsedPercentage()
    {
        $groups = $this->overpassImports()
            ->whereNotNull('fetched_at')
            ->where('ground_up', false)
            ->get()
            ->mapToGroups(function ($item) {
                return [! is_null($item->parsed_at) => $item];
            }
            );

        $parsed = $groups->has(1) ? $groups[1]->count() : 0;
        $unparsed = $groups->has(0) ? $groups[0]->count() : 0;

        $percentage = floor(round($parsed / ($parsed + $unparsed), 5) * 100000) / 100000;
        $this->parsed_percentage = $percentage * 100;

        if ($percentage === 1.0) {
            $this->parse_status = 'parsed';
            CleanupOSMSprings::dispatch($this);
            PruneMissingOSMSprings::dispatch($this);
            RemoveOlderOverpassArtifacts::dispatch($this);
        } else {
            $this->parse_status = 'parsing';
        }

        $this->save();
    }

    /**
     * Fetch every outstanding import, then keep re-queueing whatever failed until nothing is
     * left to do. Written as a loop rather than as mutual recursion with
     * {@see self::grindUpFailedImports()}, which used to grow the stack once per pass.
     */
    public function fetchImports()
    {
        $this->fetch_status = 'fetching';
        $this->save();

        $gate = new OverpassGate;
        $consecutiveFailures = 0;
        $pass = 0;

        do {
            $dueImports = $this->overpassImports()->whereNull('fetched_at')->get();

            foreach ($dueImports as $dueImport) {
                echo "Fetching id = {$dueImport->id} ({$dueImport->latitude_from}, {$dueImport->longitude_from}) to ({$dueImport->latitude_to}, {$dueImport->longitude_to}) \n";

                $gate->awaitSlot();
                $dueImport->fetch();

                if ($dueImport->isCongested()) {
                    $consecutiveFailures = $consecutiveFailures + 1;
                    $gate->backOff($consecutiveFailures);
                } else {
                    $consecutiveFailures = 0;
                }

                $this->checkImports();
            }

            $this->updateCoverage();

            $pass = $pass + 1;
        } while ($this->grindUpFailedImports() && $pass < self::MAXIMUM_FETCH_PASSES);
    }

    public function checkImports()
    {
        $dueImports = $this->overpassImports()
            ->whereNotNull('fetched_at')
            ->whereNull('has_remarks')
            ->get();

        foreach ($dueImports as $import) {
            if ($import->responseHasRemarks()) {
                $import->has_remarks = true;
                $import->save();
                echo "Import id = {$import->id} has remarks\n";
            } else {
                $import->has_remarks = false;
                $import->save();
                echo "Import id = {$import->id} is perfect\n";
            }
        }
    }

    /**
     * Re-queue every failed import. An area the API simply refused to serve is retried
     * unchanged; only an area the API complained about is ground up into smaller ones.
     *
     * @return bool Whether anything was queued for another pass.
     */
    public function grindUpFailedImports()
    {
        $dueImports = $this->overpassImports()->whereNotNull('fetched_at')
            ->where('ground_up', false)
            ->where(function ($query) {
                return $query->where('response_code', '<>', 200)
                    ->orWhere('has_remarks', true);
            })
            ->get();

        foreach ($dueImports as $import) {
            if ($import->isCongested() && $import->hasAttemptsLeft()) {
                echo "Retrying import id = {$import->id} unchanged (attempt {$import->attempts})\n";
                $import->scheduleRetry();
            } else {
                $import->grindUp();
            }
        }

        return $dueImports->count() > 0;
    }

    public function parseImports()
    {
        $this->parse_status = 'parsing';

        $dueImports = $this->overpassImports()
            ->whereNotNull('fetched_at')
            ->whereNull('parsed_at')
            ->where('ground_up', false)
            ->get();

        foreach ($dueImports as $dueImport) {
            echo "Parsing import id = {$dueImport->id}\n";
            $dueImport->parse();

            unset($dueImport);

            $this->updateParsedPercentage();
        }

        unset($dueImports);

        $this->updateParsedPercentage();
    }

    public function deleteWithArtifacts()
    {
        $this->deleteArtifacts();
        $this->delete();
    }

    public function deleteArtifacts()
    {
        $this->deleteOverpassChecksWithArtifacts();
        $this->deleteOverpassImportsWithArticats();
    }

    public function deleteOverpassChecksWithArtifacts()
    {
        OverpassCheck::where('overpass_batch_id', $this->id)->delete();
    }

    public function deleteOverpassImportsWithArticats()
    {
        foreach ($this->overpassImports as $overpassImport) {
            $overpassImport->deleteWithArtifacts();
        }
    }
}
