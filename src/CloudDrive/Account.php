<?php

namespace CloudDrive;

use GuzzleHttp\Client;
use Utility\ParameterBag;

/**
 * Class containing all account information as well as managing permission and
 * requesting / renewing access.
 *
 * @package CloudDrive
 */
class Account
{
    /**
     * The checkpoint of the last sync request
     *
     * @var null|string
     */
    private $checkpoint;

    /**
     * The Guzzle HTTP client
     *
     * @var \GuzzleHttp\Client
     */
    private $httpClient;

    /**
     * Amazon CloudDrive API Client ID credentials
     *
     * @var string
     */
    private $clientId;

    /**
     * Amazon CloudDrive API Client Secret credentials
     *
     * @var string
     */
    private $clientSecret;

    /**
     * The account's content URL received from the `endpoint` API call
     *
     * @var string
     */
    private $contentUrl;

    /**
     * Local cache storage object
     *
     * @var \CloudDrive\Cache
     */
    private $cache;

    /**
     * Account email
     *
     * @var string
     */
    private $email;

    /**
     * The account's metadata URL received from the `endpoint` API call
     *
     * @var string
     */
    private $metadataUrl;

    /**
     * API permissions scope
     *
     * @var array
     */
    private $scope = [
        self::SCOPE_READ_ALL,
        self::SCOPE_WRITE,
    ];

    /**
     * API access token data
     *
     * @var \Utility\ParameterBag
     */
    private $token;

    const SCOPE_READ_IMAGE    = 'clouddrive:read_image';
    const SCOPE_READ_VIDEO    = 'clouddrive:read_video';
    const SCOPE_READ_DOCUMENT = 'clouddrive:read_document';
    const SCOPE_READ_OTHER    = 'clouddrive:read_other';
    const SCOPE_READ_ALL      = 'clouddrive:read_all';
    const SCOPE_WRITE         = 'clouddrive:write';

    /**
     * Construct a new `Account` instance with the user's email as well as the
     * Amazon Cloud Drive API credentials and data store.
     *
     * @param string $email
     * @param string $clientId
     * @param string $clientSecret
     * @param Cache  $cache
     */
    public function __construct($email, $clientId, $clientSecret, Cache $cache)
    {
        $this->email = $email;
        $this->clientId = $clientId;
        $this->clientSecret = $clientSecret;
        $this->cache = $cache;
        $this->httpClient = new Client();
    }

    /**
     * Authorize the user object. If the initial authorization has not been
     * completed, the `auth_url` is returned. If authorization has already
     * happened and the `access_token` hasn't passed its expiration time, we
     * are already authorized. Otherwise, if the `access_token` has expired,
     * request a new token. This method also retrieves the user's API endpoints.
     *
     * @param null|string $redirectUrl The URL the user is redirected to after
     *                                 navigating to the authorization URL.
     *
     * @return array
     */
    public function authorize($redirectUrl = null)
    {
        $retval = [
            'success' => true,
            'data'    => [],
        ];

        $config = $this->cache->loadAccountConfig($this->email) ?: [];

        $this->token = new ParameterBag($config);

        $scope = rawurlencode(implode(' ', $this->scope));

        if (!$this->token["access_token"]) {
            if (!$redirectUrl) {
                $retval['success'] = false;
                if (!$this->clientId || !$this->clientSecret) {
                    $retval['data'] = [
                        'message'  => 'Initial authorization is required',
                        'auth_url' => 'https://data-mind-687.appspot.com/clouddrive',
                    ];
                } else {
                    $retval['data'] = [
                        'message'  => 'Initial authorization required.',
                        'auth_url' => "https://www.amazon.com/ap/oa?client_id={$this->clientId}&scope={$scope}&response_type=code&redirect_uri=http://localhost",
                    ];
                }

                return $retval;
            }

            $response = $this->requestAuthorization($redirectUrl);

            if (!$response["success"]) {
                return $response;
            }
        } else {
            if (time() - $this->token["last_authorized"] > $this->token["expires_in"]) {
                $response = $this->renewAuthorization();
                if (!$response["success"]) {
                    return $response;
                }
            }
        }

        if (isset($response)) {
            $this->token->merge($response['data']);
        }

        if (!$this->token["metadata_url"] || !$this->token["content_url"]) {
            $response = $this->fetchEndpoint();
            if (!$response['success']) {
                return $response;
            }

            $this->token['metadata_url'] = $response['data']['metadataUrl'];
            $this->token['content_url'] = $response['data']['contentUrl'];
        }

        $this->checkpoint = $this->token["checkpoint"];
        $this->metadataUrl = $this->token["metadata_url"];
        $this->contentUrl = $this->token["content_url"];

        $this->save();

        return $retval;
    }

