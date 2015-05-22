<?php

namespace CloudDrive;

use GuzzleHttp\Client;

class Auth extends Object
{
    protected $accessToken;

    protected $config = [];

    protected $email;

    protected $httpClient;

    public function __construct(array $config = [])
    {
        if (isset($config['tokens_directory']) && substr($config['tokens_directory'], -1) !== '/') {
            $config['tokens_directory'] .= '/';
        }

        $this->config = $config;
        $this->httpClient = new Client();
    }

    public function authorize($email)
    {
        $this->email = $email;

        $token = '';
        if (isset($this->config['tokens_store'])) {
            if (file_exists($this->config['tokens_store'])) {
                $tokens = json_decode(file_get_contents($this->config['tokens_store']), true);
                if (isset($tokens[$email])) {
                    $token = $tokens[$email];
                }
            }
        } else if (isset($this->config['tokens_directory'])) {
            if (file_exists("{$this->config['tokens_directory']}{$this->email}.token")) {
                $token = json_decode(file_get_contents(APP_ROOT
                    . "tokens/{$this->email}.token"), true);
                if (time() - $token['lastAuthorized'] > 60) {
                    $token = $this->refreshToken($token['refresh_token']);
                }
            }
        }

        if (!$token) {
            $token = $this->getAuthorizationGrant();
        }

        $this->accessToken = $token['access_token'];
    }

    public function getAccessToken()
    {
        return $this->accessToken;
    }

    public function getAuthorizationGrant()
    {
        echo "Navigate to the following URL and paste in the URL you are redirected to:\n";
        echo "https://www.amazon.com/ap/oa?client_id=amzn1.application-oa2-client.98cb6d1b9d304f08a2ccc8d59fb4e4e4&scope=clouddrive%3Aread%20clouddrive%3Awrite&response_type=code&redirect_uri=http://localhost\n";

        $handle = fopen("php://stdin", "r");
        $url = trim(fgets($handle));

        $info = parse_url($url);
        parse_str($info['query'], $query);

        if (!isset($query['code'])) {
            throw new \RuntimeException("No code exists in the redirect URL.");
        }

        $code = $query['code'];

        $request = $this->httpClient->createRequest('POST', 'https://api.amazon.com/auth/o2/token', [
            'headers' => [
                'Content-Type' => 'application/x-www-form-urlencoded',
            ],
            'body'    => [
                'grant_type'    => "authorization_code",
                'code'          => $code,
                'client_id'     => $this->config['client_id'],
                'client_secret' => $this->config['client_secret'],
                'redirect_uri'  => "http://localhost",
            ],
        ]);

        $response = $this->sendRequest($request);

        if ($response['success']) {
            $response['data']['lastAuthorized'] = time();
            $this->saveToken($response['data']);
        } else {
            throw new \Exception($response['data']['message']);
        }

        return $response['data'];
    }

    public function refreshToken($refreshToken)
    {
        $request = $this->httpClient->createRequest('POST', 'https://api.amazon.com/auth/o2/token', [
            'headers' => [
                'Content-Type' => 'application/x-www-form-urlencoded',
            ],
            'body' => [
                'grant_type'    => "refresh_token",
                'refresh_token' => $refreshToken,
                'client_id'     => $this->config['client_id'],
                'client_secret' => $this->config['client_secret'],
                'redirect_uri'  => "http://localhost",
            ],
        ]);

        $response = $this->sendRequest($request);

        if ($response['success']) {
            $response['data']['lastAuthorized'] = time();
            $this->saveToken($response['data']);
        } else {
            throw new \Exception("Unable to refresh authorization token: " . $response['data']['message']);
        }

        return $response;
    }

    protected function saveToken($tokenData)
    {
        if (isset($this->config['tokens_store'])) {
            $data = [];
            if (file_exists($this->config['tokens_store'])) {
                $data = json_decode(file_get_contents($this->config['tokens_store']), true);
            }
            $data[$this->email] = $tokenData;
            file_put_contents($this->config['tokens_store'], json_encode($data));
        } else if (isset($this->config['tokens_directory'])) {
            file_put_contents($this->config['tokens_directory'] . "{$this->email}.token", json_encode($tokenData));
        }
    }
}
