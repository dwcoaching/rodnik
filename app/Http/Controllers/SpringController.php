<?php

namespace App\Http\Controllers;

use Illuminate\Http\Response;
use App\Models\Spring;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SpringController extends Controller
{

    public function __construct()
    {

    }

    /**
     * Display a listing of the resource.
     *
     * @return Response
     */
    public function index()
    {

    }

    /**
     * Show the form for creating a new resource.
     *
     * @return Response
     */
    public function create()
    {
        return redirect(duo_route(['location' => 1]), 301);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param Request $request
     * @return Response
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     *
     * @param Spring $spring
     * @return Response
     */
    public function show($springId)
    {
        return redirect(duo_route(['spring' => $springId]), 301);
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param Spring $spring
     * @return Response
     */
    public function edit(Spring $spring)
    {
        $this->authorize('update', $spring);

        return view('springs.edit', compact('spring'));
    }

    /**
     * Update the specified resource in storage.
     *
     * @param Request $request
     * @param Spring $spring
     * @return Response
     */
    public function update(Request $request, Spring $spring)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param Spring $spring
     * @return Response
     */
    public function destroy(Spring $spring)
    {
        //
    }
}
