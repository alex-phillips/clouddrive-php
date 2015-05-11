<?php

namespace CloudDrive\Node;

use CloudDrive\Filter;
use CloudDrive\Node;

class Folder extends Node
{
    public function buildRemotePathById($id)
    {
        $path = [
            $id,
        ];
        while ($folder = $this->getMetadataById($id)) {
            array_unshift($path, $folder['id']);
            if (count($folder['parents']) === 0) {
                break;
            }

            $id = $folder['parents'][0];
        }

        return $path;
    }

    public function create($path = [], array $metadata = [], $nested = true)
    {
        $metadata += [
            'name' => '',
            'kind' => 'FOLDER',
        ];

        $path = $this->getPathArray($path);

        $body = [
            'success' => true,
            'data'    => ''
        ];
        if ($nested === true) {
            foreach ($path as $index => $identifier) {
                if (!$this->remoteFolderExists(array_slice($path, 0, $index + 1))) {
                    $parentPath = array_slice($path, 0, $index);
                    if (count($parentPath) >= 1) {
                        $response = $this->getRemoteByPath($parentPath);
                        $metadata['parents'] = [$response['data']['id']];
                    }

                    $metadata['name'] = $identifier;

                    $request = $this->httpClient->createRequest('POST', $this->account->getMetadataUrl() . 'nodes', [
                        'headers' => [
                            'Authorization' => 'Bearer ' . $this->account->getAccessToken(),
                        ],
                        'body' => json_encode($metadata),
                    ]);

                    $response = $this->sendRequest($request);

                    sleep($this->timeout);

                    $body['data'] = $response['data'];
                }
            }
        }

        return $body;
    }

    public function getLineageById($id)
    {
        $lineage = [];
        $response = $this->getMetadataById($id);

        if ($response['success']) {
            $folder = $response['data'];

            // @TODO: maybe replace this with a check for 'isRoot'? That offset exists
            // on the root.
            while (isset($folder['parents']) && count($folder['parents']) > 0) {
                array_unshift($lineage, $folder);

                $response = $this->getMetadataById($folder['parents'][0]);
                if (!$response['success']) {
                    return $response;
                }

                $folder = $response['data'];
            }

            return $lineage;
        } else {
            return $response;
        }
    }

    public function getRemoteByPath($path)
    {
        $path = $this->getPathArray($path);

        $folder = end($path);
        $query = $this->listFolders(
            (new Filter())->addEqualityFilter('name', $folder)
        );

        if (count($query['data']['data']) === 0) {
            return [
                'success' => true,
                'data'    => [],
            ];
        }

        foreach ($query['data']['data'] as $f) {
            $lineage = $this->getLineageById($f['id']);
            if ($this->lineageMatchesPath($lineage, $path)) {
                return [
                    'success' => true,
                    'data'    => $f,
                ];
            }
        }

        return [
            'success' => false,
        ];
    }

    public function lineageMatchesPath(array $lineage, $path)
    {
        $path = $this->getPathArray($path);

        if (count($lineage) !== count($path)) {
            return false;
        }

        foreach ($lineage as $index => $info) {
            if ($info['name'] !== $path[$index]) {
                return false;
            }
        }

        return true;
    }

    /**
     * List all folders from CloudDrive with the given filters and query params.
     *
     * @param \CloudDrive\Filter $filter      Params to filter the list by
     * @param array              $queryParams Additionaly query params for the request
     *
     * @return mixed
     */
    public function listFolders(Filter $filter = null, array $queryParams = [])
    {
        $filter->addEqualityFilter('kind', 'FOLDER');

        if (is_null($filter)) {
            $filter = new Filter();
        }

        $request = $this->httpClient->createRequest('GET', $this->account->getMetadataUrl() . 'nodes');

        $request->getQuery()->set('filters', $filter->buildString());

        foreach ($queryParams as $k => $v) {
            $request->getQuery()->set($k, $v);
        }

        $request->setHeaders([
            'Authorization' => 'Bearer ' . $this->account->getAccessToken(),
        ]);

        $response = $this->sendRequest($request);

        if ($response['success']) {
            if (isset($response['data']['nextToken'])) {
                $queryParams['startToken'] = $response['data']['nextToken'];
                $next = $this->listFolders($filter, $queryParams);

                $response['data']['data'] = array_merge($response['data']['data'], $next['data']['data']);
            }
        }

        return $response;
    }

    public function remoteFolderExists($path)
    {
        $path = $this->getPathString($path);

        if (empty($path)) {
            return true;
        }

        $response = $this->getRemoteByPath($path);
        if ($response['success'] && $response['data']) {
            return true;
        }

        return false;
    }
}
