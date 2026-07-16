<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum ReportState: string implements HasColor, HasLabel
{
    case Running = 'running';
    case Dripping = 'dripping';
    case Dry = 'dry';
    case NotFound = 'notfound';

    public function getLabel(): string
    {
        return match ($this) {
            self::Running => 'Has water',
            self::Dripping => 'Very little water',
            self::Dry => 'Dry',
            self::NotFound => 'Water source not found',
        };
    }

    public function formLabel(): string
    {
        return match ($this) {
            self::Running => 'Has water',
            self::Dry => 'No water',
            default => $this->getLabel(),
        };
    }

    public function gpxLabel(): string
    {
        return match ($this) {
            self::NotFound => 'Not Found',
            default => $this->getLabel(),
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Running => 'success',
            self::Dripping => 'warning',
            self::Dry, self::NotFound => 'danger',
        };
    }
}
