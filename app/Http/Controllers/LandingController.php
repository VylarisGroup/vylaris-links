<?php

namespace App\Http\Controllers;

use App\Support\Themes;

class LandingController extends Controller
{
    public function index()
    {
        return view('landing', ['themes' => Themes::THEMES, 'layouts' => Themes::LAYOUTS]);
    }
}
