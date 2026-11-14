<?php

namespace App\Http\Concerns;

use App\Support\Recaptcha;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

trait ValidatesRecaptcha
{
    protected function rejectHoneypot(Request $request, string $panel = null): ?RedirectResponse
    {
        if (!$request->filled('website')) {
            return null;
        }

        return $this->recaptchaBack($request, ['email' => 'Permintaan ditolak.'], $panel);
    }

    /**
     * @param  array<string, string>  $extraErrors
     */
    protected function validateRecaptcha(Request $request, string $panel = null, array $extraErrors = []): ?RedirectResponse
    {
        if ($redirect = $this->rejectHoneypot($request, $panel)) {
            return $redirect;
        }

        if (!Recaptcha::isConfigured()) {
            return null;
        }

        $result = Recaptcha::verify(
            $request->input('g-recaptcha-response'),
            $request->ip()
        );

        if ($result['ok']) {
            return null;
        }

        $errors = array_merge(
            ['g-recaptcha-response' => $result['message']],
            $extraErrors
        );

        return $this->recaptchaBack($request, $errors, $panel);
    }

    /**
     * @param  array<string, string>  $errors
     */
    protected function recaptchaBack(Request $request, array $errors, ?string $panel): RedirectResponse
    {
        $redirect = back()->withErrors($errors)->withInput();

        if ($panel) {
            $redirect->with('auth_panel', $panel);
        }

        return $redirect;
    }
}
