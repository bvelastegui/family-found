<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;

class FundController extends Controller
{
    public function index(): RedirectResponse
    {
        return to_route('fund.contributions.index');
    }
}
