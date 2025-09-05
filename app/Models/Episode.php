<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use App\Traits\Blameable;

class Episode extends Model
{
    use Blameable;

    protected $fillable = [
        'episode_number',
        'title',
        'description',
        'episode_type',
        'air_date',
        'eliminations',
        'status',
        'voting_open',
        'voting_start',
        'voting_end',
        'special_settings',
        'jury_weight',
        'public_weight',
        'notes'
    ];

    protected $casts = [
        'air_date' => 'datetime',
        'voting_start' => 'datetime',
        'voting_end' => 'datetime',
        'special_settings' => 'array',
        'voting_open' => 'boolean',
        'jury_weight' => 'decimal:2',
        'public_weight' => 'decimal:2'
    ];

    // Relationships
    public function participants(): BelongsToMany
    {
        return $this->belongsToMany(Participant::class, 'participations')
                    ->withPivot(['status', 'position', 'elimination_order'])
                    ->withTimestamps();
    }

    public function eliminatedParticipants(): HasMany
    {
        return $this->hasMany(Participant::class, 'eliminated_episode_id');
    }

    public function judgeVotes(): HasMany
    {
        return $this->hasMany(JudgeVote::class);
    }

    public function publicVotes(): HasMany
    {
        return $this->hasMany(PublicVote::class);
    }

    // Scopes
    public function scopeFinished($query)
    {
        return $query->where('status', 'finished');
    }

    public function scopeLive($query)
    {
        return $query->where('status', 'live');
    }

    public function scopeWithVotingOpen($query)
    {
        return $query->where('voting_open', true);
    }

    // Methods
    public function isVotingActive(): bool
    {
        if (!$this->voting_open) {
            return false;
        }

        $now = now();
        
        if ($this->voting_start && $now->lt($this->voting_start)) {
            return false;
        }

        if ($this->voting_end && $now->gt($this->voting_end)) {
            return false;
        }

        return true;
    }

    public function getVotingResults(): array
    {
        $publicVotes = $this->publicVotes()
            ->selectRaw('participant_id, COUNT(*) as vote_count')
            ->groupBy('participant_id')
            ->get()
            ->keyBy('participant_id');

        $judgeVotes = $this->judgeVotes()
            ->selectRaw('participant_id, AVG(score) as avg_score, COUNT(*) as judge_count')
            ->groupBy('participant_id')
            ->get()
            ->keyBy('participant_id');

        $results = [];
        $participants = $this->participants;

        foreach ($participants as $participant) {
            $publicCount = $publicVotes->get($participant->id)?->vote_count ?? 0;
            $judgeAvg = $judgeVotes->get($participant->id)?->avg_score ?? 0;
            
            $results[$participant->id] = [
                'participant' => $participant,
                'public_votes' => $publicCount,
                'judge_average' => $judgeAvg,
                'total_score' => ($publicCount * $this->public_weight / 100) + 
                               ($judgeAvg * $this->jury_weight / 100)
            ];
        }

        return collect($results)->sortByDesc('total_score')->toArray();
    }

    public function eliminateParticipants(array $participantIds): bool
    {
        try {
            Participant::whereIn('id', $participantIds)
                ->update([
                    'eliminated_episode_id' => $this->id,
                    'active' => false,
                    'elimination_date' => now()
                ]);

            $this->update(['eliminations' => count($participantIds)]);
            
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }
}
