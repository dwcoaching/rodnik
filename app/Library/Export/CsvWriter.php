<?php

namespace App\Library\Export;

use App\Models\User;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\AbstractWriter;
use App\Library\Export\CsvTransformer;
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
        $this->writeSprings($allSprings);
        $this->writeReports($allReports);
        $this->writeEdits($allEdits);
        $this->writePhotos($allPhotos);
    }

    public function getOpenSpoutWriter(): AbstractWriter
    {       
        return new OpenSpoutCsvWriter();
    }

    public function writeSprings(array $allSprings): void
    {
        $writer = $this->getOpenSpoutWriter();
        
        $writer->openToFile(Storage::disk('public')->path('rodnik-springs' 
        . ($this->user ? '-user-' . $this->user->id : '') 
        . '.csv'));
        
        $writer->addRows(array_map(fn ($spring) => Row::fromValues($spring), $allSprings));
        
        $writer->close();
    }

    public function writeReports(array $allReports): void
    {
        $writer = $this->getOpenSpoutWriter();
        $writer->openToFile(Storage::disk('public')->path('rodnik-reports' 
        . ($this->user ? '-user-' . $this->user->id : '') 
        . '.csv'));

        $writer->addRows(array_map(fn ($spring) => Row::fromValues($spring), $allReports));
        
        $writer->close();
    }

    public function writeEdits(array $allEdits): void
    {
        $writer = $this->getOpenSpoutWriter();
        $writer->openToFile(Storage::disk('public')->path('rodnik-edits' 
        . ($this->user ? '-user-' . $this->user->id : '') 
        . '.csv'));

        $writer->addRows(array_map(fn ($spring) => Row::fromValues($spring), $allEdits));
        
        $writer->close();
    }


    public function writePhotos(array $allPhotos): void
    {
        $writer = $this->getOpenSpoutWriter();
        $writer->openToFile(Storage::disk('public')->path('rodnik-photos' 
        . ($this->user ? '-user-' . $this->user->id : '') 
        . '.csv'));

        $writer->addRows(array_map(fn ($spring) => Row::fromValues($spring), $allPhotos));
        
        $writer->close();
    }

}