<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Photo;
use App\Models\Spring;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class PhotosController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Spring $spring)
    {
        return response()->json($spring->photos->pluck('url'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(Photo $photo)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Photo $photo)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Photo $photo)
    {
        //
    }
}
