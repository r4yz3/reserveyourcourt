@extends('layouts.public')
@section('title',$club->name)
@if(! $club->is_public)
@section('private','true')
@endif
@section('content')
<div class="public-container player-area">
    <a class="back-link" href="{{ route('clubs.index') }}"><x-icon name="left" size="14" />Clubs</a>

    <section class="club-header">
        <div class="club-cover {{ $club->cover_path ? '' : 'no-cover' }}">@if($club->cover_path)<img src="{{ route('clubs.media', ['club' => $club, 'kind' => 'cover']) }}?v={{ substr(md5($club->cover_path), 0, 8) }}" alt="">@endif</div>
        <div class="club-header-body">
            <x-club-logo :club="$club" :size="96" class="club-header-logo" />
            <div class="club-header-identity">
                <h1>{{ $club->name }}</h1>
                <p class="club-header-meta">
                    @if($club->locationLabel())<span><x-icon name="pin" size="14" />{{ $club->locationLabel() }}</span>@endif
                    <span><x-icon name="users" size="14" />{{ $club->active_memberships_count }} {{ $club->active_memberships_count === 1 ? 'member' : 'members' }}</span>
                    <span class="event-chip">{{ $club->is_public ? 'Public' : 'Private' }}</span>
                </p>
            </div>
            <div class="club-header-actions">
                @if($isManager)<a class="button secondary compact" href="{{ route('clubs.edit', $club) }}"><x-icon name="settings" size="14" />Settings</a>@endif
                @if($isMember)
                    @if($membership->role !== 'owner')<form method="post" action="{{ route('clubs.leave', $club) }}" data-confirm="Leave {{ $club->name }}?">@csrf<button class="button secondary compact">Leave club</button></form>@endif
                    <span class="status confirmed">{{ ['owner' => 'Owner', 'moderator' => 'Moderator', 'member' => 'Member'][$membership->role] }}</span>
                @elseif($membership?->status === 'pending')
                    <span class="status pending">Request pending</span>
                    <form method="post" action="{{ route('clubs.leave', $club) }}">@csrf<button class="text-button small">Withdraw</button></form>
                @elseif(! auth()->check())
                    <a class="button" href="{{ route('login') }}">Sign in to join</a>
                @elseif($club->is_public)
                    <form method="post" action="{{ route('clubs.join', $club) }}">@csrf<button class="button">{{ $club->auto_approve ? 'Join club' : 'Request to join' }}</button></form>
                @endif
            </div>
        </div>
        <nav class="club-tabs" aria-label="Club sections">
            @foreach(['feed' => ['Feed', 'list'], 'about' => ['About', 'eye'], 'members' => ['Members', 'users']] as $key => [$label, $icon])
                <a href="{{ route('clubs.show', ['club' => $club, 'tab' => $key]) }}" @if($tab === $key) aria-current="page" @endif><x-icon :name="$icon" size="16" />{{ $label }}@if($key === 'members' && $pending->isNotEmpty())<span class="tab-badge">{{ $pending->count() }}</span>@endif</a>
            @endforeach
        </nav>
    </section>

    @if($tab === 'feed')
        <div class="club-layout">
            <div>
                @if($isMember)
                    @if($canPost)
                        <form method="post" action="{{ route('clubs.posts.store', $club) }}" class="player-panel club-composer">@csrf
                            <x-player-avatar :user="auth()->user()" :size="40" />
                            <div>
                                <label for="body" class="sr-only">Write a post</label>
                                <textarea id="body" name="body" rows="3" maxlength="2000" required placeholder="Share an update with the club…">{{ old('body') }}</textarea>
                                @error('body')<p class="field-error">{{ $message }}</p>@enderror
                                <div class="composer-actions"><span class="small muted">Visible to club members only.</span><button class="button compact">Post</button></div>
                            </div>
                        </form>
                    @endif
                    @forelse($posts as $post)
                        <article class="player-panel club-post {{ $post->is_pinned ? 'pinned' : '' }}">
                            <header>
                                <x-player-avatar :user="$post->author" :size="38" />
                                <div><strong>{{ $post->author->displayName() }}</strong><span class="small muted">{{ $post->created_at->setTimezone('Asia/Manila')->format('j M Y · g:i A') }}</span></div>
                                @if($post->is_pinned)<span class="event-chip">Pinned</span>@endif
                            </header>
                            <div class="club-post-body">{!! nl2br(e($post->body)) !!}</div>
                            @if($isManager || $post->user_id === auth()->id())
                                <footer>
                                    @if($isManager)<form method="post" action="{{ route('clubs.posts.pin', ['club' => $club, 'post' => $post]) }}">@csrf @method('patch')<button class="text-button small">{{ $post->is_pinned ? 'Unpin' : 'Pin to top' }}</button></form>@endif
                                    <form method="post" action="{{ route('clubs.posts.destroy', ['club' => $club, 'post' => $post]) }}" data-confirm="Delete this post?">@csrf @method('delete')<button class="text-button small">Delete</button></form>
                                </footer>
                            @endif
                        </article>
                    @empty
                        <div class="player-panel player-empty"><x-icon name="list" size="24" /><h3>No posts yet.</h3><p>{{ $canPost ? 'Start the conversation with your first update.' : 'Club managers will post updates here.' }}</p></div>
                    @endforelse
                    @if($posts->hasPages())<nav class="simple-pagination" aria-label="Feed pages">@if($posts->previousPageUrl())<a class="button secondary" href="{{ $posts->previousPageUrl() }}">Newer</a>@endif<span>Page {{ $posts->currentPage() }} of {{ $posts->lastPage() }}</span>@if($posts->nextPageUrl())<a class="button secondary" href="{{ $posts->nextPageUrl() }}">Older</a>@endif</nav>@endif
                @else
                    <div class="player-panel player-empty locked-panel"><x-icon name="shield" size="24" /><h3>The feed is for members.</h3><p>Join {{ $club->name }} to read and post updates.</p></div>
                @endif
            </div>
            <aside>
                <section class="player-panel" aria-labelledby="club-open-play">
                    <div class="section-heading"><h2 id="club-open-play">Open play</h2>@if($isManager)<a class="text-link" href="{{ route('play.events.create', ['club' => $club->id]) }}">Host<x-icon name="plus" size="14" /></a>@endif</div>
                    @forelse($openPlays as $event)
                        <a class="event-line" href="{{ route('open-play.show', $event) }}"><span class="next-game-date"><strong>{{ $event->starts_at->setTimezone('Asia/Manila')->format('j') }}</strong>{{ $event->starts_at->setTimezone('Asia/Manila')->format('M') }}</span><span><strong>{{ $event->title }}</strong><span class="small muted">{{ $event->starts_at->setTimezone('Asia/Manila')->format('D · g:i A') }} · {{ $event->joined_count }}/{{ $event->capacity }} joined</span></span></a>
                    @empty
                        <p class="small muted">No upcoming sessions.</p>
                    @endforelse
                </section>
            </aside>
        </div>
    @elseif($tab === 'about')
        <section class="player-panel club-about">
            <h2>About</h2>
            @if($club->description)<div class="club-description">{!! nl2br(e($club->description)) !!}</div>@else<p class="muted">This club has not added a description yet.</p>@endif
            <dl class="event-facts club-facts">
                <div><dt>Owner</dt><dd>{{ $club->owner->displayName() }}</dd></div>
                <div><dt>Location</dt><dd>{{ $club->locationLabel() ?? 'Not set' }}</dd></div>
                <div><dt>Joining</dt><dd>{{ ! $club->is_public ? 'Invite only' : ($club->auto_approve ? 'Open' : 'By request') }}</dd></div>
                <div><dt>Since</dt><dd>{{ $club->created_at->format('M Y') }}</dd></div>
            </dl>
            @if($club->facebook_url || $club->website_url)
                <div class="club-links">
                    @if($club->facebook_url)<a class="button secondary" href="{{ $club->facebook_url }}" target="_blank" rel="noopener noreferrer nofollow">Facebook page<x-icon name="arrow" size="14" /></a>@endif
                    @if($club->website_url)<a class="button secondary" href="{{ $club->website_url }}" target="_blank" rel="noopener noreferrer nofollow">Website<x-icon name="arrow" size="14" /></a>@endif
                </div>
            @endif
        </section>
    @else
        @if($pending->isNotEmpty())
            <section class="player-panel review-callout" aria-labelledby="requests-heading">
                <div class="section-heading"><h2 id="requests-heading">Join requests</h2><span class="small muted">{{ $pending->count() }} waiting</span></div>
                @foreach($pending as $request)
                    <div class="member-row">
                        <x-player-avatar :user="$request->user" :size="38" />
                        <span class="member-name"><strong>{{ $request->user->displayName() }}</strong><small>Requested {{ $request->created_at->diffForHumans() }}</small></span>
                        <div class="member-actions">
                            <form method="post" action="{{ route('clubs.members.update', ['club' => $club, 'member' => $request]) }}">@csrf @method('patch')<input type="hidden" name="action" value="approve"><button class="button compact">Approve</button></form>
                            <form method="post" action="{{ route('clubs.members.update', ['club' => $club, 'member' => $request]) }}">@csrf @method('patch')<input type="hidden" name="action" value="decline"><button class="button secondary compact">Decline</button></form>
                        </div>
                    </div>
                @endforeach
            </section>
        @endif

        @if($isMember)
            <section class="player-panel" aria-labelledby="members-heading">
                <div class="section-heading"><h2 id="members-heading">Members <span class="subtle-count">{{ $members->count() }}</span></h2></div>
                @foreach($members as $member)
                    <div class="member-row">
                        <x-player-avatar :user="$member->user" :size="38" />
                        <span class="member-name">@if($member->user->show_profile)<a href="{{ route('players.show', $member->user) }}">{{ $member->user->displayName() }}</a>@else<strong>{{ $member->user->displayName() }}</strong>@endif<small>Joined {{ $member->created_at->format('M Y') }}</small></span>
                        @if($member->user->show_rating)<span class="rating-chip small">{{ $member->user->ratingBadge() }}</span>@endif
                        @if($member->role !== 'member')<span class="event-chip">{{ ucfirst($member->role) }}</span>@endif
                        @if($isManager && $member->role !== 'owner' && $member->user_id !== auth()->id())
                            <div class="member-actions">
                                @if($club->owner_id === auth()->id())
                                    <form method="post" action="{{ route('clubs.members.update', ['club' => $club, 'member' => $member]) }}">@csrf @method('patch')<input type="hidden" name="action" value="{{ $member->role === 'moderator' ? 'demote' : 'promote' }}"><button class="text-button small">{{ $member->role === 'moderator' ? 'Remove moderator' : 'Make moderator' }}</button></form>
                                @endif
                                @if($member->role === 'member' || $club->owner_id === auth()->id())
                                    <form method="post" action="{{ route('clubs.members.update', ['club' => $club, 'member' => $member]) }}" data-confirm="Remove {{ $member->user->displayName() }} from the club?">@csrf @method('patch')<input type="hidden" name="action" value="remove"><button class="text-button small danger-text">Remove</button></form>
                                @endif
                            </div>
                        @endif
                    </div>
                @endforeach
            </section>

            <section class="player-panel invite-panel" aria-labelledby="invite-heading">
                <div class="panel-intro"><h2 id="invite-heading"><x-icon name="share" size="16" />Invite link</h2><p>Anyone with this link can join right away, even if the club is private or needs approval.</p></div>
                <div class="share-row"><input id="invite-link" value="{{ $inviteUrl }}" readonly aria-label="Club invite link"><button type="button" class="button secondary compact" data-copy="#invite-link">Copy</button><button type="button" class="button compact" data-share-url="{{ $inviteUrl }}" data-share-title="Join {{ $club->name }}" hidden><x-icon name="share" size="14" />Share</button></div>
                @if($isManager)<form method="post" action="{{ route('clubs.invite.reset', $club) }}" data-confirm="Create a new invite link? The current link will stop working.">@csrf<button class="text-button small">Reset link</button></form>@endif
            </section>
        @else
            <div class="player-panel player-empty locked-panel"><x-icon name="users" size="24" /><h3>Members are visible to members.</h3><p>Join {{ $club->name }} to see who plays here.</p></div>
        @endif
    @endif
</div>
@endsection
