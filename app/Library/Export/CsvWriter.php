<?php

namespace App\Library\Export;

use App\Models\User;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\AbstractWriter;
use App\Library\Export\CsvTransformer;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Illuminate\Database\Eloquent\Builder;
use OpenSpout\Writer\CSV\Writer as OpenSpoutCsvWriter;

class CsvWriter
{
    public ?User $user = null;
    public function __construct(
        public Builder $query
    ) {}

    public function forUser(?User $user = null): static
    {
        $this->user = $user;
        return $this;
    }

    public function save(): string
    {
        $timestamp = now()->format('Y-m-d_H-i-s');

        $springsFilePath = $this->buildFilePath('rodnik-springs', $timestamp);
        $reportsFilePath = $this->buildFilePath('rodnik-reports', $timestamp);
        $editsFilePath = $this->buildFilePath('rodnik-edits', $timestamp);
        $photosFilePath = $this->buildFilePath('rodnik-photos', $timestamp);

        $springsWriter = $this->getOpenSpoutWriter();
        $reportsWriter = $this->getOpenSpoutWriter();
        $editsWriter = $this->getOpenSpoutWriter();
        $photosWriter = $this->getOpenSpoutWriter();

        $springsWriter->openToFile($springsFilePath);
        $reportsWriter->openToFile($reportsFilePath);
        $editsWriter->openToFile($editsFilePath);
        $photosWriter->openToFile($photosFilePath);

        try {
            $firstRun = true;

            $this->query->chunk(500, function ($springs) use (&$firstRun, $springsWriter, $reportsWriter, $editsWriter, $photosWriter) {
                $csvTransformer = (new CsvTransformer($springs))->forUser($this->user);

                if ($firstRun) {
                    $springsWriter->addRow(Row::fromValues($csvTransformer->getHeadersForSprings()));
                    $reportsWriter->addRow(Row::fromValues($csvTransformer->getHeadersForReports()));
                    $editsWriter->addRow(Row::fromValues($csvTransformer->getHeadersForEdits()));
                    $photosWriter->addRow(Row::fromValues($csvTransformer->getHeadersForPhotos()));
                    $firstRun = false;
                }

                $this->appendRows($springsWriter, $csvTransformer->transformSprings());
                $this->appendRows($reportsWriter, $csvTransformer->transformReports());
                $this->appendRows($editsWriter, $csvTransformer->transformEdits());
                $this->appendRows($photosWriter, $csvTransformer->transformPhotos());
            });
        } finally {
            $springsWriter->close();
            $reportsWriter->close();
            $editsWriter->close();
            $photosWriter->close();
        }

        $filename = $this->zip($springsFilePath, $reportsFilePath, $editsFilePath, $photosFilePath);

        $this->deleteFiles($springsFilePath, $reportsFilePath, $editsFilePath, $photosFilePath);

        return $filename;
    }

    public function getOpenSpoutWriter(): AbstractWriter
    {
        return new OpenSpoutCsvWriter();
    }

    protected function appendRows(AbstractWriter $writer, array $rows): void
    {
        if (empty($rows)) {
            return;
        }

        $writer->addRows(array_map(fn ($row) => Row::fromValues($row), $rows));
    }

    protected function buildFilePath(string $prefix, string $timestamp): string
    {
        return Storage::disk('public')->path('exports/'
            . ($this->user ? 'users/' : '')
            . $prefix
            . ($this->user ? '-user-' . $this->user->id : '')
            . '-from-' . $timestamp . '.csv');
    }

    public function zip(string $springsFilePath, string $reportsFilePath, string $editsFilePath, string $photosFilePath): string
    {
        $timestamp = now()->format('Y-m-d_H-i-s');

        $exportDir = Storage::disk('public')->path('exports/'
        . ($this->user ? 'users/' : ''));

        $filename = 'rodnik'
            . ($this->user ? '-user-' . $this->user->id : '')
            . '-from-' . $timestamp . '.zip';

        $filePath = $exportDir . $filename;

        Process::path($exportDir)
            ->run([
                'zip',
                '-j',  // junk paths (don't store directory structure)
                '-6',  // compression level (1=fastest, 9=best compression, 6=default)
                $filePath,
                $springsFilePath,
                $reportsFilePath,
                $editsFilePath,
                $photosFilePath
            ]);

        return $filename;
    }

    public function deleteFiles(string $springsFilePath, string $reportsFilePath, string $editsFilePath, string $photosFilePath): void
    {
        unlink($springsFilePath);
        unlink($reportsFilePath);
        unlink($editsFilePath);
        unlink($photosFilePath);
    }
}
