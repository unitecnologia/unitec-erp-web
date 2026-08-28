<?php

namespace App\Http\Controllers\Erp;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Cookie;

/**
 * Abertura limpa após instalar/reinstalar: descarta cookies/sessão do navegador
 * (evita ERR_TOO_MANY_REDIRECTS por cookie de instalação anterior).
 */
class ErpBrowserResetController
{
    public function __invoke(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        if ($request->hasSession()) {
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        $loginUrl = url('/admin/login');
        $response = redirect()->to($loginUrl);

        foreach ($this->cookieNamesToForget() as $name) {
            $response->headers->setCookie($this->forgetCookie($name));
        }

        // Chrome/Edge: limpa cookies + storage da origem (PWA/localStorage).
        $response->headers->set('Clear-Site-Data', '"cookies", "storage"');
        $response->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate');

        return $response;
    }

    /**
     * @return list<string>
     */
    private function cookieNamesToForget(): array
    {
        return array_values(array_unique(array_filter([
            (string) config('session.cookie'),
            'XSRF-TOKEN',
            'erp_login_remember',
            'uni-sistemas-30-session',
            'unitec_erp_session',
            'laravel_session',
        ])));
    }

    private function forgetCookie(string $name): Cookie
    {
        return new Cookie(
            $name,
            null,
            1,
            (string) config('session.path', '/'),
            config('session.domain'),
            (bool) config('session.secure', false),
            true,
            false,
            (string) config('session.same_site', 'lax') ?: 'lax',
        );
    }
}
