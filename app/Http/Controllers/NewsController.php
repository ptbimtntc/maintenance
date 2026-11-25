<?php

namespace App\Http\Controllers;

use App\Enums\PermissionName;
use App\Enums\RoleName;
use App\Http\Requests\StoreNewsRequest;
use App\Models\News;
use App\Models\NewsAcknowledgement;
use App\Models\User;
use App\Concerns\PrunesNotificationLinks;
use App\Notifications\NewsPublished;
use App\Support\NewsImageProcessor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class NewsController extends Controller
{
    use PrunesNotificationLinks;

    public function index(): View
    {
        $news = News::query()
            ->with('createdBy')
            ->withCount([
                'acknowledgements',
                'acknowledgements as acknowledged_count' => fn ($q) => $q->whereNotNull('acknowledged_at'),
            ])
            ->orderByDesc('created_at')
            ->paginate(20);

        return view('news.index', [
            'newsItems' => $news,
        ]);
    }

    public function create(): View
    {
        return view('news.form', [
            'news' => null,
            'candidates' => $this->recipientCandidates(),
            'selectedRecipientIds' => [],
        ]);
    }

    public function store(StoreNewsRequest $request): RedirectResponse
    {
        $news = News::create([
            ...$request->safe()->except('image', 'recipient_ids'),
            'requires_acknowledgement' => $request->boolean('requires_acknowledgement'),
            'is_published' => $request->boolean('is_published', true),
            'created_by' => $request->user()->id,
        ]);

        if ($request->hasFile('image')) {
            $news->update(['image_path' => NewsImageProcessor::store($request->file('image'), 'news-images')]);
        }

        $this->syncRecipients($news, $request->input('recipient_ids', []));
        $this->notifyIfNewlyPublished($news, $request->user());

        return redirect()->route('news.index')->with('status', 'News created.');
    }

    public function show(News $news): View
    {
        $user = auth()->user();
        $canManage = $user->hasPermissionTo(PermissionName::ManageNews->value);
        $ownAcknowledgement = $news->requires_acknowledgement
            ? $news->acknowledgements()->where('user_id', $user->id)->first()
            : null;

        abort_unless(
            $news->is_published && ! $news->isExpired()
                && (! $news->requires_acknowledgement || $ownAcknowledgement !== null)
                || $canManage,
            404
        );

        return view('news.show', [
            'news' => $news,
            'ownAcknowledgement' => $ownAcknowledgement,
            // Same visibility rules as the dashboard carousel.
            'moreNews' => News::query()->published()->visibleTo($user)
                ->whereKeyNot($news->getKey())
                ->orderByDesc('created_at')
                ->take(3)
                ->get(),
            'acknowledgements' => $canManage && $news->requires_acknowledgement
                ? $news->acknowledgements()->with('user')->get()->sortBy(fn ($a) => $a->user->name)->values()
                : collect(),
        ]);
    }

    public function edit(News $news): View
    {
        return view('news.form', [
            'news' => $news,
            'candidates' => $this->recipientCandidates(),
            'selectedRecipientIds' => $news->acknowledgements()->pluck('user_id')->all(),
            'acknowledgedUserIds' => $news->acknowledgements()->whereNotNull('acknowledged_at')->pluck('user_id')->all(),
        ]);
    }

    public function update(StoreNewsRequest $request, News $news): RedirectResponse
    {
        $news->fill([
            ...$request->safe()->except('image', 'recipient_ids', 'requires_acknowledgement'),
            'is_published' => $request->boolean('is_published', true),
        ]);

        if ($request->hasFile('image')) {
            if ($news->image_path) {
                Storage::disk('public')->delete($news->image_path);
            }

            $news->image_path = NewsImageProcessor::store($request->file('image'), 'news-images');
        }

        $news->save();

        if ($news->requires_acknowledgement) {
            $this->syncRecipients($news, $request->input('recipient_ids', []));
        }

        $this->notifyIfNewlyPublished($news, $request->user());

        return redirect()->route('news.index')->with('status', 'News updated.');
    }

    /**
     * Records that the signed-in recipient has read and understood the news.
     * The first confirmation time is kept - confirming again changes nothing.
     */
    public function acknowledge(News $news): RedirectResponse
    {
        $acknowledgement = $news->acknowledgements()->where('user_id', auth()->id())->first();

        abort_unless(
            $news->requires_acknowledgement && $news->is_published && ! $news->isExpired() && $acknowledgement !== null,
            404
        );

        if ($acknowledgement->acknowledged_at === null) {
            $acknowledgement->forceFill(['acknowledged_at' => now()])->save();
        }

        return redirect()->route('news.show', $news)->with('status', 'Thank you - your confirmation has been recorded.');
    }

    /**
     * Notify once, the moment a news item first becomes visible on the
     * dashboard - not on every save, so fixing a typo on an already-published
     * item doesn't spam everyone again. Acknowledgement news only goes to its
     * recipients (including ones added later, each notified once).
     */
    private function notifyIfNewlyPublished(News $news, User $author): void
    {
        if (! $news->is_published) {
            return;
        }

        if ($news->requires_acknowledgement) {
            $pending = $news->acknowledgements()->whereNull('notified_at')->where('user_id', '!=', $author->id)->with('user')->get();
            $pending->each(fn (NewsAcknowledgement $a) => $a->user->notify(new NewsPublished($news)));
            $news->acknowledgements()->whereNull('notified_at')->update(['notified_at' => now()]);

            return;
        }

        if ($news->notified_at !== null) {
            return;
        }

        User::where('id', '!=', $author->id)->get()->each->notify(new NewsPublished($news));

        $news->forceFill(['notified_at' => now()])->save();
    }

    /**
     * Makes the recipient list match $userIds, except that anyone who has
     * already confirmed is never removed - that record must not disappear.
     *
     * @param  array<int, int|string>  $userIds
     */
    private function syncRecipients(News $news, array $userIds): void
    {
        $wanted = User::whereIn('id', $userIds)->withoutRole(RoleName::Guest->value)->pluck('id');

        $news->acknowledgements()
            ->whereNull('acknowledged_at')
            ->whereNotIn('user_id', $wanted)
            ->delete();

        $existing = $news->acknowledgements()->pluck('user_id');

        foreach ($wanted->diff($existing) as $userId) {
            $news->acknowledgements()->create(['user_id' => $userId]);
        }
    }

    /** Login accounts that can be addressed - the read-only Guest account can't confirm anything. */
    private function recipientCandidates()
    {
        return User::query()->withoutRole(RoleName::Guest->value)->orderBy('name')->get(['id', 'name', 'email']);
    }

    public function destroy(News $news): RedirectResponse
    {
        if ($news->image_path) {
            Storage::disk('public')->delete($news->image_path);
        }

        $this->pruneNotificationsLinkedTo(route('news.show', $news));

        $news->delete();

        return redirect()->route('news.index')->with('status', 'News deleted.');
    }
}
