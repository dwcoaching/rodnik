<?php

namespace App\Library\Export;

use App\Models\User;
use App\Models\Photo;
use App\Models\Report;
use App\Models\Spring;
use App\Models\SpringRevision;
use App\Library\Export\Transformer;
use Illuminate\Database\Eloquent\Collection;

class JsonTransformer extends Transformer
{
    public function transform(): array
    {
        $collection = $this->springs->map(function (Spring $spring): array {
            return [
                'id' => $spring->id,
                'latitude' => $spring->latitude,
                'longitude' => $spring->longitude,
                ...($this->user ? [] : [
                    'type' => $spring->getRodnikType(),
                    'name' => $spring->getRodnikName(),
                ]),
                'reports' => $spring->reports->filter(function (Report $report) {
                    return $report->user_id === $this->user?->id || !$this->user;
                })
                ->map(function (Report $report) {
                    return [
                        'id' => $report->id,
                        'user' => $report->user?->name ?? 'Anonymous',
                        'user_id' => $report->user_id ?? null,
                        'created_at' => $report->created_at?->format('Y-m-d H:i:s') ?? '',
                        'visited_at' => (string) ($report->visited_at ?? ''), 
                        'state' => $report->state ?? '',
                        'quality' => $report->quality ?? '',
                        'comment' => $report->comment ?? '',
                        'photos' => $report->photos->map(function (Photo $photo) {
                            return $photo->url ?? '';
                        }),
                    ];
                }),
                'edits' => $spring->springRevisions->filter(function (SpringRevision $revision) {
                    return $revision->user_id === $this->user?->id || !$this->user;
                })
                ->map(function (SpringRevision $revision) {
                    return [
                        'id' => $revision->id,
                        'user' => $revision->user?->name ?? 'Anonymous',
                        'user_id' => $revision->user_id ?? null,
                        'latitude' => $revision->new_latitude,
                        'longitude' => $revision->new_longitude,
                        'type' => $revision->new_type,
                        'name' => $revision->new_name,
                        'created_at' => $revision->created_at?->format('Y-m-d H:i:s') ?? '',
                    ];
                }),
            ];
        });

        return $collection->toArray();
    }
}