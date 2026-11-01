<?php

namespace App\Actions;

use App\Models\PlayerMatch;
use App\Models\User;
use Illuminate\Support\Collection;

class PlayerStats
{
    /**
     * Summarise a player's confirmed results. Unconfirmed, personal and disputed scores never count.
     *
     * @param  Collection<int, PlayerMatch>  $matches  Matches the player took part in, newest first, with players loaded.
     * @return array{played: int, wins: int, losses: int, win_rate: int|null, streak: int, streak_type: string|null, logged: int}
     */
    public function handle(User $user, Collection $matches): array
    {
        $results = $matches->where('status', 'confirmed')->map(fn (PlayerMatch $match): bool => $match->winning_side === $match->sideFor($user))->values();
        $wins = $results->filter()->count();
        $streakType = $results->first();
        $streak = 0;
        foreach ($results as $won) {
            if ($won !== $streakType) {
                break;
            }
            $streak++;
        }

        return [
            'played' => $results->count(),
            'wins' => $wins,
            'losses' => $results->count() - $wins,
            'win_rate' => $results->isEmpty() ? null : (int) round($wins / $results->count() * 100),
            'streak' => $streak,
            'streak_type' => $results->isEmpty() ? null : ($streakType ? 'won' : 'lost'),
            'logged' => $matches->count(),
        ];
    }
}
