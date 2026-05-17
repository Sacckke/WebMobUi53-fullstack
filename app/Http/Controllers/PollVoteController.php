<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class PollVoteController extends Controller
{
    public function __invoke(Request $request, string $token)
    {
        return view('polls.vote', [
            'token'         => $token,
            'authenticated' => $request->user() !== null,
            'loginUrl'      => route('login'),
        ]);
    }
}
