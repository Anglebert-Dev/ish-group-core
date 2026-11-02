<?php

namespace App\Http\Controllers\Web\Appearance;

use App\Http\Controllers\Controller;
use App\Services\Branding\ThemeService;
use App\Support\Branding\ThemeTokens;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * A person's own light/dark/system choice, when their business allows it.
 * "business" clears it (back to the business's mode).
 */
class AppearanceController extends Controller
{
    public function update(Request $request, ThemeService $themes): RedirectResponse
    {
        $user = $request->user('staff');
        abort_unless($themes->allowsUserMode($user->business), 403);

        $choice = $request->validate(['appearance' => ['required', Rule::in([...ThemeTokens::MODES, 'business'])]])['appearance'];
        $old = $user->appearance;
        $user->appearance = $choice === 'business' ? null : $choice;
        $user->save();
        audit('appearance_changed', 'Appearance changed to '.$choice, $user, ['old' => $old, 'new' => $user->appearance], $user->business_id);

        return redirect()->back();
    }
}
