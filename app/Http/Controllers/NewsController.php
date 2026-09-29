<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreNewsRequest;
use App\Models\News;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class NewsController extends Controller
{
    public function index(): View
    {
        $news = News::query()
            ->with('createdBy')
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
        ]);
    }

    public function store(StoreNewsRequest $request): RedirectResponse
    {
        $news = News::create([
            ...$request->safe()->except('image'),
            'is_published' => $request->boolean('is_published', true),
            'created_by' => $request->user()->id,
        ]);

        if ($request->hasFile('image')) {
            $news->update(['image_path' => $request->file('image')->store('news-images', 'public')]);
        }

        return redirect()->route('news.index')->with('status', 'News created.');
    }

    public function show(News $news): View
    {
        abort_unless(
            $news->is_published && (!$news->expires_at || !$news->expires_at->isPast())
                || auth()->user()->hasPermissionTo(\App\Enums\PermissionName::ManageNews->value),
            404
        );

        return view('news.show', [
            'news' => $news,
        ]);
    }

    public function edit(News $news): View
    {
        return view('news.form', [
            'news' => $news,
        ]);
    }

    public function update(StoreNewsRequest $request, News $news): RedirectResponse
    {
        $news->fill([
            ...$request->safe()->except('image'),
            'is_published' => $request->boolean('is_published', true),
        ]);

        if ($request->hasFile('image')) {
            if ($news->image_path) {
                Storage::disk('public')->delete($news->image_path);
            }

            $news->image_path = $request->file('image')->store('news-images', 'public');
        }

        $news->save();

        return redirect()->route('news.index')->with('status', 'News updated.');
    }

    public function destroy(News $news): RedirectResponse
    {
        if ($news->image_path) {
            Storage::disk('public')->delete($news->image_path);
        }

        $news->delete();

        return redirect()->route('news.index')->with('status', 'News deleted.');
    }
}
