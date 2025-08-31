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

class XlsxWriter extends CsvWriter
{
    public function write(array $allSprings, array $allReports, array $allEdits, array $allPhotos): string {
        $filename = $this->writeXlsx($allSprings, $allReports, $allEdits, $allPhotos);

        return $filename;
    }

    public function getOpenSpoutWriter(): OpenSpoutXlsxWriter
    {
        return new OpenSpoutXlsxWriter();
    }

    public function writeXlsx(array $allSprings, array $allReports, array $allEdits, array $allPhotos): string
    {
        $writer = $this->getOpenSpoutWriter();

        $timestamp = now()->format('Y-m-d_H-i-s');

        $filename = 'rodnik' 
            . ($this->user ? '-user-' . $this->user->id : '') 
            . '-from-' . $timestamp . '.xlsx';

        $writer->openToFile(Storage::disk('public')->path('exports/'
        . ($this->user ? 'users/' : '') 
        . $filename));

        // Create bold style for headers
        $headerStyle = new Style();
        $headerStyle->setFontBold();
        $headerStyle->setShouldWrapText(false);

        // Create default style without text wrapping
        $defaultStyle = new Style();
        $defaultStyle->setShouldWrapText(false);

        // Write Springs data to the first sheet
        $springsSheet = $writer->getCurrentSheet();
        $springsSheet->setName('Springs');
        $columnWidths = [
            1 => 10,  // id
            2 => 15,  // latitude
            3 => 15,  // longitude
            4 => 20,  // type
            5 => 35,  // name
            6 => 15,  // osm_latitude
            7 => 15,  // osm_longitude
            8 => 20,  // osm_type
            9 => 35,  // osm_name
        ];
        
        $this->styleSheet($writer, $springsSheet, $allSprings, $headerStyle, $defaultStyle, $columnWidths);

        // Create and write Reports sheet
        $reportsSheet = $writer->addNewSheetAndMakeItCurrent();
        $reportsSheet->setName('Reports');
        $this->styleSheet($writer, $reportsSheet, $allReports, $headerStyle, $defaultStyle, [
            1 => 10,  // id
            2 => 12,  // spring_id
            3 => 25,  // user (increased for full names)
            4 => 10,  // user_id
            5 => 20,  // created_at
            6 => 20,  // visited_at
            7 => 15,  // state
            8 => 15,  // quality
            9 => 60   // comment (significantly increased for long text)
        ]);

        // Create and write Edits sheet
        $editsSheet = $writer->addNewSheetAndMakeItCurrent();
        $editsSheet->setName('Edits');
        $this->styleSheet($writer, $editsSheet, $allEdits, $headerStyle, $defaultStyle, [
            1 => 10,  // id
            2 => 12,  // spring_id
            3 => 25,  // user
            4 => 10,  // user_id
            5 => 15,  // latitude
            6 => 15,  // longitude
            7 => 20,  // type
            8 => 35,  // name
            9 => 20   // created_at
        ]);

        // Create and write Photos sheet
        $photosSheet = $writer->addNewSheetAndMakeItCurrent();
        $photosSheet->setName('Photos');
        $this->styleSheet($writer, $photosSheet, $allPhotos, $headerStyle, $defaultStyle, [
            1 => 10,  // id
            2 => 12,  // report_id
            3 => 12,  // spring_id
            4 => 60   // url (increased for long URLs)
        ]);
        
        $writer->close();

        return $filename;
    }

    private function styleSheet($writer, $sheet, array $data, Style $headerStyle, Style $defaultStyle, array $columnWidths): void
    {
        if (empty($data)) {
            return;
        }

        // Set column widths
        foreach ($columnWidths as $columnIndex => $width) {
            $sheet->setColumnWidth($width, $columnIndex);
        }

        // Add header row with bold style (but no text wrapping)
        $headerRow = Row::fromValues($data[0], $headerStyle);
        $writer->addRow($headerRow);

        // Add data rows with no text wrapping (skip first row as it's the header)
        $dataRows = array_slice($data, 1);

        foreach ($dataRows as $row) {
            $writer->addRow(Row::fromValues($row, $defaultStyle));
        }
    }
}