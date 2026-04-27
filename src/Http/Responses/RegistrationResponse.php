<?php

declare(strict_types=1);

namespace Tallcms\FilamentRegistration\Http\Responses;

use Filament\Auth\Http\Responses\Contracts\RegistrationResponse as RegistrationResponseContract;
use Filament\Facades\Filament;
use Illuminate\Http\RedirectResponse;

/**
 * Default post-registration redirect: panel home (or the intended URL if one
 * was set before the user landed on the register page).
 *
 * Hosts override this by binding `Filament\Auth\Http\Responses\Contracts\RegistrationResponse`
 * to their own concrete in a service provider.
 *
 * Note: we construct `Illuminate\Http\RedirectResponse` directly rather than
 * calling `redirect(...)`. Inside a Livewire component (which Filament's
 * Register page is), the `redirect()` helper returns Livewire's
 * `SupportRedirects\Redirector` wrapper, which is not a Symfony Response
 * and would fail Filament's `Responsable::toResponse()` contract.
 */
class RegistrationResponse implements RegistrationResponseContract
{
    public function toResponse($request): RedirectResponse
    {
        $intended = $request->session()?->pull('url.intended');

        return new RedirectResponse($intended ?: Filament::getUrl());
    }
}
