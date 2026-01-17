<?php

namespace App\Http\Middleware;

use App\Models\License;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyHmacSignature
{
    /**
     * Verify HMAC signature for plugin requests.
     *
     * The signature is computed as: HMAC-SHA256(request_body, license_secret)
     * The signature must be sent in the X-Plugin-Signature header.
     *
     * This middleware is optional and only validates if a signature is present.
     * To enforce signatures, use VerifyHmacSignature::class with 'required' option.
     */
    public function handle(Request $request, Closure $next, string $mode = 'optional'): Response
    {
        $signature = $request->header('X-Plugin-Signature');
        $timestamp = $request->header('X-Plugin-Timestamp');
        $licenseKey = $request->input('license_key');

        // If no signature provided
        if (empty($signature)) {
            if ($mode === 'required') {
                return response()->json([
                    'success' => false,
                    'error' => 'missing_signature',
                    'message' => 'X-Plugin-Signature header is required',
                ], 401);
            }

            // Optional mode: continue without signature validation
            return $next($request);
        }

        // Validate timestamp to prevent replay attacks (5 minute window)
        if (empty($timestamp)) {
            return response()->json([
                'success' => false,
                'error' => 'missing_timestamp',
                'message' => 'X-Plugin-Timestamp header is required when using signatures',
            ], 401);
        }

        $timestampInt = (int) $timestamp;
        $now = time();
        $maxAge = 300; // 5 minutes

        if (abs($now - $timestampInt) > $maxAge) {
            return response()->json([
                'success' => false,
                'error' => 'expired_timestamp',
                'message' => 'Request timestamp is too old or in the future',
            ], 401);
        }

        // Get license to retrieve the signing secret
        if (empty($licenseKey)) {
            return response()->json([
                'success' => false,
                'error' => 'missing_license_key',
                'message' => 'license_key is required for signature validation',
            ], 401);
        }

        $license = License::where('uuid', $licenseKey)->first();

        if (!$license) {
            return response()->json([
                'success' => false,
                'error' => 'invalid_license',
                'message' => 'License not found',
            ], 401);
        }

        // Get the signing secret (use license UUID + app key as secret if no dedicated secret)
        $secret = $license->signing_secret ?? hash('sha256', $license->uuid . config('app.key'));

        // Compute expected signature
        // Payload = timestamp + request body (sorted JSON)
        $payload = $timestamp . $this->getCanonicalBody($request);
        $expectedSignature = hash_hmac('sha256', $payload, $secret);

        // Constant-time comparison to prevent timing attacks
        if (!hash_equals($expectedSignature, $signature)) {
            return response()->json([
                'success' => false,
                'error' => 'invalid_signature',
                'message' => 'Request signature is invalid',
            ], 401);
        }

        // Store license in request for later use (avoids duplicate query)
        $request->attributes->set('verified_license', $license);

        return $next($request);
    }

    /**
     * Get canonical (sorted) body for consistent signature computation.
     */
    private function getCanonicalBody(Request $request): string
    {
        $data = $request->all();
        ksort($data);
        return json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }
}
