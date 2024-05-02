<?php

require_once 'vendor/autoload.php';

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

class Token {

    private $secret_key;
    private $expiration_time;

    public function __construct() {
        $this->secret_key = '';
        $this->expiration_time = 259200;
    }

    public function createToken($figma_id) {
        $issued_at = time();
        $expiration_time = $issued_at + $this->expiration_time;
        $payload = array(
            'figma_id' => $figma_id,
            'iat' => $issued_at,
            'exp' => $expiration_time,
            'iss' => 'https://svgconverter.ru',
            'aud' => 'https://svgconverter.ru'
        );
        $jwt = JWT::encode($payload, $this->secret_key, 'HS256');
        return $jwt;
    }

    public function verifyToken($jwt) {
        try {
            $decoded = JWT::decode($jwt, new Key($this->secret_key, 'HS256'));
            return $decoded;
        } catch (Exception $e) {
            return false;
        }
    }
}