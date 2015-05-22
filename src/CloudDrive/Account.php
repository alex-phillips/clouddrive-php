<?php

namespace CloudDrive;

use GuzzleHttp\Client;

class Account extends Object
{
    protected $accessToken = '';

    protected $contentUrl = '';

    protected $metadataUrl = '';

    protected $urlPrefix = 'https://cdws.us-east-1.amazonaws.com/drive/v1/account/';

    public function __construct($accessToken)
    {
        $this->accessToken = $accessToken;
        $this->httpClient = new Client();
    }

    public function getAccessToken()
    {
        return $this->accessToken;
    }

    public function getAccountInfo()
    {
        $response = $this->httpClient->get($this->urlPrefix . 'info', [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->accessToken
            ]
        ]);

        $body = json_decode((string)$response->getBody(), true);

        return $body;
    }

    public function getContentUrl()
    {
        if (!$this->contentUrl) {
            $data = $this->getEndpoint();
            $this->contentUrl = $data['data']['contentUrl'];
        }

        return $this->contentUrl;
    }

    public function getEndpoint()
    {
        $request = $this->httpClient->createRequest('GET', $this->urlPrefix . 'endpoint', [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->accessToken
            ]
        ]);

        return $this->sendRequest($request);
    }

    public function getMetadataUrl()
    {
        if (!$this->metadataUrl) {
            $data = $this->getEndpoint();
            $this->metadataUrl = $data['data']['metadataUrl'];
        }

        return $this->metadataUrl;
    }

    public function getQuota()
    {
        $request = $this->httpClient->createRequest('GET', $this->urlPrefix . 'quota', [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->accessToken
            ]
        ]);

        return $this->sendRequest($request);
    }

    public function getUsage()
    {
        $request = $this->httpClient->createRequest('GET', $this->urlPrefix . 'usage', [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->accessToken
            ]
        ]);

        return $this->sendRequest($request);
    }
}
