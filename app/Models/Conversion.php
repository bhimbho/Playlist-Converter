<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Conversion extends Model
{
    protected $fillable = [
        'user_id',
        'spotify_playlist_id',
        'spotify_playlist_name',
        'spotify_playlist_url',
        'youtube_playlist_id',
        'youtube_playlist_title',
        'youtube_playlist_url',
        'total_tracks',
        'converted_tracks',
        'failed_tracks',
        'conversion_results',
    ];

    protected $casts = [
        'conversion_results' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
