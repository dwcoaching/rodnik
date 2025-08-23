<?php

namespace App\Library\Export;

use ZipArchive;
use App\Models\User;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\AbstractWriter;
use App\Library\Export\CsvTransformer;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use OpenSpout\Common\Entity\Style\Style;
use Illuminate\Database\Eloquent\Builder;
use OpenSpout\Writer\CSV\Writer as OpenSpoutCsvWriter;
use OpenSpout\Writer\XLSX\Writer as OpenSpoutXlsxWriter;

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

    public function format(?string $format = 'csv'): static
    {
        $this->format = $format;
        return $this;
    }

    public function save(): void
    {
        $allSprings = [];
        $allReports = [];
        $allEdits = [];
        $allPhotos = [];

        $firstRun = true;
        
        $this->query->chunk(500, function ($springs) use (&$allSprings, &$allReports, &$allEdits, &$allPhotos, &$firstRun) {
            $csvTransformer = new CsvTransformer($springs)->forUser($this->user);

            if ($firstRun) {
                $allSprings[] = $csvTransformer->getHeadersForSprings();
                $allReports[] = $csvTransformer->getHeadersForReports();
                $allEdits[] = $csvTransformer->getHeadersForEdits();
                $allPhotos[] = $csvTransformer->getHeadersForPhotos();
                $firstRun = false;
            }
            
            $processedSprings = $csvTransformer->transformSprings(); 
            $processedReports = $csvTransformer->transformReports();
            $processedEdits = $csvTransformer->transformEdits();
            $processedPhotos = $csvTransformer->transformPhotos();

            $allSprings = array_merge($allSprings, $processedSprings);
            $allReports = array_merge($allReports, $processedReports);
            $allEdits = array_merge($allEdits, $processedEdits);
            $allPhotos = array_merge($allPhotos, $processedPhotos);
            echo "Processed " . count($allSprings) - 1 . " springs\n";
        });

        $this->write($allSprings, $allReports, $allEdits, $allPhotos);
    }

    public function write(array $allSprings, array $allReports, array $allEdits, array $allPhotos): void
    {
        $springsFilePath = $this->writeSprings($allSprings);
        $reportsFilePath = $this->writeReports($allReports);
        $editsFilePath = $this->writeEdits($allEdits);
        $photosFilePath = $this->writePhotos($allPhotos);

        $this->zip($springsFilePath, $reportsFilePath, $editsFilePath, $photosFilePath);

        $this->deleteFiles($springsFilePath, $reportsFilePath, $editsFilePath, $photosFilePath);
    }

    public function getOpenSpoutWriter(): AbstractWriter
    {       
        return new OpenSpoutCsvWriter();
    }

    public function writeSprings(array $allSprings): string
    {
        $writer = $this->getOpenSpoutWriter();

        $timestamp = now()->format('Y-m-d_H-i-s');

        $filePath = Storage::disk('public')->path('exports/'
        . ($this->user ? 'users/' : '') 
        . 'rodnik-springs' 
        . ($this->user ? '-user-' . $this->user->id : '') 
        . '-from-' . $timestamp . '.csv');

        $writer->openToFile($filePath);
        
        $writer->addRows(array_map(fn ($spring) => Row::fromValues($spring), $allSprings));
        
        $writer->close();

        return $filePath;
    }

    public function writeReports(array $allReports): string
    {
        $writer = $this->getOpenSpoutWriter();

        $timestamp = now()->format('Y-m-d_H-i-s');

        $filePath = Storage::disk('public')->path('exports/'
        . ($this->user ? 'users/' : '') 
        . 'rodnik-reports' 
        . ($this->user ? '-user-' . $this->user->id : '') 
        . '-from-' . $timestamp . '.csv');

        $writer->openToFile($filePath);

        $writer->addRows(array_map(fn ($spring) => Row::fromValues($spring), $allReports));
        
        $writer->close();

        return $filePath;
    }

    public function writeEdits(array $allEdits): string
    {
        $writer = $this->getOpenSpoutWriter();

        $timestamp = now()->format('Y-m-d_H-i-s');

        $filePath = Storage::disk('public')->path('exports/'
        . ($this->user ? 'users/' : '') 
        . 'rodnik-edits' 
        . ($this->user ? '-user-' . $this->user->id : '') 
        . '-from-' . $timestamp . '.csv');

        $writer->openToFile($filePath);

        $writer->addRows(array_map(fn ($spring) => Row::fromValues($spring), $allEdits));
        
        $writer->close();

        return $filePath;
    }


    public function writePhotos(array $allPhotos): string
    {
        $writer = $this->getOpenSpoutWriter();

        $timestamp = now()->format('Y-m-d_H-i-s');

        $filePath = Storage::disk('public')->path('exports/'
        . ($this->user ? 'users/' : '') 
        . 'rodnik-photos' 
        . ($this->user ? '-user-' . $this->user->id : '') 
        . '-from-' . $timestamp . '.csv');

        $writer->openToFile($filePath);

        $writer->addRows(array_map(fn ($spring) => Row::fromValues($spring), $allPhotos));
        
        $writer->close();

        return $filePath;
    }

    public function zip(string $springsFilePath, string $reportsFilePath, string $editsFilePath, string $photosFilePath): string
    {
        $timestamp = now()->format('Y-m-d_H-i-s');
        
        $exportDir = Storage::disk('public')->path('exports/'
        . ($this->user ? 'users/' : ''));
        
        $filePath = $exportDir . 'rodnik' 
        . ($this->user ? '-user-' . $this->user->id : '') 
        . '-from-' . $timestamp . '.zip';
        
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

        return $filePath;
    }

    public function deleteFiles(string $springsFilePath, string $reportsFilePath, string $editsFilePath, string $photosFilePath): void
    {
        unlink($springsFilePath);
        unlink($reportsFilePath);
        unlink($editsFilePath);
        unlink($photosFilePath);
    }
}