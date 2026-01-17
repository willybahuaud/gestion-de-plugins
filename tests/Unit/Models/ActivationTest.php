<?php

namespace Tests\Unit\Models;

use App\Models\Activation;
use Tests\TestCase;

class ActivationTest extends TestCase
{
    public function test_normalize_domain_removes_protocol(): void
    {
        $this->assertEquals('example.com', Activation::normalizeDomain('https://example.com'));
        $this->assertEquals('example.com', Activation::normalizeDomain('http://example.com'));
    }

    public function test_normalize_domain_removes_www(): void
    {
        $this->assertEquals('example.com', Activation::normalizeDomain('www.example.com'));
        $this->assertEquals('example.com', Activation::normalizeDomain('https://www.example.com'));
    }

    public function test_normalize_domain_removes_trailing_slash(): void
    {
        $this->assertEquals('example.com', Activation::normalizeDomain('example.com/'));
        $this->assertEquals('example.com/path', Activation::normalizeDomain('example.com/path/'));
    }

    public function test_normalize_domain_converts_to_lowercase(): void
    {
        $this->assertEquals('example.com', Activation::normalizeDomain('EXAMPLE.COM'));
        $this->assertEquals('example.com', Activation::normalizeDomain('Example.Com'));
    }

    public function test_normalize_domain_handles_complex_urls(): void
    {
        $this->assertEquals(
            'example.com/path',
            Activation::normalizeDomain('https://www.example.com/path/')
        );
    }

    public function test_domain_is_normalized_on_set(): void
    {
        $activation = new Activation();
        $activation->domain = 'HTTPS://WWW.EXAMPLE.COM/';

        $this->assertEquals('example.com', $activation->domain);
    }
}
