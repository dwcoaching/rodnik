<?php

namespace App\Http\Controllers\Tools;

use App\Library\EnrichGPX;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class EnrichedGPXController extends Controller
{
    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return view('tools.enriched-gpx.create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'gpx' => 'required|file|mimetypes:application/xml,text/xml',
        ]);

        $gpx = $request->file('gpx');
        $gpxString = $gpx->get();
        $originalFilename = $gpx->getClientOriginalName();
        
        // Extract the filename without extension and sanitize it
        $filenameWithoutExt = pathinfo($originalFilename, PATHINFO_FILENAME);
        $sanitizedFilename = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $filenameWithoutExt);
        
        // Create the new enriched filename
        $enrichedFilename = $sanitizedFilename . '-enriched.gpx';
        
        $enrichedGPX = EnrichGPX::enrich($gpxString);

        return response($enrichedGPX)
            ->withHeaders([
                'Content-Type' => 'application/xml',
                'Content-Disposition' => 'attachment; filename="' . $enrichedFilename . '"',
            ]);
    }
}
