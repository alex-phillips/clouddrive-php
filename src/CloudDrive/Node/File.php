<?php

namespace CloudDrive\Node;

use CloudDrive\Node;
use CloudDrive\Filter;

class File extends Node
{
    public function findByPath($filePath)
    {
        $pathInfo = pathinfo($filePath);
        $list = $this->listFiles((new Filter())->addEqualityFilter('name', $pathInfo['basename']));

        if (!$list['success']) {
            return false;
        }

        $folder = new Folder($this->account);

        $found = false;
        foreach ($list['data']['data'] as $file) {
            if ($folder->lineageMatchesPath($folder->getLineageById($file['parents'][0]), $pathInfo['dirname'])) {
                $found = true;
                break;
            }
        }

        if ($found) {
            return $file;
        }

        return null;
    }

    public function getMetadataById($id)
    {
        $url = $this->account->getMetadataUrl() . "nodes";
        $query = [
            'id' => $id,
        ];

        $url .= "?" . http_build_query($query);

        $request = $this->httpClient->createRequest('GET', $url, [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->account->getAccessToken(),
            ],
        ]);

        return $this->sendRequest($request);
    }

    public function listFiles(Filter $filter = null, $startToken = '', $limit = 200)
    {
        $request = $this->httpClient->createRequest('GET', $this->account->getMetadataUrl() . "nodes");

        if (is_null($filter)) {
            $filter = new Filter();
            $filter->addEqualityFilter('kind', 'FILE');
        }

        $request->getQuery()->set('filters', $filter->buildString());
        $request->getQuery()->setEncodingType(false);

        if ($startToken) {
            $request->getQuery()->set('nextToken', $startToken);
        }
        if ($limit !== 200) {
            $request->getQuery()->set('limit', $limit);
        }

        $request->addHeader('Authorization', 'Bearer ' . $this->account->getAccessToken());

        return $this->sendRequest($request);
    }

    public function overwrite($id, $file)
    {
        $url = $this->account->getContentUrl() . "nodes/$id/content";

        $request = $this->httpClient->createRequest('PUT', $url, [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->account->getAccessToken(),
            ],
            'body' => [
                'content' => fopen($file, 'r'),
            ]
        ]);

        return $this->sendRequest($request);
    }

    /**
     * Upload a new file and its metadata. Supported metadata:
     *   name (required) : file name. Max to 256 Characters.
     *   kind (required) : "FILE"
     *   labels (optional) : Extra information which is indexed. For example the value can be "PHOTO"
     *   properties (optional) : List of properties to be added for the file.
     *   parents(optional) : List of parent Ids. If no parent folders are provided, the file will be placed in the default root folder.
     */
    public function upload($file, array $options = [], array $metadata = [], $suppress = true)
    {
        $metadata += [
            'name'       => '',
            'kind'       => 'FILE',
        ];

        $fileInfo = pathinfo($file);
        if (!$metadata['name']) {
            $metadata['name'] = $fileInfo['basename'];
        }

        $folder = new Folder($this->account);

        if ($options['remotePath']) {
            if (!$folder->remoteFolderExists($options['remotePath'])) {
                $response = $folder->create($options['remotePath']);
            } else {
                $response = $folder->getRemoteByPath($options['remotePath']);
            }

            if ($response['success']) {
                $metadata['parents'] = [$response['data']['id']];
            } else {
                throw new \Exception("Could not find or create parent folder");
            }
        }

        $url = $this->contentUrl . 'nodes';

        if ($suppress === true) {
            $url .= '?suppress=deduplication';
        }

        $metadata = json_encode($metadata);

        $request = $this->httpClient->createRequest('POST', $url, [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->account->getAccessToken(),
            ],
            'body' => [
                'metadata' => $metadata,
                'content' => fopen($file, 'r'),
            ]
        ]);

        return $this->sendRequest($request);
    }
}