<?php

namespace App\Http\Controllers;

use App\Models\Spring;
use Illuminate\Http\Request;

class SpringJsonController extends Controller
{
    public function index()
    {
        $springs = Spring::limit(10000)->get();

        $features = $springs->map(function($spring) {
            return [
                'type' => 'Feature',
                'geometry' => [
                    'type' => 'Point',
                    'coordinates' => [
                        floatval($spring->longitude),
                        floatval($spring->latitude)
                    ]
                ],
                'properties' => [
                    'id' => $spring->id,
                    'name' => $spring->name,
                    'intermittent' => 'no',
                ]
            ];
        });

        $result = [
            "type" => "FeatureCollection",
            "features" => $features
        ];

        return json_encode($result, JSON_PRETTY_PRINT);
    }
}
