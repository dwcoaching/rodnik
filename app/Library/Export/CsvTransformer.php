<?php

namespace App\Library\Export;

use App\Models\User;
use App\Models\Photo;
use App\Models\Report;
use App\Models\Spring;
use App\Models\SpringRevision;
use Illuminate\Database\Eloquent\Collection;

class CsvTransformer extends Transformer
{
    public function transformSprings(): array
    {
        return $this->springs->map(function (Spring $spring) {
            return [
                'id' => $spring->id,
                'latitude' => $spring->getRodnikLatitude(),
                'longitude' => $spring->getRodnikLongitude(),
                'type' => $spring->getRodnikType(),
                'name' => $spring->getRodnikName(),
                'osm_latitude' => $spring->osm_latitude,
                'osm_longitude' => $spring->osm_longitude,
                'osm_type' => $spring->osm_type,
                'osm_name' => $spring->osm_name,
            ];
        })->toArray();
    }
    public function transformReports(): array
    {
        $reports = [];
        
        foreach ($this->springs as $spring) {
            foreach ($spring->reports as $report) {
                if ($report->user_id === $this->user?->id || !$this->user) {
                    $reports[] = [
                        'id' => $report->id,
                        'spring_id' => $report->spring_id,
                        'user' => $report->user?->name ?? 'Anonymous',
                        'user_id' => $report->user_id ?? null,
                        'created_at' => $report->created_at?->format('Y-m-d H:i:s') ?? '',
                        'visited_at' => (string) ($report->visited_at ?? ''), 
                        'state' => $report->state ?? '',
                        'quality' => $report->quality ?? '',
                        'comment' => $report->comment ?? '',
                    ];
                }
            }
        }
        
        return $reports;
    }

    public function transformEdits(): array
    {
        $revisions = [];
        
        foreach ($this->springs as $spring) {
            foreach ($spring->springRevisions as $revision) {
                if ($revision->user_id === $this->user?->id || !$this->user) {
                    $revisions[] = [
                        'id' => $revision->id,
                        'spring_id' => $revision->spring_id,
                        'user' => $revision->user?->name ?? 'Anonymous',
                        'user_id' => $revision->user_id ?? null,
                        'latitude' => $revision->new_latitude,
                        'longitude' => $revision->new_longitude,
                        'type' => $revision->new_type,
                        'name' => $revision->new_name,
                        'created_at' => $revision->created_at?->format('Y-m-d H:i:s') ?? '',
                    ];
                }
            }
        }
        
        return $revisions;
    }

    public function transformPhotos(): array
    {
        $photos = [];
        
        foreach ($this->springs as $spring) {
            foreach ($spring->reports as $report) {
                foreach ($report->photos as $photo) {
                    $photos[] = [
                        'id' => $photo->id,
                        'report_id' => $report->id,
                        'spring_id' => $spring->id,
                        'url' => $photo->url,
                    ];
                }
            }
        }
        
        return $photos;
    }

    public function getHeadersForSprings(): array
    {
        return ['id', 'latitude', 'longitude', 'type', 'name', 'osm_latitude', 'osm_longitude', 'osm_type', 'osm_name'];
    }

    public function getHeadersForReports(): array
    {
        return ['id', 'spring_id', 'user', 'user_id', 'created_at', 'visited_at', 'state', 'quality', 'comment'];
    }


    public function getHeadersForEdits(): array
    {
        return ['id', 'spring_id', 'user', 'user_id', 'latitude', 'longitude', 'type', 'name', 'created_at'];
    }

    public function getHeadersForPhotos(): array
    {
        return ['id', 'report_id', 'spring_id', 'url'];
    }
}