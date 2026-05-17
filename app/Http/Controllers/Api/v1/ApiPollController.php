<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Models\Poll;
use App\Models\PollVote;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ApiPollController extends Controller
{
    /**
     * Liste les sondages de l'utilisateur connecté
     * GET /api/v1/polls
     */
    public function index(Request $request)
    {
        $polls = $request->user()
            ->polls()
            ->with('options')
            ->withCount('votes')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json($polls);
    }

    /**
     * Crée un nouveau sondage — brouillon par défaut, ou lancé si 'launch: true'
     * POST /api/v1/polls
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'question'               => 'required|string|max:255',
            'title'                  => 'nullable|string|max:255',
            'allow_multiple_choices' => 'boolean',
            'allow_vote_change'      => 'boolean',
            'results_public'         => 'boolean',
            'duration'               => 'nullable|integer|min:60',
            'options'                => 'required|array|min:2',
            'options.*'              => 'required|string|max:255',
            'launch'                 => 'sometimes|boolean',
        ]);

        // Si l'utilisateur veut lancer directement, pas la peine de passer par brouillon
        $isDraft = !($validated['launch'] ?? false);
        $startedAt = $isDraft ? null : now();
        $endsAt = null;

        if (!$isDraft && ($validated['duration'] ?? null)) {
            $endsAt = now()->addSeconds($validated['duration']);
        }

        $poll = Poll::create([
            'user_id'                => $request->user()->id,
            'question'               => $validated['question'],
            'title'                  => $validated['title'] ?? null,
            'secret_token'           => Str::random(32),
            'is_draft'               => $isDraft,
            'allow_multiple_choices' => $validated['allow_multiple_choices'] ?? false,
            'allow_vote_change'      => $validated['allow_vote_change'] ?? false,
            'results_public'         => $validated['results_public'] ?? false,
            'duration'               => $validated['duration'] ?? null,
            'started_at'             => $startedAt,
            'ends_at'                => $endsAt,
        ]);

        foreach ($validated['options'] as $label) {
            $poll->options()->create(['label' => $label]);
        }

        return response()->json($poll->load('options'), 201);
    }

    /**
     * Met à jour un sondage existant (propriétaire seulement)
     * PUT /api/v1/polls/{id}
     */
    public function update(Request $request, int $id)
    {
        $poll = Poll::where('id', $id)
            ->where('user_id', $request->user()->id)
            ->first();

        if (!$poll) {
            return response()->json(['message' => 'Sondage introuvable.'], 404);
        }

        $validated = $request->validate([
            'question'               => 'sometimes|string|max:255',
            'title'                  => 'nullable|string|max:255',
            'allow_multiple_choices' => 'sometimes|boolean',
            'allow_vote_change'      => 'sometimes|boolean',
            'results_public'         => 'sometimes|boolean',
            'duration'               => 'nullable|integer|min:60',
            'is_draft'               => 'sometimes|boolean',
            'options'                => 'sometimes|array|min:2',
            'options.*'              => 'required|string|max:255',
        ]);

        // Lancement du sondage : passage de brouillon à actif
        if (isset($validated['is_draft']) && $validated['is_draft'] === false && $poll->is_draft) {
            $validated['started_at'] = now();

            $dur = $validated['duration'] ?? $poll->duration;
            if ($dur) {
                $validated['ends_at'] = now()->addSeconds($dur);
            }
        }

        $poll->update($validated);

        // Options modifiables seulement si encore en brouillon
        if (isset($validated['options']) && $poll->is_draft) {
            $poll->options()->delete();
            foreach ($validated['options'] as $label) {
                $poll->options()->create(['label' => $label]);
            }
        }

        return response()->json($poll->load('options'));
    }

    /**
     * Affiche un sondage par son token (accès public)
     * Retourne aussi les options votées par l'utilisateur connecté si applicable
     * GET /api/v1/polls/{token}
     */
    public function show(Request $request, string $token)
    {
        $poll = Poll::with(['options' => function ($query) {
            $query->withCount('votes');
        }])->where('secret_token', $token)->first();

        if (!$poll) {
            return response()->json(['message' => 'Sondage introuvable.'], 404);
        }

        // Sanctum stateful : on peut récupérer l'user même sur une route publique
        $user = $request->user();
        $userVoteIds = [];
        $isOwner = false;

        if ($user) {
            $userVoteIds = PollVote::where('poll_id', $poll->id)
                ->where('user_id', $user->id)
                ->pluck('poll_option_id')
                ->toArray();
            $isOwner = $poll->user_id === $user->id;
        }

        return response()->json([
            ...$poll->toArray(),
            'user_voted_option_ids' => $userVoteIds,
            'is_owner'              => $isOwner,
        ]);
    }

    /**
     * Résultats d'un sondage — accès conditionnel
     * Si results_public = true -> tout le monde peut voir
     * Sinon -> seulement le propriétaire
     * GET /api/v1/polls/{token}/results
     */
    public function results(Request $request, string $token)
    {
        $poll = Poll::with(['options' => function ($query) {
            $query->withCount('votes');
        }])->where('secret_token', $token)->first();

        if (!$poll) {
            return response()->json(['message' => 'Sondage introuvable.'], 404);
        }

        $user = $request->user();
        $isOwner = $user && $poll->user_id === $user->id;

        if (!$poll->results_public && !$isOwner) {
            return response()->json(['message' => 'Résultats non disponibles.'], 403);
        }

        $totalVotes = $poll->votes()->count();

        $options = $poll->options->map(function ($option) use ($totalVotes) {
            return [
                'id'          => $option->id,
                'label'       => $option->label,
                'votes_count' => $option->votes_count,
                'percentage'  => $totalVotes > 0
                    ? round(($option->votes_count / $totalVotes) * 100, 1)
                    : 0,
            ];
        });

        return response()->json([
            'poll'        => [
                'id'       => $poll->id,
                'question' => $poll->question,
                'title'    => $poll->title,
                'ends_at'  => $poll->ends_at,
                'is_draft' => $poll->is_draft,
            ],
            'total_votes' => $totalVotes,
            'options'     => $options,
        ]);
    }

    /**
     * Vote sur un sondage
     * POST /api/v1/polls/{token}/vote
     */
    public function vote(Request $request, string $token)
    {
        $poll = Poll::with('options')->where('secret_token', $token)->first();

        if (!$poll) {
            return response()->json(['message' => 'Sondage introuvable.'], 404);
        }

        if ($poll->is_draft) {
            return response()->json(['message' => 'Ce sondage n\'est pas encore lancé.'], 422);
        }

        if ($poll->ends_at && now()->isAfter($poll->ends_at)) {
            return response()->json(['message' => 'Ce sondage est terminé.'], 422);
        }

        $validated = $request->validate([
            'option_ids'   => 'required|array|min:1',
            'option_ids.*' => 'integer|exists:poll_options,id',
        ]);

        // Choix unique → on garde que le premier
        $optionIds = $poll->allow_multiple_choices
            ? $validated['option_ids']
            : [$validated['option_ids'][0]];

        $validIds = $poll->options->pluck('id')->toArray();
        foreach ($optionIds as $oid) {
            if (!in_array($oid, $validIds)) {
                return response()->json(['message' => 'Option invalide.'], 422);
            }
        }

        $user = $request->user();
        $existingVotes = PollVote::where('poll_id', $poll->id)
            ->where('user_id', $user->id)
            ->get();

        if ($existingVotes->isNotEmpty()) {
            if ($poll->allow_vote_change) {
                PollVote::where('poll_id', $poll->id)
                    ->where('user_id', $user->id)
                    ->delete();
            } else {
                return response()->json(['message' => 'Tu as déjà voté.'], 422);
            }
        }

        foreach ($optionIds as $oid) {
            PollVote::create([
                'poll_id'        => $poll->id,
                'user_id'        => $user->id,
                'poll_option_id' => $oid,
            ]);
        }

        return response()->json(['message' => 'Vote enregistré !'], 201);
    }

    /**
     * Supprime un sondage (propriétaire seulement)
     * DELETE /api/v1/polls/{id}
     */
    public function remove(Request $request, int $id)
    {
        $poll = Poll::where('id', $id)
            ->where('user_id', $request->user()->id)
            ->first();

        if (!$poll) {
            return response()->json(['message' => 'Sondage introuvable.'], 404);
        }

        $poll->delete();

        return response()->json(['message' => 'Supprimé.']);
    }
}
