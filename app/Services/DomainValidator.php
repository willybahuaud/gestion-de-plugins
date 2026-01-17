<?php

namespace App\Services;

class DomainValidator
{
    /**
     * Domains that should never be allowed for production licenses.
     */
    protected array $blacklistedDomains = [
        'localhost',
        'localhost.localdomain',
        '127.0.0.1',
        '0.0.0.0',
        '::1',
        'example.com',
        'example.org',
        'example.net',
        'test.com',
        'test.local',
    ];

    /**
     * Domain patterns that indicate development/staging environments.
     */
    protected array $devPatterns = [
        '/\.local$/',
        '/\.localhost$/',
        '/\.test$/',
        '/\.example$/',
        '/\.invalid$/',
        '/\.dev$/',           // Often used for local dev
        '/\.ddev\.site$/',    // DDEV local environments
        '/\.lndo\.site$/',    // Lando local environments
        '/\.ngrok\.io$/',     // Ngrok tunnels
        '/\.localtunnel\.me$/',
    ];

    /**
     * IP ranges that are private/reserved (RFC 1918, RFC 4193, etc.)
     */
    protected array $privateIpRanges = [
        '10.0.0.0/8',
        '172.16.0.0/12',
        '192.168.0.0/16',
        '127.0.0.0/8',
        '169.254.0.0/16',
        '0.0.0.0/8',
    ];

    /**
     * Check if a domain is valid for license activation.
     *
     * @param string $domain The domain to validate
     * @param bool $allowDev Whether to allow development domains (for testing)
     * @return array{valid: bool, reason?: string, is_dev?: bool}
     */
    public function validate(string $domain, bool $allowDev = false): array
    {
        $domain = $this->normalize($domain);

        // Check if empty
        if (empty($domain)) {
            return [
                'valid' => false,
                'reason' => 'empty_domain',
            ];
        }

        // Check explicit blacklist
        if (in_array($domain, $this->blacklistedDomains, true)) {
            return [
                'valid' => false,
                'reason' => 'blacklisted_domain',
                'is_dev' => true,
            ];
        }

        // Check if it's an IP address
        if ($this->isIpAddress($domain)) {
            if ($this->isPrivateIp($domain)) {
                return [
                    'valid' => $allowDev,
                    'reason' => 'private_ip',
                    'is_dev' => true,
                ];
            }

            // Public IPs are generally okay but discouraged
            return [
                'valid' => true,
                'reason' => 'public_ip',
                'is_dev' => false,
            ];
        }

        // Check dev patterns
        foreach ($this->devPatterns as $pattern) {
            if (preg_match($pattern, $domain)) {
                return [
                    'valid' => $allowDev,
                    'reason' => 'dev_domain',
                    'is_dev' => true,
                ];
            }
        }

        // Check if domain has at least one dot (basic TLD check)
        if (strpos($domain, '.') === false) {
            return [
                'valid' => $allowDev,
                'reason' => 'no_tld',
                'is_dev' => true,
            ];
        }

        // Check for valid domain format
        if (!$this->isValidDomainFormat($domain)) {
            return [
                'valid' => false,
                'reason' => 'invalid_format',
            ];
        }

        return [
            'valid' => true,
            'is_dev' => false,
        ];
    }

    /**
     * Check if domain appears to be a development/staging domain.
     */
    public function isDevelopmentDomain(string $domain): bool
    {
        $result = $this->validate($domain, allowDev: true);
        return $result['is_dev'] ?? false;
    }

    /**
     * Normalize domain for comparison.
     */
    protected function normalize(string $domain): string
    {
        $domain = strtolower(trim($domain));
        $domain = preg_replace('#^https?://#', '', $domain);
        $domain = preg_replace('#^www\.#', '', $domain);
        $domain = rtrim($domain, '/');

        // Remove port if present
        $domain = preg_replace('/:\d+$/', '', $domain);

        // Remove path if present
        $domain = explode('/', $domain)[0];

        return $domain;
    }

    /**
     * Check if string is an IP address.
     */
    protected function isIpAddress(string $domain): bool
    {
        return filter_var($domain, FILTER_VALIDATE_IP) !== false;
    }

    /**
     * Check if IP is in private/reserved ranges.
     */
    protected function isPrivateIp(string $ip): bool
    {
        // Use PHP's built-in filter
        $result = filter_var(
            $ip,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
        );

        // If filter returns false, the IP is private or reserved
        return $result === false;
    }

    /**
     * Basic domain format validation.
     */
    protected function isValidDomainFormat(string $domain): bool
    {
        // Allow subdomains, alphanumeric, hyphens
        // Must have at least one dot, cannot start/end with hyphen
        $pattern = '/^(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,}$/';
        return preg_match($pattern, $domain) === 1;
    }

    /**
     * Get list of blacklisted domains.
     */
    public function getBlacklistedDomains(): array
    {
        return $this->blacklistedDomains;
    }

    /**
     * Add a domain to the blacklist.
     */
    public function addToBlacklist(string $domain): void
    {
        $domain = $this->normalize($domain);
        if (!in_array($domain, $this->blacklistedDomains, true)) {
            $this->blacklistedDomains[] = $domain;
        }
    }
}
