<?php

namespace App\Models;

use App\Models\OverpassCheck;
use App\Models\OverpassImport;
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
        return $this->hasMany(OverpassCheks::class);
    }

    public function createImports()
    {
        for ($longitude = -180; $longitude <= 170; $longitude = $longitude + 10) {
            $overpassImport = new OverpassImport();
            $overpassImport->latitude_from = -90;
            $overpassImport->latitude_to = 90;
            $overpassImport->longitude_from = $longitude;
            $overpassImport->longitude_to = $longitude + 10;
            $overpassImport->overpass_batch_id = $this->id;
            $overpassImport->save();
        }
    }

    public function createChecks()
    {
        for ($latitude = -90; $latitude <= 89; $latitude = $latitude + 1) {
            for ($longitude = -180; $longitude <= 179; $longitude = $longitude + 1) {
                $overpassCheck = new OverpassCheck();
                $overpassCheck->latitude_from = $latitude;
                $overpassCheck->latitude_to = $latitude + 1;
                $overpassCheck->longitude_from = $longitude;
                $overpassCheck->longitude_to = $longitude + 1;
                $overpassCheck->overpass_batch_id = $this->id;
                $overpassCheck->save();
            }
        }
    }

    public function updateCoverage()
    {
        $overpassChecksUnknown = $this->overpassChecks()->whereNull('covered_by')->get();

        foreach ($overpassChecksUnknown as $check) {
            $overpassImport = OverpassImport::where('has_remarks', 0)
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
    }

    public function fetchImports()
    {
        $dueImports = $this->overpassImports()->whereNull('fetched_at')->get();

        foreach ($dueImports as $dueImport) {
            // echo "Fetching id = {$dueImport->id} ({$dueImport->latitude_from}, {$dueImport->longitude_from}) to ({$dueImport->latitude_to}, {$dueImport->longitude_to}) \n";
            $dueImport->fetch();
        }

        $this->checkImports();
        $this->grindUpFailedImports();
    }

    public function checkImports()
    {
        $dueImports = $this->overpassImports()->whereNotNull('fetched_at')
            ->get();

        foreach ($dueImports as $import) {
            if ($import->responseHasRemarks()) {
                $import->has_remarks = true;
                $import->save();
                //echo "Import id = {$import->id} has remarks\n";
            } else {
                $import->has_remarks = false;
                $import->save();
                //echo "Import id = {$import->id} IS FUCKING PERFECT\n";
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
        $dueImports = $this->overpassImports()
            ->whereNotNull('fetched_at')
            ->whereNull('parsed_at')
            ->where('ground_up', false)
            ->get();

        foreach ($dueImports as $dueImport) {
            // echo "Parsing import id = {$dueImport->id}\n";
            $dueImport->parse();
        }
    }
}
