<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PublicVote extends Model
{
    protected $fillable = [
        'participant_id',
        'voter_ip',
        'voter_device',
        'voted_at',
    ];

    protected $casts = [
        'voted_at' => 'datetime',
    ];

    /**
     * Get the participant that received this vote
     */
    public function participant(): BelongsTo
    {
        return $this->belongsTo(Participant::class);
    }

    /**
     * Scope to get votes from today
     */
    public function scopeToday($query)
    {
        return $query->whereDate('voted_at', today());
    }

    /**
     * Scope to get votes from this week
     */
    public function scopeThisWeek($query)
    {
        return $query->whereBetween('voted_at', [now()->startOfWeek(), now()->endOfWeek()]);
    }

    /**
     * Scope to get votes by IP address
     */
    public function scopeByIp($query, $ip)
    {
        return $query->where('voter_ip', $ip);
    }
}
