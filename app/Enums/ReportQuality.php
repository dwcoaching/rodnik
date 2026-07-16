<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum ReportQuality: string implements HasColor, HasLabel
{
    case Bad = 'bad';
    case Uncertain = 'uncertain';
    case Good = 'good';

    public function getLabel(): string
    {
        return match ($this) {
            self::Bad => 'Poor water',
            self::Uncertain => 'Questionable water',
            self::Good => 'Good water',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Bad => 'danger',
            self::Uncertain => 'warning',
            self::Good => 'success',
        };
    }
}
