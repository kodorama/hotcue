<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class ObsAuthTest extends TestCase
{
    /**
     * Compute the OBS v5 auth response.
     * Formula: base64(sha256(base64(sha256(password + salt)) + challenge))
     */
    private function computeAuth(string $password, string $challenge, string $salt): string
    {
        $secret = base64_encode(hash('sha256', $password . $salt, true));
        return base64_encode(hash('sha256', $secret . $challenge, true));
    }

    public function test_computes_obs_v5_auth_correctly(): void
    {
        $password  = 'testpassword';
        $salt      = 'testsalt';
        $challenge = 'testchallenge';

        $secret   = base64_encode(hash('sha256', $password . $salt, true));
        $expected = base64_encode(hash('sha256', $secret . $challenge, true));

        $result = $this->computeAuth($password, $challenge, $salt);

        $this->assertEquals($expected, $result);
    }

    public function test_auth_with_different_credentials(): void
    {
        $password  = 'mySecret123!';
        $salt      = 'randomSalt456';
        $challenge = 'serverChallenge789';

        $secret   = base64_encode(hash('sha256', $password . $salt, true));
        $expected = base64_encode(hash('sha256', $secret . $challenge, true));

        $result = $this->computeAuth($password, $challenge, $salt);

        $this->assertEquals($expected, $result);
        $this->assertNotEmpty($result);
        $this->assertMatchesRegularExpression('/^[A-Za-z0-9+\/=]+$/', $result);
    }

    public function test_auth_is_not_plain_password(): void
    {
        $result = $this->computeAuth('hunter2', 'abc123', 'saltysalt');
        $this->assertStringNotContainsString('hunter2', $result);
    }

    public function test_different_challenges_produce_different_auth(): void
    {
        $password = 'password';
        $salt     = 'salt';

        $r1 = $this->computeAuth($password, 'challenge1', $salt);
        $r2 = $this->computeAuth($password, 'challenge2', $salt);

        $this->assertNotEquals($r1, $r2);
    }

    public function test_different_salts_produce_different_auth(): void
    {
        $password  = 'password';
        $challenge = 'challenge';

        $r1 = $this->computeAuth($password, $challenge, 'salt1');
        $r2 = $this->computeAuth($password, $challenge, 'salt2');

        $this->assertNotEquals($r1, $r2);
    }
}