    /**
     * Reset the account's sync checkpoint.
     */
    public function clearCache()
    {
        $this->checkpoint = null;
        $this->save();
        $this->cache->deleteAllNodes();
    }

    /**
     * Fetch the user's API endpoints from the REST API.
     *
     * @return array
     */
    private function fetchEndpoint()
    {
        $retval = [
            'success' => false,
            'data'    => [],
        ];

        $response = $this->httpClient->get('https://cdws.us-east-1.amazonaws.com/drive/v1/account/endpoint', [
            'headers'    => [
                'Authorization' => "Bearer {$this->token["access_token"]}",
            ],
            'exceptions' => false,
        ]);

        $retval['data'] = json_decode((string)$response->getBody(), true);

        if ($response->getStatusCode() === 200) {
            $retval['success'] = true;
        }

        return $retval;
    }

    /**
     * Retrieve the last sync checkpoint.
     *
     * @return null|string
     */
    public function getCheckpoint()
    {
        return $this->checkpoint;
    }

    /**
     * Retrieve the user's API content URL.
     *
     * @return string
     */
    public function getContentUrl()
    {
        return $this->contentUrl;
    }

    /**
     * Retrieve the account's email.
     *
     * @return string
     */
    public function getEmail()
    {
        return $this->email;
    }

    /**
     * Retrieve the user's API metadata URL.
     *
     * @return string
     */
    public function getMetadataUrl()
    {
        return $this->metadataUrl;
    }

    /**
     * Retrieve the account's quota.
     *
     * @return array
     */
    public function getQuota()
    {
        $retval = [
            'success' => false,
            'data'    => [],
        ];

        $response = $this->httpClient->get("{$this->getMetadataUrl()}account/quota", [
            'headers'    => [
                'Authorization' => "Bearer {$this->token["access_token"]}",
            ],
            'exceptions' => false,
        ]);

        $retval['data'] = json_decode((string)$response->getBody(), true);

        if ($response->getStatusCode() === 200) {
            $retval['success'] = true;
        }

        return $retval;
    }

    /**
     * Retrieve access token data.
     *
     * @return ParameterBag
     */
    public function getToken()
    {
        return $this->token;
    }

    /**
     * Retrieve the account's current usage.
     *
     * @return array
     */
    public function getUsage()
    {
        $retval = [
            'success' => false,
            'data'    => [],
        ];

        $response = $this->httpClient->get(
            "{$this->getMetadataUrl()}account/usage",
            [
                'headers'    => [
                    'Authorization' => "Bearer {$this->token["access_token"]}",
                ],
                'exceptions' => false,
            ]
        );

        $retval['data'] = json_decode((string)$response->getBody(), true);

        if ($response->getStatusCode() === 200) {
            $retval['success'] = true;
        }

        return $retval;
    }

    /**
     * Renew the OAuth2 access token after the current access token has expired.
     *
     * @return array
     */
    public function renewAuthorization()
    {
        $retval = [
            "success" => false,
            "data"    => [],
        ];

        if ($this->clientId && $this->clientSecret) {
            $response = $this->httpClient->post(
                'https://api.amazon.com/auth/o2/token',
                [
                    'form_params' => [
                        'grant_type'    => "refresh_token",
                        'refresh_token' => $this->token["refresh_token"],
                        'client_id'     => $this->clientId,
                        'client_secret' => $this->clientSecret,
                        'redirect_uri'  => "http://localhost",
                    ],
                    'exceptions'  => false,
                ]
            );
        } else {
            $response = $this->httpClient->get(
                'https://data-mind-687.appspot.com/clouddrive?refresh_token=${this.token.refresh_token}'
            );
        }

        $retval["data"] = json_decode((string)$response->getBody(), true);

        if ($response->getStatusCode() === 200) {
            $retval["success"] = true;
            $retval["data"]["last_authorized"] = time();
        }

        return $retval;
    }

