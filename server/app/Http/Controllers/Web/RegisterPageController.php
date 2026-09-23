<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\RegisterRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class RegisterPageController extends Controller
{
    public function create(): View
    {
        return view('auth.register');
    }

    public function store(RegisterRequest $request): RedirectResponse
    {
        $user = User::create([
            ...$request->safe()->only(['name', 'email', 'password']),
            'plan' => config('snapask.plans.default.name'),
            'monthly_ask_limit' => config('snapask.plans.default.monthly_ask_limit'),
        ]);

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('web.connectors.index');
    }
}
