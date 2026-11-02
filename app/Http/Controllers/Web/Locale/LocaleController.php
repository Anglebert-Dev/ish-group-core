<?php

namespace App\Http\Controllers\Web\Locale;

use App\Http\Controllers\Controller;
use App\Support\Locale\Locales;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * The language switcher: saved on the signed-in user, or in the session for guests.
 */
class LocaleController extends Controller
{
    public function update(Request $request): RedirectResponse
    {
        $code = $request->validate(['locale' => ['required', Rule::in(array_keys(Locales::available()))]])['locale'];

        if ($user = $request->user('staff')) {
            $old = $user->locale;
            $user->locale = $code;
            $user->save();
            audit('locale_changed', "Language changed to {$code}", $user, ['old' => $old, 'new' => $code], $user->business_id);
        }

        $request->session()->put(Locales::SESSION, $code);

        return redirect()->back();
    }
}
