<?php

namespace Modules\Savings\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Modules\Savings\Models\Category;

class CategoryController extends Controller
{
    public const STARTER = [
        'income' => ['Salary', 'Business income', 'Interest earned', 'Gifts received', 'Other income'],
        'expense' => ['Rent', 'Food & groceries', 'Transport', 'Utilities', 'Internet & phone', 'Health', 'School fees', 'Family support', 'Contributions', 'Entertainment', 'Clothing', 'Bank & mobile fees', 'Interest paid', 'Other'],
    ];

    public function index(): View
    {
        return view('savings::categories.index', [
            'categories' => Category::query()->with('parent')->orderBy('kind')->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        (new Category($this->validated($request)))->save();

        return back()->with('success', __('savings::savings.category_saved'));
    }

    public function update(Request $request, Category $category): RedirectResponse
    {
        // Renaming keeps history (lines point to the category, not its name).
        $category->fill($this->validated($request, $category) + ['is_archived' => $request->boolean('is_archived')])->save();

        return back()->with('success', __('savings::savings.category_saved'));
    }

    public function starter(): RedirectResponse
    {
        foreach (self::STARTER as $kind => $names) {
            foreach ($names as $name) {
                Category::query()->firstOrCreate(['name' => $name, 'kind' => $kind]);
            }
        }

        return back()->with('success', __('savings::savings.starter_done'));
    }

    private function validated(Request $request, ?Category $category = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'kind' => ['required', Rule::in(['income', 'expense'])],
            'parent' => ['nullable', 'string'],
            'color' => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
        ]);
        $parent = filled($data['parent'] ?? null) ? Category::query()->where('uuid', $data['parent'])->where('kind', $data['kind'])->whereNull('parent_id')->first() : null;

        return ['name' => $data['name'], 'kind' => $data['kind'], 'parent_id' => $parent?->id !== $category?->id ? $parent?->id : null, 'color' => $data['color'] ?? null];
    }
}
