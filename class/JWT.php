<?php

class JWT {
    private static $secret_key = "your_super_secret_jwt_key_here_change_this_in_production";
    private static $algorithm = 'HS256';
    
    /**
     * Generate JWT token
     */
    public static function encode($payload, $expiration_hours = 24) {
        $header = [
            'typ' => 'JWT',
            'alg' => self::$algorithm
        ];
        
        $payload['iat'] = time(); // Issued at
        $payload['exp'] = time() + ($expiration_hours * 3600); // Expiration
        
        $header_encoded = self::base64UrlEncode(json_encode($header));
        $payload_encoded = self::base64UrlEncode(json_encode($payload));
        
        $signature = hash_hmac('sha256', $header_encoded . "." . $payload_encoded, self::$secret_key, true);
        $signature_encoded = self::base64UrlEncode($signature);
        
        return $header_encoded . "." . $payload_encoded . "." . $signature_encoded;
    }
    
    /**
     * Decode and validate JWT token
     */
    public static function decode($token) {
        $parts = explode('.', $token);
        
        if (count($parts) !== 3) {
            return false;
        }
        
        list($header_encoded, $payload_encoded, $signature_encoded) = $parts;
        
        $header = json_decode(self::base64UrlDecode($header_encoded), true);
        $payload = json_decode(self::base64UrlDecode($payload_encoded), true);
        
        if (!$header || !$payload) {
            return false;
        }
        
        // Verify signature
        $signature = self::base64UrlDecode($signature_encoded);
        $expected_signature = hash_hmac('sha256', $header_encoded . "." . $payload_encoded, self::$secret_key, true);
        
        if (!hash_equals($signature, $expected_signature)) {
            return false;
        }
        
        // Check expiration
        if (isset($payload['exp']) && $payload['exp'] < time()) {
            return false; // Token expired
        }
        
        return $payload;
    }
    
    /**
     * Validate token and return customer data
     */
    public static function validateToken($token) {
        if (!$token) {
            return [
                'valid' => false,
                'message' => 'No token provided'
            ];
        }
        
        $payload = self::decode($token);
        
        if ($payload === false) {
            return [
                'valid' => false,
                'message' => 'Invalid or expired token'
            ];
        }
        
        return [
            'valid' => true,
            'payload' => $payload,
            'customer_id' => $payload['customer_id'] ?? null,
            'email' => $payload['email'] ?? null
        ];
    }
    
    /**
     * Refresh token (generate new token with extended expiration)
     */
    public static function refreshToken($token, $expiration_hours = 24) {
        $payload = self::decode($token);
        
        if ($payload === false) {
            return false;
        }
        
        // Remove old timestamps
        unset($payload['iat'], $payload['exp']);
        
        // Generate new token
        return self::encode($payload, $expiration_hours);
    }
    
    /**
     * Base64 URL safe encode
     */
    private static function base64UrlEncode($data) {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
    
    /**
     * Base64 URL safe decode
     */
    private static function base64UrlDecode($data) {
        return base64_decode(str_pad(strtr($data, '-_', '+/'), strlen($data) % 4, '=', STR_PAD_RIGHT));
    }
}
?>
