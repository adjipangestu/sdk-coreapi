<?php

namespace Rsjpdharapankita\Simrs;

class CoreApi
{
    private $version = '0000000001';
    private $apiSandboxBaseUrl = 'https://dev-coreapi.pjnhk.go.id';
    private $apiProductionBaseUrl = 'https://coreapi.pjnhk.go.id';
    private $clientId;
    private $clientSecret;
    private $grantType = 'client_credentials';
    private $isProduction = true;
    private $tokenCookieName = 'access_token';
    private $accessToken = null;
    private $tokenExpiry = null;
    private $username;
    private $password;

    public function __construct($clientId, $clientSecret, $isProduction = true, $grantType = 'client_credentials', $tokenCookieName = 'access_token', $username = null, $password = null)
    {
        $this->clientId = $clientId;
        $this->clientSecret = $clientSecret;
        $this->grantType = $grantType;
        $this->isProduction = $isProduction;
        $this->tokenCookieName = $tokenCookieName;
        $this->username = $username;
        $this->password = $password;
        $this->loadTokenFromCookie();
    }

    private function getCoreApiBaseUrl()
    {
        return $this->isProduction ? $this->apiProductionBaseUrl : $this->apiSandboxBaseUrl;
    }

    private function loadTokenFromCookie()
    {
        if (isset($_COOKIE[$this->tokenCookieName])) {
            $this->accessToken = $_COOKIE[$this->tokenCookieName];
            if (isset($_COOKIE['token_expiry'])) {
                $this->tokenExpiry = $_COOKIE['token_expiry'];
                if (time() > floatval($this->tokenExpiry)) {
                    $this->accessToken = null;
                    $this->tokenExpiry = null;
                }
            }
        }
    }

    private function saveTokenToCookie($tokenData)
    {
        setcookie($this->tokenCookieName, $tokenData['access_token'], time() + ($tokenData['expires_in'] ?? 3600), "/");
        setcookie('token_expiry', strval(time() + ($tokenData['expires_in'] ?? 3600)), time() + ($tokenData['expires_in'] ?? 3600), "/");
        $this->accessToken = $tokenData['access_token'];
        $this->tokenExpiry = time() + ($tokenData['expires_in'] ?? 3600);
    }

    private function getAccessToken()
    {
        if ($this->accessToken === null || time() > $this->tokenExpiry) {
            $url = $this->getCoreApiBaseUrl() . '/oauth/token';

            $payload = [
                'grant_type' => $this->grantType,
                'client_id' => $this->clientId,
                'client_secret' => $this->clientSecret
            ];

            if ($this->grantType === 'password') {
                $payload['username'] = $this->username;
                $payload['password'] = $this->password;
            }

            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($payload));
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/x-www-form-urlencoded']);
            $response = curl_exec($ch);

            if (curl_errno($ch)) {
                throw new Exception('cURL error: ' . curl_error($ch));
            }

            $responseData = json_decode($response, true);
            curl_close($ch);

            $this->saveTokenToCookie($responseData);
        }
    }

    private function getHeaders()
    {
        $this->getAccessToken(); // Ensure we have a valid token
        return [
            'Authorization: Bearer ' . $this->accessToken,
            'Content-Type: application/json'
        ];
    }

    public function get($url, $params = [])
    {
        $this->getAccessToken();
        $queryString = http_build_query($params);
        $fullUrl = $url . '?' . $queryString;

        $ch = curl_init($fullUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $this->getHeaders());
        $response = curl_exec($ch);

        if (curl_errno($ch)) {
            return [
                'error' => true,
                'message' => 'cURL error: ' . curl_error($ch)
            ];
        }

        $httpStatus = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpStatus >= 200 && $httpStatus < 300) {
            return json_decode($response, true);
        } else {
            return [
                'error' => true,
                'status_code' => $httpStatus,
                'message' => $response
            ];
        }
    }

    public function post($url, $data = null)
    {
        $this->getAccessToken();
        
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $this->getHeaders());
        
        if (is_array($data)) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        } else {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        }

        $response = curl_exec($ch);

        if (curl_errno($ch)) {
            return [
                'error' => true,
                'message' => 'cURL error: ' . curl_error($ch)
            ];
        }

        $httpStatus = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpStatus >= 200 && $httpStatus < 300) {
            return json_decode($response, true);
        } else {
            return [
                'error' => true,
                'status_code' => $httpStatus,
                'message' => $response
            ];
        }
    }
}