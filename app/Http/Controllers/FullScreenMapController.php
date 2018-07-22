<?php

namespace App\Http\Controllers;

use App\CrimeEvent;
use App\CrimeView;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Cache;
use DB;

class FullScreenMapController extends Controller
{
    /**
     * @param Request $request Request.
     *
     * @return void
     */
    public function index(Request $request)
    {

        $data = [];

        return view('FullScreenMap', $data);

    }
}
