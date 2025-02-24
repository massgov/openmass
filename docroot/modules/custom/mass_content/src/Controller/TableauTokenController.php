<?php

namespace Drupal\mass_content\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpFoundation\JsonResponse;
use Firebase\JWT\JWT;

/**
 * Provides a Tableau JWT Token endpoint.
 */
class TableauTokenController extends ControllerBase {

  /**
   * Generates a JWT token for Tableau Connected Apps embedding.
   */
  public function generateToken() {
    ob_clean(); // Clear any output before this.

    $client_id = 'f2255c8c-256c-4c77-9e2d-22a4293433b4';
    $secret_id = '40bff8ff-9706-4537-b36c-ec33d47efced';
    $secret_value = 'sm8uHLPLvyeq6io5G4tgLDgCU7EMm7l0TYuHj31GEvo=';
    $user_email = 'arthur@lastcallmedia.com';

    $payload = [
      'iss' => $client_id,
      'sub' => $user_email,
      'aud' => 'tableau',
      'exp' => (int) time() + 5 * 60,
      'jti' => bin2hex(random_bytes(16)),
      'scp' => ['tableau:views:embed'],
    ];

    $headers = [
      'kid' => $secret_id,
    ];

    $jwt = JWT::encode(
      $payload,
      base64_decode($secret_value),
      'HS256',
      $headers['kid']
    );

    return new JsonResponse(['token' => $jwt], 200, ['Content-Type' => 'application/json']);
  }

}
