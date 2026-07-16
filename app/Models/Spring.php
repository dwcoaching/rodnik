<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ReportQuality;
use App\Enums\ReportState;
use App\Library\RedirectChain;
use App\Library\StatisticsService;
use DB;
use Exception;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class Spring extends Model
{
    use HasFactory;

    public const TYPES = [
        'Spring',
        'Water well',
        'Water tap',
        'Drinking water source',
        'Fountain',
        'Water source',
        // 'Not a water source',
    ];

    // Keep in sync with resources/js/utils/classifyScore.js.
    public const WATER_SCORE_THRESHOLD = 0.4;

    public const MERGE_RADIUS_METERS = 500;

    // Coarse query prefilter for nearby merge candidates. Exact merge distance
    // checks use the HaversineDistance library.
    public const MERGE_RADIUS_DEGREES = self::MERGE_RADIUS_METERS / 111320;

    public function reports()
    {
        return $this->hasMany(Report::class);
    }

    public function visibleReports(): HasMany
    {
        return $this->hasMany(Report::class)->visible();
    }

    public function redirectedTo()
    {
        return $this->belongsTo(self::class, 'redirect_to_spring_id');
    }

    public function redirectedFrom()
    {
        return $this->hasMany(self::class, 'redirect_to_spring_id');
    }

    public function springRevisions()
    {
        return $this->hasMany(SpringRevision::class);
    }

    public function osm_tags()
    {
        return $this->hasMany(OSMTag::class);
    }

    public function photos()
    {
        return $this->hasManyThrough(Photo::class, Report::class);
    }

    public function getCoordinatesAttribute()
    {
        if ($this->latitude && $this->longitude) {
            return $this->latitude.', '.$this->longitude;
        }

        return '';
    }

    public function parseOSMIntermittent()
    {
        $intermittentOrSeasonalTags = $this->osm_tags->filter(function ($item) {
            return $item->key === 'seasonal' || $item->key === 'intermittent';
        });

        if (! $intermittentOrSeasonalTags->count()) {
            return null;
        }

        $permanentTagsCount = $intermittentOrSeasonalTags->reduce(function ($carry, $item) {
            return $carry + (int) ($item->value === 'no' ? 1 : 0);
        });

        if ($permanentTagsCount) {
            return 'no';
        }

        return 'yes';
    }

    public function parseOSMName()
    {
        $name = $this->osm_tags->first(function ($item) {
            return $item->key === 'name';
        });

        if ($name) {
            return $name->value;
        }

        return null;
    }

    public function parseOSMType()
    {
        if (count(
            $this->osm_tags->filter(function ($item) {
                return $item->key === 'natural' &&
                    ($item->value === 'spring' || $item->value === 'spring_box');
            }))) {
            return 'Spring';
        }

        if (count(
            $this->osm_tags->filter(function ($item) {
                return $item->key === 'man_made' && $item->value === 'water_well';
            }))) {
            return 'Water well';
        }

        if (count(
            $this->osm_tags->filter(function ($item) {
                return $item->key === 'man_made' && $item->value === 'water_tap';
            }))) {
            return 'Water tap';
        }

        if (count(
            $this->osm_tags->filter(function ($item) {
                return
                    ($item->key === 'amenity' && $item->value === 'drinking_water')
                    || ($item->key === 'drinking_water' && $item->value === 'yes')
                    || ($item->key === 'man_made' && $item->value === 'drinking_fountain');
            }))) {
            return 'Drinking water source';
        }

        if (count(
            $this->osm_tags->filter(function ($item) {
                return $item->key === 'amenity' && $item->value === 'fountain';
            }))) {
            return 'Fountain';
        }

        return 'Water source';
    }

    public function invalidateTiles()
    {
        SpringTile::invalidate($this->longitude, $this->latitude);
        WateredSpringTile::invalidate($this->longitude, $this->latitude);
    }

    public function updateFromOSM($key, $newValue, SpringRevision $revision)
    {
        if (! $this->osmValuesAreEquivalent($key, $this->{'osm_'.$key}, $newValue)) {
            if ($this->osmValuesAreEquivalent($key, $this->{$key}, $this->{'osm_'.$key})) {
                $revision->{'old_'.$key} = $this->{'osm_'.$key};
                $revision->{'new_'.$key} = $newValue;

                $this->{$key} = $newValue;
            }

            $revision->{'old_osm_'.$key} = $this->{'osm_'.$key};
            $revision->{'new_osm_'.$key} = $newValue;
            $this->{'osm_'.$key} = $newValue;
        }

        return $revision;
    }

    public function waterConfirmed()
    {
        $presenceOfGoodWaterCount = 0;
        $absenceOfGoodWaterCount = 0;

        foreach ($this->visibleReports as $report) {
            if ($report->quality === ReportQuality::Good) {
                $presenceOfGoodWaterCount++;
            }

            if (
                ! is_null($report->quality)
                && $report->quality !== ReportQuality::Good
            ) {
                $absenceOfGoodWaterCount++;
            }
        }

        if ($presenceOfGoodWaterCount > $absenceOfGoodWaterCount) {
            return true;
        }

        return false;
    }

    public function annihilate()
    {
        if (! $this->canBeAnnihilated()) {
            throw new Exception('Spring can not be annihilated');
        }

        $latitude = $this->latitude;
        $longitude = $this->longitude;

        $this->delete();

        SpringTile::invalidate($this->longitude, $this->latitude);
        WateredSpringTile::invalidate($this->longitude, $this->latitude);
        StatisticsService::invalidateSpringsCount();
    }

    public function hide()
    {
        $this->hidden_at = now();
        $this->save();

        SpringTile::invalidate($this->longitude, $this->latitude);
        WateredSpringTile::invalidate($this->longitude, $this->latitude);
        StatisticsService::invalidateSpringsCount();
    }

    public function unhide()
    {
        $this->hidden_at = null;
        $this->save();

        SpringTile::invalidate($this->longitude, $this->latitude);
        WateredSpringTile::invalidate($this->longitude, $this->latitude);
        StatisticsService::invalidateSpringsCount();
    }

    public function canBeAnnihilated()
    {
        if ($this->reports()->visible()->count() > 0) {
            return false;
        }

        if ($this->osm_node_id || $this->osm_way_id) {
            return false;
        }

        return true;
    }

    public function isOsmTracked()
    {
        return $this->osm_node_id || $this->osm_way_id;
    }

    // Source spring (the duplicate being merged away). Must not be tracked by
    // OSM — OSM-tracked springs would re-appear on the next import and need
    // to be deleted from OSM first.
    public function canBeRedirectedFrom()
    {
        return ! $this->isOsmTracked();
    }

    public function canBeRedirectedTo(self $source)
    {
        if ($this->id === $source->id) {
            return false;
        }

        if ($this->hidden_at) {
            return false;
        }

        if ($this->redirect_to_spring_id) {
            return false;
        }

        return ! $source->redirect_to_spring_id;
    }

    public function finallyRedirectedTo(): ?self
    {
        return $this->redirectChain()->finalTarget();
    }

    public function visibleMergeTargetForReports(): ?self
    {
        $target = $this->finallyRedirectedTo();

        return $target && ! $target->hidden_at ? $target : null;
    }

    public function redirectChain(): RedirectChain
    {
        return RedirectChain::fromSpring($this);
    }

    public function scopeNotRedirected($query)
    {
        return $query->whereNull($this->qualifyColumn('redirect_to_spring_id'));
    }

    public function scopeWithinMergeRadiusOf($query, self $other)
    {
        if ($other->latitude === null || $other->longitude === null) {
            return $query->whereRaw('1 = 0');
        }

        $latitude = (float) $other->latitude;
        $longitude = (float) $other->longitude;
        $latitudeDelta = self::MERGE_RADIUS_DEGREES;
        $cosLatitude = abs(cos(deg2rad($latitude)));
        $longitudeDelta = $cosLatitude > 0.000001
            ? min(180, $latitudeDelta / $cosLatitude)
            : 180;

        $query->whereBetween('latitude', [
            max(-90, $latitude - $latitudeDelta),
            min(90, $latitude + $latitudeDelta),
        ]);

        if ($longitudeDelta >= 180) {
            return $query;
        }

        $minLongitude = $longitude - $longitudeDelta;
        $maxLongitude = $longitude + $longitudeDelta;

        return $query->where(function ($query) use ($minLongitude, $maxLongitude) {
            if ($minLongitude < -180) {
                return $query
                    ->whereBetween('longitude', [$minLongitude + 360, 180])
                    ->orWhereBetween('longitude', [-180, $maxLongitude]);
            }

            if ($maxLongitude > 180) {
                return $query
                    ->whereBetween('longitude', [$minLongitude, 180])
                    ->orWhereBetween('longitude', [-180, $maxLongitude - 360]);
            }

            return $query->whereBetween('longitude', [$minLongitude, $maxLongitude]);
        });
    }

    public function scopeMissingFromOsm($query)
    {
        $latest = OverpassBatch::where('parse_status', 'parsed')
            ->where('coverage', 100)
            ->max('id');

        if ($latest === null) {
            return $query->whereRaw('1 = 0');
        }

        return $query
            ->whereNull('hidden_at')
            ->where(function ($q) {
                $q->whereNotNull('osm_node_id')->orWhereNotNull('osm_way_id');
            })
            ->where(function ($q) use ($latest) {
                $q->whereNull('last_seen_overpass_batch_id')
                    ->orWhere('last_seen_overpass_batch_id', '<', $latest);
            });
    }

    public function canBePrunedAsMissing()
    {
        if ($this->reports()->count() > 0) {
            return false;
        }

        if ($this->springRevisions()->where('revision_type', '!=', 'from_osm')->count() > 0) {
            return false;
        }

        return true;
    }

    public function pruneAsMissing()
    {
        if (! $this->canBePrunedAsMissing()) {
            throw new Exception('Spring can not be pruned as missing');
        }

        $longitude = $this->longitude;
        $latitude = $this->latitude;
        $id = $this->id;

        DB::transaction(function () use ($id) {
            OSMTag::where('spring_id', $id)->delete();
            SpringRevision::where('spring_id', $id)->delete();
            Spring::where('id', $id)->delete();
        });

        SpringTile::invalidate($longitude, $latitude);
        WateredSpringTile::invalidate($longitude, $latitude);
        StatisticsService::invalidateSpringsCount();
    }

    public function visible()
    {
        return ! $this->hidden_at;
    }

    /**
     * Eager-load visible reports with just the columns needed for scoring
     * (getWaterScore()/isNotFound()).
     */
    public function scopeWithVisibleReportConditions(Builder $query): void
    {
        $query->with([
            'visibleReports' => function (HasMany $reports): void {
                $reports->select(['id', 'spring_id', ...Report::CONDITION_COLUMNS]);
            },
        ]);
    }

    public function getWaterScore(): ?float
    {
        $scores = $this->visibleReports
            ->map(fn (Report $report): ?int => $report->getWaterScore())
            ->filter(fn (?int $score): bool => $score !== null);

        if ($scores->isEmpty()) {
            return null;
        }

        return (float) $scores->average();
    }

    public function isNotFound(): bool
    {
        if (! $this->visibleReports->contains('state', ReportState::NotFound)) {
            return false;
        }

        return ! $this->visibleReports->contains(
            fn (Report $report): bool => $report->confirmsSpringFound(),
        );
    }

    public function getRodnikType()
    {
        if ($this->type !== $this->osm_type) {
            return $this->type;
        } // this condition is here for legacy reasons when we didn't have spring_revisions table

        return $this->springRevisions->whereNotNull('new_type')->sortByDesc('updated_at')->first()?->new_type ?? null;
    }

    public function getRodnikName()
    {
        if ($this->name !== $this->osm_name) {
            return $this->name;
        } // this condition is here for legacy reasons when we didn't have spring_revisions table

        return $this->springRevisions->whereNotNull('new_name')->sortByDesc('updated_at')->first()?->new_name ?? null;
    }

    public function getRodnikLatitude()
    {
        if ($this->latitude !== $this->osm_latitude) {
            return $this->latitude;
        } // this condition is here for legacy reasons when we didn't have spring_revisions table

        return $this->springRevisions->whereNotNull('new_latitude')->sortByDesc('updated_at')->first()?->new_latitude ?? null;
    }

    public function getRodnikLongitude()
    {
        if ($this->longitude !== $this->osm_longitude) {
            return $this->longitude;
        } // this condition is here for legacy reasons when we didn't have spring_revisions table

        return $this->springRevisions->whereNotNull('new_longitude')->sortByDesc('updated_at')->first()?->new_longitude ?? null;
    }

    private function osmValuesAreEquivalent(string $key, mixed $currentValue, mixed $newValue): bool
    {
        if (! in_array($key, ['latitude', 'longitude'], true)) {
            return $currentValue === $newValue;
        }

        if ($currentValue === null || $newValue === null) {
            return $currentValue === $newValue;
        }

        if (! is_numeric($currentValue) || ! is_numeric($newValue)) {
            return $currentValue === $newValue;
        }

        return number_format((float) $currentValue, 6, '.', '')
            === number_format((float) $newValue, 6, '.', '');
    }
}
