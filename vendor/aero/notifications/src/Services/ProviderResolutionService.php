<?php

declare(strict_types=1);

namespace Aero\Notifications\Services;

use Aero\Notifications\Contracts\MailContextResolver;
use Aero\Notifications\Contracts\SmsContextResolver;
use Aero\Notifications\Models\NotificationSetting;

/**
 * Resolves the EFFECTIVE mail/SMS provider configuration for the current
 * context, honouring provider inheritance:
 *
 *   1. Tenant override — NotificationSetting rows the tenant has set
 *      (mail_from_email/mail_host, sms_provider/sms_api_key/sms_from).
 *   2. Platform/deployment default — the injected MailContextResolver /
 *      SmsContextResolver bindings (aero-platform overrides these in SaaS;
 *      aero-notifications' own defaults apply in standalone).
 *
 * Real secrets (SMTP password, SMS API key, etc.) are NEVER returned — only
 * whether a secret is configured (bool). Callers needing to actually send
 * mail/SMS must use MailSenderInterface / SmsGatewayInterface, not this
 * service.
 */
class ProviderResolutionService
{
    public function __construct(
        private readonly MailContextResolver $mailContextResolver,
        private readonly SmsContextResolver $smsContextResolver,
    ) {}

    /**
     * @return array{configured: bool, source: string, driver: ?string, from_address: ?string, host: ?string}
     */
    public function mail(): array
    {
        $tenantFromAddress = $this->stringSetting('mail_from_email');
        $tenantHost = $this->stringSetting('mail_host');
        $tenantHasOverride = $tenantFromAddress !== null || $tenantHost !== null;

        $default = $this->mailContextResolver->resolve();

        return [
            'configured' => $tenantHasOverride ? true : (bool) ($default['configured'] ?? false),
            'source' => $tenantHasOverride ? 'tenant' : 'platform',
            'driver' => $default['driver'] ?? null,
            'from_address' => $tenantFromAddress ?? ($default['from_address'] ?? null),
            'host' => $tenantHost ?? ($default['host'] ?? null),
        ];
    }

    /**
     * @return array{configured: bool, source: string, provider: ?string, from: ?string, api_key_set: bool}
     */
    public function sms(): array
    {
        $tenantProvider = $this->stringSetting('sms_provider');
        $tenantFrom = $this->stringSetting('sms_from');
        $tenantApiKeySet = $this->stringSetting('sms_api_key') !== null;
        $tenantHasOverride = $tenantProvider !== null;

        $default = $this->smsContextResolver->resolve();

        return [
            'configured' => $tenantHasOverride ? $tenantApiKeySet : (bool) ($default['configured'] ?? false),
            'source' => $tenantHasOverride ? 'tenant' : 'platform',
            'provider' => $tenantProvider ?? ($default['provider'] ?? null),
            'from' => $tenantFrom,
            'api_key_set' => $tenantHasOverride ? $tenantApiKeySet : (bool) ($default['configured'] ?? false),
        ];
    }

    /**
     * Read a NotificationSetting value as a trimmed, non-empty string, or
     * null when the tenant has not configured it (unset/blank).
     */
    private function stringSetting(string $key): ?string
    {
        $value = NotificationSetting::getValue($key);

        if (! is_scalar($value)) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
