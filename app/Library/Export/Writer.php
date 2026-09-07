<?php

declare(strict_types=1);

namespace App\Library\Export;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

abstract class Writer
{
    public ?User $user = null;

    public function __construct(public Builder $query) {}

    abstract protected function write(): string;

    final public function forUser(?User $user = null): static
    {
        $this->user = $user;

        return $this;
    }

    final public function save(): string
    {
        return ExportLock::run(function (): string {
            abort_if($this->user && ! $this->user->newQuery()->whereKey($this->user->id)->exists(), 403);

            return $this->write();
        });
    }
}
