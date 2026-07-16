<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum ReportAccess: string implements HasColor, HasLabel
{
    case Limited = 'limited';
    case No = 'no';

    public function getLabel(): string
    {
        return match ($this) {
            self::Limited => 'Limited access',
            self::No => 'No access',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Limited => 'warning',
            self::No => 'danger',
        };
    }
}