    /**
     * Use the `code` from the passed in `authUrl` to retrieve the OAuth2
     * tokens for API access.
     *
     * @param string $authUrl The redirect URL from the authorization request
     *
     * @return array
     */
    private function requestAuthorization($authUrl)
    {
        $retval = [
            'success' => false,
            'data'    => [],
        ];

        if (!$token = json_decode($authUrl, true)) {
            $url = parse_url($authUrl);
            parse_str($url['query'], $params);

            if (!isset($params['code'])) {
                $retval['data']['message'] = "No authorization code found in callback URL: $authUrl";

                return $retval;
            }

            $response = $this->httpClient->post(
                'https://api.amazon.com/auth/o2/token',
                [
                    'form_params' => [
                        'grant_type'    => 'authorization_code',
                        'code'          => $params['code'],
                        'client_id'     => $this->clientId,
                        'client_secret' => $this->clientSecret,
                        'redirect_uri'  => 'http://localhost',
                    ],
                    'exceptions'  => false,
                ]
            );

            $retval["data"] = json_decode((string)$response->getBody(), true);

            if ($response->getStatusCode() === 200) {
                $retval["success"] = true;
                $retval["data"]["last_authorized"] = time();
            }
        } else {
            $retval["success"] = true;
            $retval["data"] = $token;
            $retval["data"]["last_authorized"] = time();
        }

        return $retval;
    }

    /**
     * Save the account config into the cache database. This includes authorization
     * tokens, endpoint URLs, and the last sync checkpoint.
     *
     * @return bool
     */
    public function save()
    {
        return $this->cache->saveAccountConfig($this);
    }

    /**
     * Set the permission scope of the API before requesting authentication.
     *
     * @param array $scopes The permissions requested
     *
     * @return $this
     */
    public function setScope(array $scopes)
    {
        $this->scope = $scopes;

        return $this;
    }

    /**
     * Sync the local cache with the remote changes. If checkpoint is null, this
     * will sync all remote node data.
     *
     * @throws \Exception
     */
    public function sync()
    {
        $params = [
            'maxNodes' => 5000,
        ];

        if ($this->checkpoint) {
            $params['includePurged'] = "true";
        }

        while (true) {
            if ($this->checkpoint) {
                $params['checkpoint'] = $this->checkpoint;
            }

            $loop = true;

            $response = $this->httpClient->post(
                "{$this->getMetadataUrl()}changes",
                [
                    'headers'    => [
                        'Authorization' => "Bearer {$this->token['access_token']}",
                    ],
                    'body'       => json_encode($params),
                    'exceptions' => false,
                ]
            );

            if ($response->getStatusCode() !== 200) {
                throw new \Exception((string)$response->getBody());
            }

            $data = explode("\n", (string)$response->getBody());
            foreach ($data as $part) {
                $part = json_decode($part, true);

                if (isset($part['end']) && $part['end'] === true) {
                    break;
                }

                if (isset($part['reset']) && $part['reset'] === true) {
                    $this->cache->deleteAllNodes();
                }

                if (isset($part['nodes'])) {
                    if (empty($part['nodes'])) {
                        $loop = false;
                    } else {
                        foreach ($part['nodes'] as $node) {
                            $node = new Node($node);
                            if ($node['status'] === 'PURGED') {
                                $node->delete();
                            } else {
                                $node->save();
                            }
                        }
                    }
                }

                if (isset($part['checkpoint'])) {
                    $this->checkpoint = $part['checkpoint'];
                }

                $this->save();
            }

            if (!$loop) {
                break;
            }
        }
    }
}
