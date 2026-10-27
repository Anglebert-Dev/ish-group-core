@php $open = session(\App\Http\Middleware\ConfirmStaffPassword::MODAL_FLAG) || $errors->has('password_confirm'); @endphp

{{-- Password pop-up for protected actions. Opens when ConfirmStaffPassword
     sent the user back here; on success the original action is re-submitted. --}}
@auth('staff')
<div id="password-confirm-modal" class="fixed inset-0 z-[70] {{ $open ? '' : 'hidden' }}" role="dialog" aria-modal="true" aria-labelledby="password-confirm-title">
    <div class="absolute inset-0 bg-ink/50" data-password-confirm-close></div>
    <div class="relative flex min-h-full items-center justify-center p-4">
        <form action="{{ route('staff.password.confirm.store') }}" method="POST" class="w-full max-w-md bg-white rounded-2xl shadow-xl p-6 sm:p-8" data-password-confirm-form>
            @csrf
            <div class="h-11 w-11 rounded-xl bg-brand-light text-brand flex items-center justify-center mb-4">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
            </div>
            <h2 id="password-confirm-title" class="font-display font-bold text-lg text-ink">{{ __('body.confirm_password_title') }}</h2>
            <p class="text-sm text-muted mt-1 mb-5">{{ __('body.confirm_password_subtitle') }}</p>

            <x-common.input type="password" name="password" id="password-confirm-input" :label="__('body.password')" required autocomplete="current-password"
                :error="$errors->first('password')" />
            <p class="text-xs text-danger mt-2 hidden" data-password-confirm-error role="alert"></p>

            <div class="flex flex-col-reverse sm:flex-row gap-2 sm:justify-end mt-6">
                <x-common.button type="button" variant="secondary" data-password-confirm-close>{{ __('body.cancel') }}</x-common.button>
                <x-common.button type="submit" data-password-confirm-submit>{{ __('body.confirm_password_action') }}</x-common.button>
            </div>
            <p class="text-xs text-muted mt-5">{{ __('body.confirm_password_note', ['hours' => max(1, round(config('auth.password_timeout', 10800) / 3600))]) }}</p>
        </form>
    </div>
</div>

<script>
    (function () {
        var modal = document.getElementById('password-confirm-modal');
        if (!modal) return;
        var form = modal.querySelector('[data-password-confirm-form]');
        var input = modal.querySelector('#password-confirm-input');
        var error = modal.querySelector('[data-password-confirm-error]');
        var submit = modal.querySelector('[data-password-confirm-submit]');

        function close() { modal.classList.add('hidden'); }
        modal.querySelectorAll('[data-password-confirm-close]').forEach(function (el) { el.addEventListener('click', close); });
        document.addEventListener('keydown', function (e) { if (e.key === 'Escape') close(); });
        if (!modal.classList.contains('hidden')) setTimeout(function () { input && input.focus(); }, 50);

        // Re-submit the remembered action as a normal form post.
        function resume(action) {
            var f = document.createElement('form');
            f.method = 'POST';
            f.action = action.url;
            var add = function (name, value) {
                var i = document.createElement('input');
                i.type = 'hidden'; i.name = name; i.value = value; f.appendChild(i);
            };
            add('_token', form.querySelector('input[name=_token]').value);
            if (action.method.toUpperCase() !== 'POST') add('_method', action.method);
            action.fields.forEach(function (pair) { add(pair[0], pair[1]); });
            document.body.appendChild(f);
            f.submit();
        }

        form.addEventListener('submit', function (e) {
            if (!window.fetch) return; // plain form post still works
            e.preventDefault();
            submit.disabled = true;
            error.classList.add('hidden');

            fetch(form.action, {
                method: 'POST',
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                body: new FormData(form),
                credentials: 'same-origin'
            }).then(function (response) {
                return response.json().then(function (data) { return { ok: response.ok, data: data }; });
            }).then(function (result) {
                if (!result.ok) {
                    error.textContent = (result.data && result.data.message) || 'Error';
                    error.classList.remove('hidden');
                    submit.disabled = false;
                    input.select();
                    return;
                }
                if (result.data.action) return resume(result.data.action);
                if (result.data.redirect) return window.location.assign(result.data.redirect);
                window.location.reload();
            }).catch(function () { form.submit(); });
        });
    })();
</script>
@endauth
