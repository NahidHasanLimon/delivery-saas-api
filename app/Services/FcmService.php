<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class FcmService
{
    private $projectId;
    private $serviceAccountKeyPath;

    public function __construct()
    {
        $this->projectId = config('services.fcm.project_id');
        $this->serviceAccountKeyPath = config('services.fcm.service_account_key_path');
    }

    /**
     * Get OAuth2 access token for FCM v1
     */
    private function getAccessToken()
    {
        $cacheKey = 'fcm_access_token';
        
        return Cache::remember($cacheKey, 3300, function () {
            $serviceAccountPath = base_path($this->serviceAccountKeyPath);
            
            if (!file_exists($serviceAccountPath)) {
                throw new \Exception('Service account key file not found: ' . $serviceAccountPath);
            }
            
            $serviceAccount = json_decode(file_get_contents($serviceAccountPath), true);
            
            if (!$serviceAccount) {
                throw new \Exception('Invalid service account key file');
            }

            $now = time();
            $header = [
                'alg' => 'RS256',
                'typ' => 'JWT'
            ];

            $payload = [
                'iss' => $serviceAccount['client_email'],
                'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
                'aud' => 'https://oauth2.googleapis.com/token',
                'iat' => $now,
                'exp' => $now + 3600
            ];

            $jwt = $this->createJWT($header, $payload, $serviceAccount['private_key']);

            $response = Http::asForm()->post('https://oauth2.googleapis.com/token', [
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion' => $jwt
            ]);

            if ($response->successful()) {
                return $response->json('access_token');
            }

            throw new \Exception('Failed to get access token: ' . $response->body());
        });
    }

    /**
     * Create JWT token for OAuth2
     */
    private function createJWT($header, $payload, $privateKey)
    {
        $headerEncoded = $this->base64UrlEncode(json_encode($header));
        $payloadEncoded = $this->base64UrlEncode(json_encode($payload));
        
        $data = $headerEncoded . '.' . $payloadEncoded;
        
        $signature = '';
        openssl_sign($data, $signature, $privateKey, OPENSSL_ALGO_SHA256);
        
        $signatureEncoded = $this->base64UrlEncode($signature);
        
        return $data . '.' . $signatureEncoded;
    }

    /**
     * Base64 URL encode
     */
    private function base64UrlEncode($data)
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    /**
     * Send push notification to a single device token
     */
    public function sendToOne($token, $title, $body, $data = [], $webConfig = [])
    {
        $accessToken = $this->getAccessToken();
        
        $message = [
            'message' => [
                'token' => $token,
                'notification' => [
                    'title' => $title,
                    'body' => $body
                ],
                'webpush' => [
                    'notification' => array_merge([
                        'title' => $title,
                        'body' => $body,
                        'icon' => '/icon-192x192.png',
                        'badge' => '/badge-72x72.png',
                        'requireInteraction' => true,
                        'actions' => []
                    ], $webConfig)
                ]
            ]
        ];

        if (!empty($data)) {
            $message['message']['data'] = $data;
        }

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $accessToken,
            'Content-Type' => 'application/json'
        ])->post("https://fcm.googleapis.com/v1/projects/{$this->projectId}/messages:send", $message);

        if ($response->successful()) {
            Log::info('FCM notification sent successfully', [
                'token' => substr($token, 0, 20) . '...',
                'title' => $title,
                'response' => $response->json()
            ]);
            return $response->json();
        }

        Log::error('FCM notification failed', [
            'token' => substr($token, 0, 20) . '...',
            'title' => $title,
            'error' => $response->body(),
            'status' => $response->status()
        ]);

        throw new \Exception('Failed to send notification: ' . $response->body());
    }

    /**
     * Send push notification to multiple device tokens
     */
    public function sendToMany($tokens, $title, $body, $data = [], $webConfig = [])
    {
        $results = [];
        
        foreach ($tokens as $token) {
            try {
                $result = $this->sendToOne($token, $title, $body, $data, $webConfig);
                $results[] = [
                    'token' => substr($token, 0, 20) . '...',
                    'success' => true,
                    'result' => $result
                ];
            } catch (\Exception $e) {
                $results[] = [
                    'token' => substr($token, 0, 20) . '...',
                    'success' => false,
                    'error' => $e->getMessage()
                ];
            }
        }

        return $results;
    }

}
