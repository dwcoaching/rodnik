<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ReportAccess;
use App\Enums\ReportQuality;
use App\Enums\ReportState;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

final class Report extends Model
{
    use HasFactory;

    /** @var list<string> Columns required to evaluate getWaterScore()/hasConditionSignals(). */
    public const CONDITION_COLUMNS = ['state', 'quality', 'access', 'littered', 'ruined'];

    protected $casts = [
        'visited_at' => 'date',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'state' => ReportState::class,
        'quality' => ReportQuality::class,
        'access' => ReportAccess::class,
        'littered' => 'boolean',
        'ruined' => 'boolean',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function photos()
    {
        return $this->hasMany(Photo::class)->orderBy('order', 'asc');
    }

    public function spring()
    {
        return $this->belongsTo(Spring::class);
    }

    public function getShortCommentAttribute()
    {
        if ($this->comment === null) {
            return null;
        }

        return mb_strlen($this->comment) > 150
            ? mb_trim(mb_substr($this->comment, 0, 150)).'...'
            : $this->comment;
    }

    public function getWaterScore(): ?int
    {
        if ($this->state === ReportState::NotFound) {
            return null;
        }

        if (
            $this->quality === ReportQuality::Bad
            || $this->state === ReportState::Dry
            || $this->access === ReportAccess::No
        ) {
            return -1;
        }

        if ($this->quality === ReportQuality::Good) {
            return $this->access === ReportAccess::Limited ? 0 : 1;
        }

        return $this->hasConditionSignals() ? 0 : null;
    }

    public function confirmsSpringFound(): bool
    {
        return $this->state !== ReportState::NotFound
            && $this->hasConditionSignals();
    }

    public function hasConditionSignals(): bool
    {
        return $this->state !== null
            || $this->quality !== null
            || $this->access !== null
            || $this->littered
            || $this->ruined;
    }

    public function scopeVisible(Builder $query): void
    {
        $query->whereNull('reports.from_osm')
            ->whereNull('reports.hidden_at');
    }
}
