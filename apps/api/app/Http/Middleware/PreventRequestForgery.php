<?php

namespace App\Http\Middleware;

class PreventRequestForgery extends \Illuminate\Foundation\Http\Middleware\PreventRequestForgery
{
    // M1 uses form/Livewire tokens, not a SPA double-submit cookie. Keep validation enabled.
    protected $addHttpCookie = false;
}
