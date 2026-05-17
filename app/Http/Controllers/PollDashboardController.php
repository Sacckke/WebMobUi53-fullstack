<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class PollDashboardController extends Controller
{
    public function __invoke(Request $request)
    {
        // On charge les options et le nombre de votes pour que le frontend ait tout
        $polls = $request->user()
            ->polls()
            ->with('options')
            ->withCount('votes')
            ->orderBy('created_at', 'desc')
            ->get();

        return view('polls.dashboard', [
            'polls' => $polls,
        ]);
    }
}
