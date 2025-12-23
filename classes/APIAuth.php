<?php
require_once __DIR__ . '/../vendor/autoload.php'; // Assuming you use Composer for JWT library

use \Firebase\JWT\JWT;
use \Firebase\JWT\Key;

class APIAuth {
    private static $secret_key = 'YOUR_SECRET_KEY'; // Replace with a strong, secret key from a config file
    private static $issuer = 'HSMS_API';
    private static $audience = 'HSMS_Clients';
    private static $issued_at;
    private static $not_before;
    private static $expire;

    /**
     * Generate a JWT token for a user.
     */
    public static function generateToken($user_id, $username, $role) {
        self::$issued_at = time();
        self::$not_before = self::$issued_at;
        self::$expire = self::$issued_at + (60 * 60 * 24); // 24 hours validity

        $token = array(
            "iss" => self::$issuer,
            "aud" => self::$audience,
            "iat" => self::$issued_at,
            "nbf" => self::$not_before,
            "exp" => self::$expire,
            "data" => array(
                "id" => $user_id,
                "username" => $username,
                "role" => $role
            )
        );

        return JWT::encode($token, self::$secret_key, 'HS256');
    }

    /**
     * Validate a JWT token and return the decoded data.
     */
    public static function validateToken() {
        $headers = getallheaders();
        if (!isset($headers['Authorization'])) {
            return ["success" => false, "message" => "Authorization header missing."];
        }

        $auth_header = $headers['Authorization'];
        list($jwt) = sscanf($auth_header, 'Bearer %s');

        if ($jwt) {
            try {
                $decoded = JWT::decode($jwt, new Key(self::$secret_key, 'HS256'));
                return ["success" => true, "data" => $decoded->data];
            } catch (Exception $e) {
                return ["success" => false, "message" => "Access denied: " . $e->getMessage()];
            }
        } else {
            return ["success" => false, "message" => "Malformed token."];
        }
    }
    
    /**
     * Check if the user from the token has the required role.
     */
    public static function hasRole($required_role) {
        $validation = self::validateToken();
        if (!$validation['success']) {
            return false;
        }
        
        $user_data = $validation['data'];
        $user_roles = is_array($user_data->role) ? $user_data->role : [$user_data->role];
        
        if (is_array($required_role)) {
            // Check if user has any of the required roles
            return !empty(array_intersect($user_roles, $required_role));
        } else {
            return in_array($required_role, $user_roles);
        }
    }
}
?>