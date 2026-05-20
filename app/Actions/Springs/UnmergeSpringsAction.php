<?php

namespace App\Actions\Springs;

use App\Models\Spring;
use App\Library\StatisticsService;
use Illuminate\Support\Facades\Gate;

class UnmergeSpringsAction
{
    public function __invoke(Spring $spring)
    {
        $this->authorize();
        return $this->execute($spring);
    }

    public function authorize(): void
    {
        Gate::authorize('admin');
    }

    public function execute(Spring $spring): Spring
    {
        if ($spring->redirect_to_spring_id === null) {
            return $spring;
        }

        $spring->redirect_to_spring_id = null;
        $spring->save();

        $spring->invalidateTiles();
        StatisticsService::invalidateSpringsCount();

        return $spring;
    }
}
