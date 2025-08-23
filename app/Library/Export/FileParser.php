<?php

namespace App\Library\Export;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

class FileParser
{
    /**
     * Get all export files with parsed timestamps from the exports directory
     *
     * @return Collection<array{filename: string, timestamp: Carbon, extension: string, size: int, size_human: string}>
     */
    public static function getExportFiles(): Collection
    {
        $files = collect();
        
        // Get files from main exports directory
        $mainFiles = Storage::disk('public')->files('exports');
        foreach ($mainFiles as $file) {
            if ($parsed = self::parseFilename($file)) {
                $files->push($parsed);
            }
        }
        
        return $files->sortByDesc('timestamp');
    }
    
    /**
     * Parse a single filename and extract information
     *
     * @param string $filePath
     * @return array{filename: string, timestamp: Carbon, extension: string, size: int, size_human: string}|null
     */
    public static function parseFilename(string $filePath): ?array
    {
        $filename = basename($filePath);
        $extension = pathinfo($filename, PATHINFO_EXTENSION);
        
        // Pattern for regular exports: rodnik-from-YYYY-MM-DD_HH-ii-ss.ext
        
        $patterns = [
            // Pattern for general exports: rodnik-from-timestamp.ext
            '/^rodnik-from-(\d{4}-\d{2}-\d{2}_\d{2}-\d{2}-\d{2})\.' . preg_quote($extension, '/') . '$/' => ['type' => 'full', 'user_index' => null, 'timestamp_index' => 1],
        ];
        
        $matches = null;
        $matchedPattern = null;
        
        foreach ($patterns as $pattern => $config) {
            if (preg_match($pattern, $filename, $matches)) {
                $matchedPattern = $config;
                break;
            }
        }
        
        if (!$matchedPattern) {
            return null;
        }
        
        $timestampString = $matches[$matchedPattern['timestamp_index']];
        
        try {
            // Convert timestamp format from YYYY-MM-DD_HH-ii-ss to Carbon
            $timestamp = Carbon::createFromFormat('Y-m-d_H-i-s', $timestampString);
        } catch (\Exception $e) {
            return null;
        }
        
        $sizeBytes = 0;
        try {
            $sizeBytes = (int) Storage::disk('public')->size($filePath);
        } catch (\Throwable $e) {
            $sizeBytes = 0;
        }
        
        return [
            'filename' => $filename,
            'timestamp' => $timestamp,
            'extension' => $extension,
            'size' => $sizeBytes,
            'size_human' => self::humanReadableSize($sizeBytes),
        ];
    }

    protected static function humanReadableSize(int $bytes): string
    {
        if ($bytes < 1024) {
            return $bytes . ' B';
        }
        $units = ['KB', 'MB', 'GB', 'TB'];
        $bytesFloat = (float) $bytes;
        foreach ($units as $index => $unit) {
            $bytesFloat = $bytesFloat / 1024;
            if ($bytesFloat < 1024 || $unit === 'TB') {
                return number_format($bytesFloat, $bytesFloat >= 100 ? 0 : 1) . ' ' . $unit;
            }
        }
        return number_format($bytesFloat, 1) . ' TB';
    }
}
