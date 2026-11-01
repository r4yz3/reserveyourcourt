<?php

namespace App\Models;

use Database\Factories\ClubFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['owner_id', 'name', 'description', 'country', 'city', 'facebook_url', 'website_url', 'logo_path', 'cover_path', 'media_disk', 'is_public', 'auto_approve', 'members_can_post', 'invite_token'])]
#[Hidden(['invite_token'])]
class Club extends Model
{
    /** @use HasFactory<ClubFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return ['is_public' => 'boolean', 'auto_approve' => 'boolean', 'members_can_post' => 'boolean'];
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(ClubMember::class);
    }

    public function activeMemberships(): HasMany
    {
        return $this->memberships()->where('status', 'active');
    }

    public function posts(): HasMany
    {
        return $this->hasMany(ClubPost::class);
    }

    public function openPlays(): HasMany
    {
        return $this->hasMany(OpenPlay::class);
    }

    public function membershipFor(?User $user): ?ClubMember
    {
        return $user ? $this->memberships()->where('user_id', $user->id)->first() : null;
    }

    public function isMember(?User $user): bool
    {
        return $user !== null && $this->activeMemberships()->where('user_id', $user->id)->exists();
    }

    /**
     * Owners and moderators run the club: approvals, roles, pinned posts and club-only open play.
     */
    public function isManager(?User $user): bool
    {
        return $user !== null && $this->activeMemberships()->where('user_id', $user->id)->whereIn('role', ['owner', 'moderator'])->exists();
    }

    public function canPost(?User $user): bool
    {
        return $this->members_can_post ? $this->isMember($user) : $this->isManager($user);
    }

    /**
     * Private clubs are only visible to members and to people holding the invite link.
     */
    public function isVisibleTo(?User $user): bool
    {
        return $this->is_public || $this->isMember($user) || ($user !== null && $this->memberships()->where('user_id', $user->id)->exists());
    }

    public function locationLabel(): ?string
    {
        return collect([$this->city, $this->country])->filter()->join(', ') ?: null;
    }

    public function initials(): string
    {
        return collect(preg_split('/\s+/', trim($this->name)))->filter()->take(2)->map(fn (string $word): string => mb_strtoupper(mb_substr($word, 0, 1)))->join('');
    }
}
