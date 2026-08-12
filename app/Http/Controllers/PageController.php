<?php

namespace App\Http\Controllers;

use App\Models\Page;
use App\Models\ParkRule;

class PageController extends Controller
{
    /** Página de texto legal (contenido desde la tabla `pages`). */
    public function show(string $slug)
    {
        $page = Page::where('slug', $slug)->where('is_active', true)->firstOrFail();

        return view('pages.text', ['page' => $page]);
    }

    /** Normas del parque (desde `park_rules`). */
    public function rules()
    {
        return view('pages.rules', [
            'rules' => ParkRule::where('is_active', true)->orderBy('position')->get(),
        ]);
    }
}
