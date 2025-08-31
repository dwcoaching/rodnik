<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Photo;
use Illuminate\Http\Request;

class UserPhotoController extends Controller
{
    public function index(User $user)
    {
        $photos = $user->photos
            // ->load('report')
            // ->filter(function ($photo) {
            //     return $photo->report->visible;
            // })
            ;
        

        return view('users.photos.index', ['photos' => $photos, 'user' => $user]);
    }
}
