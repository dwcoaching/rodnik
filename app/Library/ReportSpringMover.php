<?php

namespace App\Library;

use App\Models\Report;
use App\Models\Spring;

class ReportSpringMover
{
    public function move(Report $report, Spring $target): Report
    {
        $source = $report->spring;

        $report->spring_id = $target->id;
        $report->save();

        $source?->invalidateTiles();
        $target->invalidateTiles();

        return $report->fresh(['spring', 'user', 'photos']);
    }
}
