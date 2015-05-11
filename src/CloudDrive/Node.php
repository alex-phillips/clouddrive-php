<?php

namespace CloudDrive;

use GuzzleHttp\Client;

class Node extends Object
{
    protected $account;

    protected $contentUrl = '';

    protected $remoteRoot = '';

    protected $timeout = 2;

    public function __construct(Account $account)
    {
        $this->account = $account;
        $this->contentUrl = $account->getContentUrl();

        // Make sure content URL contains trailing '/'
        if (substr($this->contentUrl, -1) !== '/') {
            $this->contentUrl .= '/';
        }

        parent::__construct();
    }

    public function getMetadataById($id)
    {
        $request = $this->httpClient->createRequest('GET', $this->account->getMetadataUrl() . "nodes/$id");
        $request->getQuery()->set('fields', '["properties"]');
        $request->setHeaders([
            'Authorization' => 'Bearer ' . $this->account->getAccessToken(),
        ]);

        return $this->sendRequest($request);
    }

    public function getPathArray($path)
    {
        if (is_array($path)) {
            return $path;
        }

        return array_filter(explode('/', $path), function ($val) {
            return ($val !== "");
        });
    }

    public function getPathString($path)
    {
        if (is_array($path)) {
            $path = implode("/", $path);
        }

        return trim($path, " \t\n\r\0\x0B/");
    }
}
