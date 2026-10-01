<?php

namespace App\Http\Responses;

use Filament\Auth\Http\Responses\Contracts\LoginResponse as LoginResponseContract;
use Filament\Facades\Filament;
use Illuminate\Http\RedirectResponse;
use Livewire\Features\SupportRedirects\Redirector;
use App\Support\Erp\RequestOrigin;

class LoginResponse implements LoginResponseContract
{
    public function toResponse($request): RedirectResponse | Redirector
    {
        $intended = (string) $request->session()->pull('url.intended', '');
        $target = RequestOrigin::toBrowserUrl($intended !== '' ? $intended : (string) Filament::getUrl());

        return redirect()
            ->away($target)
            ->setStatusCode(303);
    }
}
