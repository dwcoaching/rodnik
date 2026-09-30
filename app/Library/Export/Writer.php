<?php

declare(strict_types=1);

namespace App\Library\Export;

use App\Models\User;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

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

    /**
     * Writes the export under a temporary name and moves it into place once it is
     * complete, so the exports page and the latest links never serve a partial file.
     *
     * @param  Closure(string): void  $write  receives the absolute temporary path
     */
    protected function writeAtomically(string $filename, Closure $write): string
    {
        $directory = Storage::disk('public')->path('exports/'.($this->user ? 'users/' : ''));
        $temporaryPath = $directory.'.partial-'.$filename;

        File::ensureDirectoryExists($directory);
        File::delete($temporaryPath);

        try {
            $write($temporaryPath);
            File::move($temporaryPath, $directory.$filename);
        } finally {
            File::delete($temporaryPath);
        }

        return $filename;
    }
}
