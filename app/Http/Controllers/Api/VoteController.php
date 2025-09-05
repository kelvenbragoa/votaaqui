<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Participant;
use App\Models\PublicVote;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class VoteController extends Controller
{
    /**
     * Store a newly created vote.
     */
    public function store(Request $request)
    {
        $request->validate([
            'participant_id' => 'required|exists:participants,id',
            'voter_name' => 'nullable|string|max:255',
            'voter_email' => 'nullable|email|max:255',
            'voter_phone' => 'nullable|string|max:20',
            'voter_ip' => 'nullable|ip',
            'voter_device' => 'nullable|string',
            'newsletter_subscription' => 'nullable|boolean',
        ]);

        try {
            // Check if participant is active and not eliminated
            $participant = Participant::where('id', $request->participant_id)
                ->where('active', true)
                ->whereNull('eliminated_episode_id')
                ->first();

            if (!$participant) {
                return response()->json([
                    'success' => false,
                    'message' => 'Participante não está disponível para votação'
                ], 400);
            }

            // Get client IP if not provided
            $voterIp = $request->voter_ip ?? $request->ip();

            // Check rate limiting (optional - limit votes per IP per hour)
            $recentVotes = PublicVote::where('voter_ip', $voterIp)
                ->where('created_at', '>=', now()->subHour())
                ->count();

            if ($recentVotes >= 10) { // Limit to 10 votes per hour per IP
                return response()->json([
                    'success' => false,
                    'message' => 'Limite de votos por hora excedido. Tente novamente mais tarde.'
                ], 429);
            }

            // Create the vote
            $vote = PublicVote::create([
                'participant_id' => $request->participant_id,
                'voter_name' => $request->voter_name,
                'voter_email' => $request->voter_email,
                'voter_phone' => $request->voter_phone,
                'voter_ip' => $voterIp,
                'voter_device' => $request->voter_device ?? $request->userAgent(),
                'newsletter_subscription' => $request->newsletter_subscription ?? false,
                'voted_at' => now(),
            ]);

            return response()->json([
                'success' => true,
                'data' => $vote,
                'message' => 'Voto registrado com sucesso!',
                'participant' => [
                    'name' => $participant->display_name,
                    'voting_code' => $participant->voting_code
                ]
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erro ao registrar voto',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get voting statistics
     */
    public function statistics()
    {
        try {
            $stats = DB::table('public_votes')
                ->join('participants', 'public_votes.participant_id', '=', 'participants.id')
                ->select('participants.id', 'participants.name', 'participants.stage_name', 
                        DB::raw('COUNT(public_votes.id) as vote_count'))
                ->where('participants.active', true)
                ->whereNull('participants.eliminated_episode_id')
                ->groupBy('participants.id', 'participants.name', 'participants.stage_name')
                ->orderBy('vote_count', 'desc')
                ->get();

            $totalVotes = $stats->sum('vote_count');

            // Add percentage
            $stats = $stats->map(function ($stat) use ($totalVotes) {
                $stat->percentage = $totalVotes > 0 ? round(($stat->vote_count / $totalVotes) * 100, 2) : 0;
                return $stat;
            });

            return response()->json([
                'success' => true,
                'data' => [
                    'statistics' => $stats,
                    'total_votes' => $totalVotes,
                    'last_updated' => now()->toISOString()
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erro ao carregar estatísticas',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get votes for a specific participant
     */
    public function participantVotes($participantId)
    {
        try {
            $participant = Participant::findOrFail($participantId);
            
            $voteCount = PublicVote::where('participant_id', $participantId)->count();
            
            $recentVotes = PublicVote::where('participant_id', $participantId)
                ->orderBy('created_at', 'desc')
                ->limit(10)
                ->get(['voter_ip', 'voted_at']);

            return response()->json([
                'success' => true,
                'data' => [
                    'participant' => $participant,
                    'vote_count' => $voteCount,
                    'recent_votes' => $recentVotes
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erro ao carregar votos do participante',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
