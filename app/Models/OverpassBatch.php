<?php

namespace App\Models;

use App\Models\OverpassCheck;
use App\Models\OverpassImport;
use App\Jobs\CleanupOSMSprings;
use App\Jobs\ParseOverpassBatchImports;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class OverpassBatch extends Model
{
    use HasFactory;

    public function overpassImports(): HasMany
    {
        return $this->hasMany(OverpassImport::class);
    }

    public function overpassChecks(): HasMany
    {
        return $this->hasMany(OverpassCheck::class);
    }

    public function createImports()
    {
        $step = 1;

        for ($longitude = -180; $longitude <= 180 - $step; $longitude = $longitude + $step) {
            $overpassImport = new OverpassImport();
            $overpassImport->latitude_from = -90;
            $overpassImport->latitude_to = 90;
            $overpassImport->longitude_from = $longitude;
            $overpassImport->longitude_to = $longitude + $step;
            $overpassImport->overpass_batch_id = $this->id;
            $overpassImport->save();
        }

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
        $overpassChecksUnknown = $this->overpassChecks()->whereNull('covered_by')->get();

        foreach ($overpassChecksUnknown as $check) {
            $overpassImport = $this->overpassImports()->where('has_remarks', 0)
                ->where('latitude_from', '<=', $check->latitude_from)
                ->where('latitude_to', '>=', $check->latitude_to)
                ->where('longitude_from', '<=', $check->longitude_from)
                ->where('longitude_to', '>=', $check->longitude_to)
                ->first();

            if ($overpassImport) {
                $check->covered_by = $overpassImport->id;
                $check->save();
            }
        }

        $covered = $this->overpassChecks()->whereNotNull('covered_by')->count();
        $coverage = floor(round($covered / 64800, 5) * 100000) / 100000;
        $this->coverage = $coverage * 100;

        if ($coverage === 1.0) {
            $this->fetch_status = "fetched";

            ParseOverpassBatchImports::dispatch($this);
        } else {
            $this->fetch_status = "fetching";
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
            $this->parse_status = "parsed";
            CleanupOSMSprings::dispatch($this);
        } else {
            $this->parse_status = "parsing";
        }

        $this->save();
    }

    public function fetchImports()
    {
        $this->fetch_status = 'fetching';
        $this->save();

        $dueImports = $this->overpassImports()->whereNull('fetched_at')->get();

        foreach ($dueImports as $dueImport) {
            echo "Fetching id = {$dueImport->id} ({$dueImport->latitude_from}, {$dueImport->longitude_from}) to ({$dueImport->latitude_to}, {$dueImport->longitude_to}) \n";
            $dueImport->fetch();
            $this->checkImports();
        }

        $this->updateCoverage();
        $this->grindUpFailedImports();
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
            $import->grindUp();
        }

        if ($dueImports->count()) {
            $this->fetchImports();
        }
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

            $this->updateParsedPercentage();
        }

        $this->updateParsedPercentage();
    }
}
