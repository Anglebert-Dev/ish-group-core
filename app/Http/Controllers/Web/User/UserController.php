<?php

namespace App\Http\Controllers\Web\User;

use App\Enums\UserStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\User\StoreUserRequest;
use App\Http\Requests\User\UpdateUserRequest;
use App\Http\Requests\User\UpdateUserStatusRequest;
use App\Models\User;
use App\Services\User\UserService;
use App\Libraries\ListExportLibrary;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class UserController extends Controller
{
    public function __construct(protected UserService $users)
    {
    }

    public function index(): View
    {
        return view('app.users.index', [
            'users' => $this->users->list(Auth::guard('staff')->user()),
        ]);
    }

    public function exportPdf(): Response
    {
        return ListExportLibrary::pdf($this->users->exportList(Auth::guard('staff')->user()));
    }

    public function exportExcel(): Response
    {
        return ListExportLibrary::excel($this->users->exportList(Auth::guard('staff')->user()));
    }

    public function create(): View
    {
        return view('app.users.create', [
            'roles' => $this->users->assignableRoles(Auth::guard('staff')->user()),
        ]);
    }

    public function store(StoreUserRequest $request): RedirectResponse
    {
        $result = $this->users->create(Auth::guard('staff')->user(), $request->validated());

        return redirect()->route('users.index')->with(
            $result['status'] ? 'success' : 'error',
            $result['message']
        );
    }

    public function show(User $user): View
    {
        return view('app.users.show', [
            'user' => $user->load('roles'),
        ]);
    }

    public function edit(User $user): View
    {
        return view('app.users.edit', [
            'user' => $user,
            'roles' => $this->users->assignableRoles(Auth::guard('staff')->user()),
            'currentRole' => $user->roles->first()?->id,
        ]);
    }

    public function update(UpdateUserRequest $request, User $user): RedirectResponse
    {
        $result = $this->users->update(Auth::guard('staff')->user(), $user, $request->validated());

        return redirect()->route('users.index')->with(
            $result['status'] ? 'success' : 'error',
            $result['message']
        );
    }

    public function updateStatus(UpdateUserStatusRequest $request, User $user): RedirectResponse
    {
        $status = UserStatus::from($request->validated('status'));

        $result = $this->users->updateStatus(Auth::guard('staff')->user(), $user, $status);

        return back()->with($result['status'] ? 'success' : 'error', $result['message']);
    }
}
