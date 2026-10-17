<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\DB;
use Throwable;

class HealthController
{
    public function __invoke()
    {
        try {
            DB::select('SELECT 1');

            return response()->json(['application' => 'laamach-marbre', 'version' => 'v1', 'status' => 'ok', 'database' => 'connected'])->header('Cache-Control', 'no-store');
        } catch (Throwable) {
            return response()->json(['application' => 'laamach-marbre', 'version' => 'v1', 'status' => 'unavailable'])->setStatusCode(503)->header('Cache-Control', 'no-store');
        }
    }
}
