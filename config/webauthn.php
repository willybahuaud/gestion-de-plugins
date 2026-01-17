<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Relying Party (RP) Information
    |--------------------------------------------------------------------------
    |
    | The Relying Party is the application the user is authenticating to.
    | The name is displayed during registration. The ID should be the
    | application domain without protocol or port.
    |
    */

    'relying_party' => [
        'name' => env('APP_NAME', 'Plugin Hub'),
        'id' => env('WEBAUTHN_RP_ID', parse_url(env('APP_URL', 'http://localhost'), PHP_URL_HOST)),
    ],

    /*
    |--------------------------------------------------------------------------
    | Challenge Length
    |--------------------------------------------------------------------------
    |
    | The length of the random challenge used in the registration and
    | authentication ceremonies. A higher value is more secure.
    |
    */

    'challenge_length' => 32,

    /*
    |--------------------------------------------------------------------------
    | Challenge Timeout
    |--------------------------------------------------------------------------
    |
    | The time in seconds the challenge is valid. After this time, the
    | challenge will expire and the user will need to start over.
    |
    */

    'timeout' => 60000, // 60 seconds

    /*
    |--------------------------------------------------------------------------
    | User Verification
    |--------------------------------------------------------------------------
    |
    | Determines if user verification (PIN, biometric) is required,
    | preferred, or discouraged during authentication.
    |
    | Supported: "required", "preferred", "discouraged"
    |
    */

    'user_verification' => 'preferred',

    /*
    |--------------------------------------------------------------------------
    | Resident Key
    |--------------------------------------------------------------------------
    |
    | Determines if the authenticator should store the credential
    | internally, enabling passwordless authentication.
    |
    | Supported: "required", "preferred", "discouraged"
    |
    */

    'resident_key' => 'preferred',

    /*
    |--------------------------------------------------------------------------
    | Authenticator Attachment
    |--------------------------------------------------------------------------
    |
    | Restricts the type of authenticator that can be used.
    |
    | Supported: null, "platform", "cross-platform"
    | - null: Any authenticator
    | - "platform": Built-in (TouchID, Windows Hello)
    | - "cross-platform": External (YubiKey, security key)
    |
    */

    'authenticator_attachment' => null, // Allow both platform and cross-platform

    /*
    |--------------------------------------------------------------------------
    | Attestation Conveyance
    |--------------------------------------------------------------------------
    |
    | The attestation statement provides information about the authenticator.
    | For most applications, "none" is sufficient and protects user privacy.
    |
    | Supported: "none", "indirect", "direct", "enterprise"
    |
    */

    'attestation_conveyance' => 'none',

    /*
    |--------------------------------------------------------------------------
    | Credential Model
    |--------------------------------------------------------------------------
    |
    | The model used to store WebAuthn credentials.
    |
    */

    'model' => \Laragear\WebAuthn\Models\WebAuthnCredential::class,
];
