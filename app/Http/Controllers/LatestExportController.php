<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Library\Export\FileParser;
use Illuminate\Http\RedirectResponse;

final class LatestExportController extends Controller
{
    public const FORMATS = [
        'json' => 'json',
        'csv' => 'zip',
        'xlsx' => 'xlsx',
    ];

    public function __invoke(string $format): RedirectResponse
    {
        $file = FileParser::getExportFiles()
            ->firstWhere('extension', self::FORMATS[$format]);

        abort_if($file === null, 404);

        return redirect('/exports/'.$file['filename'])
            ->header('Cache-Control', 'no-store');
    }
}
