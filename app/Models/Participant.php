<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Participant extends Model
{
    protected $fillable = [
        'name',
        'stage_name',
        'age',
        'gender',
        'city',
        'province',
        'phone',
        'email',
        'photo_url',
        'biography',
        'skills',
        'social_links',
        'voting_code',
        'active',
        'eliminated_episode_id',
        'final_position',
        'overall_average',
    ];

    protected $casts = [
        'active' => 'boolean',
        'age' => 'integer',
        'final_position' => 'integer',
        'overall_average' => 'decimal:2',
    ];

    /**
     * Get the episode where this participant was eliminated
     */
    public function eliminatedEpisode(): BelongsTo
    {
        return $this->belongsTo(Episode::class, 'eliminated_episode_id');
    }

    /**
     * Get all public votes for this participant
     */
    public function publicVotes(): HasMany
    {
        return $this->hasMany(PublicVote::class);
    }

    /**
     * Get all judge votes for this participant
     */
    public function judgeVotes(): HasMany
    {
        return $this->hasMany(JudgeVote::class);
    }

    /**
     * Get all participations (episodes) for this participant
     */
    public function participations(): HasMany
    {
        return $this->hasMany(Participation::class);
    }

    /**
     * Check if the participant is still active in the competition
     */
    public function isActive(): bool
    {
        return $this->active && is_null($this->eliminated_episode_id);
    }

    /**
     * Get the total number of public votes
     */
    public function getTotalPublicVotesAttribute(): int
    {
        return $this->publicVotes()->count();
    }

    /**
     * Get the participant's display name (stage name or real name)
     */
    public function getDisplayNameAttribute(): string
    {
        return $this->stage_name ?: $this->name;
    }

    /**
     * Scope to get only active participants
     */
    public function scopeActive($query)
    {
        return $query->where('active', true)->whereNull('eliminated_episode_id');
    }

    /**
     * Scope to get eliminated participants
     */
    public function scopeEliminated($query)
    {
        return $query->whereNotNull('eliminated_episode_id');
    }
}
