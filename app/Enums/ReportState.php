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
        return (string) __('ui.report.conditions.'.$this->value);
    }

    public function formLabel(): string
    {
        return match ($this) {
            self::Running => (string) __('ui.report.form_conditions.running'),
            self::Dry => (string) __('ui.report.form_conditions.dry'),
            default => $this->getLabel(),
        };
    }

    public function gpxLabel(): string
    {
        return match ($this) {
            self::NotFound => (string) __('ui.home.map_legend.not_found'),
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
