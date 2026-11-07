<?php

namespace App\Models\Concerns;

use SensitiveParameter;

/**
 * Filament app authentication (TOTP) + recovery codes for staff. Columns from migration 2026_11_07_270004:
 * app_authentication_secret (encrypted), app_authentication_recovery_codes (encrypted array).
 */
trait HasAdminTwoFactor
{
    public function initializeHasAdminTwoFactor(): void
    {
        $this->mergeCasts([
            'app_authentication_secret' => 'encrypted',
            'app_authentication_recovery_codes' => 'encrypted:array',
            'password_changed_at' => 'datetime',
        ]);
        $this->makeHidden(['app_authentication_secret', 'app_authentication_recovery_codes']);
    }

    public function getAppAuthenticationSecret(): ?string
    {
        return $this->app_authentication_secret;
    }

    public function saveAppAuthenticationSecret(#[SensitiveParameter] ?string $secret): void
    {
        $this->forceFill(['app_authentication_secret' => $secret])->save();
    }

    public function getAppAuthenticationHolderName(): string
    {
        return (string) ($this->email ?: $this->phone ?: $this->name);
    }

    /** @return ?array<string> */
    public function getAppAuthenticationRecoveryCodes(): ?array
    {
        return $this->app_authentication_recovery_codes;
    }

    /** @param  ?array<string>  $codes */
    public function saveAppAuthenticationRecoveryCodes(#[SensitiveParameter] ?array $codes): void
    {
        $this->forceFill(['app_authentication_recovery_codes' => $codes])->save();
    }
}
