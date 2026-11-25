<?php

namespace Tests\Feature\Security;

use Tests\TestCase;

class SystemPulseSecurityTest extends TestCase
{
    private const TOKEN = 'pulse-token-that-is-at-least-32-chars';

    public function test_the_diagnostic_endpoint_is_disabled_by_default(): void
    {
        config([
            'services.system_pulse.enabled' => false,
            'services.system_pulse.token' => self::TOKEN,
        ]);

        $this->getJson('/api/v1/_pulse/'.self::TOKEN)->assertNotFound();
    }

    public function test_the_diagnostic_endpoint_rejects_an_invalid_token(): void
    {
        config([
            'services.system_pulse.enabled' => true,
            'services.system_pulse.token' => self::TOKEN,
        ]);

        $this->getJson('/api/v1/_pulse/pulse-token-that-is-at-least-32-wrong')
            ->assertNotFound();
    }

    public function test_the_diagnostic_endpoint_accepts_the_configured_token(): void
    {
        config([
            'services.system_pulse.enabled' => true,
            'services.system_pulse.token' => self::TOKEN,
        ]);

        $this->getJson('/api/v1/_pulse/'.self::TOKEN)
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonMissing(['exception']);
    }
}
