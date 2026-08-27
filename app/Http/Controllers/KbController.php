<?php

namespace App\Http\Controllers;

use App\Models\KbArticle;
use App\Models\KbCategory;
use App\Rules\TenantScoped;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;

class KbController extends Controller
{
    // Admin: list all articles
    public function index()
    {
        return Inertia::render('KB/Index', [
            'articles' => KbArticle::with('category:id,name', 'author:id,name')
                ->orderBy('updated_at', 'desc')->get(),
            'categories' => KbCategory::orderBy('sort_order')->get(),
        ]);
    }

    // Admin: create/edit article
    public function show(KbArticle $kbArticle)
    {
        return Inertia::render('KB/Edit', [
            'article' => $kbArticle,
            'categories' => KbCategory::orderBy('sort_order')->get(),
        ]);
    }

    public function create()
    {
        return Inertia::render('KB/Edit', [
            'article' => null,
            'categories' => KbCategory::orderBy('sort_order')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'category_id' => ['required', TenantScoped::exists('kb_categories')],
            'excerpt' => 'nullable|string|max:500',
            'body' => 'required|string',
            'status' => 'in:draft,published',
        ]);

        $validated['slug'] = Str::slug($validated['title']);
        $validated['author_id'] = $request->user()->id;

        if (empty($validated['excerpt'])) {
            $validated['excerpt'] = Str::limit(strip_tags($validated['body']), 200);
        }

        if ($validated['status'] === 'published') {
            $validated['published_at'] = now();
        }

        $article = KbArticle::create($validated);

        return redirect()->route('kb.admin.index')
            ->with('success', 'Article created.');
    }

    public function update(Request $request, KbArticle $kbArticle)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'category_id' => ['required', TenantScoped::exists('kb_categories')],
            'excerpt' => 'nullable|string|max:500',
            'body' => 'required|string',
            'status' => 'in:draft,published',
        ]);

        $validated['slug'] = Str::slug($validated['title']);

        if (empty($validated['excerpt'])) {
            $validated['excerpt'] = Str::limit(strip_tags($validated['body']), 200);
        }

        if ($validated['status'] === 'published' && ! $kbArticle->published_at) {
            $validated['published_at'] = now();
        }

        $kbArticle->update($validated);

        return redirect()->route('kb.admin.index')
            ->with('success', 'Article updated.');
    }

    public function destroy(KbArticle $kbArticle)
    {
        $kbArticle->delete();

        return back()->with('success', 'Article deleted.');
    }

    // Admin: categories CRUD
    public function storeCategory(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'icon' => 'nullable|string|max:50',
        ]);

        $baseSlug = Str::slug($validated['name']);
        $slug = $baseSlug;
        $i = 2;
        while (KbCategory::where('slug', $slug)->exists()) {
            $slug = "{$baseSlug}-{$i}";
            $i++;
        }
        $validated['slug'] = $slug;

        KbCategory::create($validated);

        return back()->with('success', 'Category created.');
    }

    public function updateCategory(Request $request, KbCategory $category)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'icon' => 'nullable|string|max:50',
        ]);

        $validated['slug'] = Str::slug($validated['name']);

        $category->update($validated);

        return back()->with('success', 'Category updated.');
    }

    public function destroyCategory(KbCategory $category)
    {
        $category->delete();

        return back()->with('success', 'Category deleted.');
    }

    // Public: knowledge base portal
    public function portal()
    {
        return Inertia::render('Public/KB/Portal', [
            'categories' => KbCategory::withCount('publishedArticles')
                ->orderBy('sort_order')->get(),
        ]);
    }

    public function portalCategory(KbCategory $category)
    {
        return Inertia::render('Public/KB/Category', [
            'category' => $category->load('publishedArticles'),
        ]);
    }

    public function portalArticle(KbCategory $category, KbArticle $article)
    {
        $article->increment('views');

        return Inertia::render('Public/KB/Article', [
            'category' => $category,
            'article' => $article->load('author:id,name'),
        ]);
    }

    public function search(Request $request)
    {
        $query = $request->input('q', '');

        $articles = KbArticle::where('status', 'published')
            ->where(function ($q) use ($query) {
                $q->where('title', 'like', "%{$query}%")
                    ->orWhere('body', 'like', "%{$query}%");
            })
            ->with('category:id,name,slug')
            ->take(20)
            ->get(['id', 'title', 'slug', 'excerpt', 'category_id']);

        return Inertia::render('Public/KB/Search', [
            'query' => $query,
            'articles' => $articles,
        ]);
    }
}
