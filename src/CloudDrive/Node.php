<?php
/**
 * @author Alex Phillips <ahp118@gmail.com>
 * Date: 7/10/15
 * Time: 3:36 PM
 */

namespace CloudDrive;

use ArrayAccess;
use Countable;
use GuzzleHttp\Client;
use IteratorAggregate;
use JsonSerializable;
use Utility\Traits\Bag;

/**
 * Class representing a remote `Node` object in Amazon's CloudDrive.
 *
 * @package CloudDrive
 */
class Node implements ArrayAccess, IteratorAggregate, JsonSerializable, Countable
{
    use Bag {
        __construct as constructor;
    }

    /**
     * Cloud Drive `Account` object
     *
     * @var \CloudDrive\Account
     */
    protected static $account;

    /**
     * Local `Cache` storage object
     *
     * @var \CloudDrive\Cache
     */
    protected static $cacheStore;

    /**
     * HTTP client
     *
     * @var \GuzzleHttp\Client
     */
    protected static $httpClient;

    /**
     * Flag set if the `Node` class has already been initialized
     *
     * @var bool
     */
    protected static $initialized = false;

    /**
     * Construct a new instance of a remote `Node` object given the metadata
     * provided.
     *
     * @param array $data
     *
     * @throws \Exception
     */
    public function __construct($data = [])
    {
        if (self::$initialized === false) {
            throw new \Exception("`Node` class must first be initialized.");
        }

        $this->constructor($data);
    }

    /**
     * Delete a `Node` and its parent associations.
     *
     * @return bool
     */
    public function delete()
    {
        return self::$cacheStore->deleteNodeById($this['id']);
    }

    /**
     * Download contents of `Node` to local save path. If only the local
     * directory is given, the file will be saved as its remote name.
     *
     * @param resource|string $dest
     * @param callable        $callback
     *
     * @return array
     * @throws \Exception
     */
    public function download($dest, $callback = null)
    {
        if ($this->isFolder()) {
            return $this->downloadFolder($dest, $callback);
        }

        return $this->downloadFile($dest, $callback);
    }

    /**
     * Save a FILE node to the specified local destination.
     *
     * @param resource|string $dest
     * @param callable        $callback
     *
     * @return array
     * @throws \Exception
     */
    protected function downloadFile($dest, $callback = null)
    {
        $retval = [
            'success' => false,
            'data'    => [],
        ];

        if (is_resource($dest)) {
            $handle = $dest;
            $metadata = stream_get_meta_data($handle);
            $dest = $metadata["uri"];
        } else {
            $dest = rtrim($dest, '/');

            if (file_exists($dest)) {
                if (is_dir($dest)) {
                    $dest = rtrim($dest, '/') . "/{$this['name']}";
                } else {
                    $retval['data']['message'] = "File already exists at '$dest'.";
                    if (is_callable($callback)) {
                        call_user_func($callback, $retval, $dest);
                    }

                    return $retval;
                }
            }

            $handle = @fopen($dest, 'a');

            if (!$handle) {
                throw new \Exception("Unable to open file at '$dest'. Make sure the directory path exists.");
            }
        }

        $response = self::$httpClient->get(
            self::$account->getContentUrl() . "nodes/{$this['id']}/content",
            [
                'headers'    => [
                    'Authorization' => 'Bearer ' . self::$account->getToken()['access_token'],
                ],
                'stream'     => true,
                'exceptions' => false,
            ]
        );

        $retval['data'] = json_decode((string)$response->getBody(), true);

        if ($response->getStatusCode() !== 200) {
            if (is_callable($callback)) {
                call_user_func($callback, $retval, $dest);
            }

            return $retval;
        }

        $retval['success'] = true;

        $body = $response->getBody();
        while (!$body->eof()) {
            fwrite($handle, $body->read(1024));
        }

        fclose($handle);

        if (is_callable($callback)) {
            call_user_func($callback, $retval, $dest);
        }

        return $retval;
    }

    /**
     * Recursively download all children of a FOLDER node to the specified
     * local destination.
     *
     * @param string   $dest
     * @param callable $callback
     *
     * @return array
     * @throws \Exception
     */
    protected function downloadFolder($dest, $callback = null)
    {
        $retval = [
            'success' => false,
            'data'    => [],
        ];

        if (!is_string($dest)) {
            throw new \Exception("Must pass in local path to download directory to.");
        }

        $nodes = $this->getChildren();

        $dest = rtrim($dest) . "/{$this['name']}";
        if (!file_exists($dest)) {
            mkdir($dest);
        }

        foreach ($nodes as $node) {
            if ($node->isFile()) {
                $node->download("{$dest}/{$node['name']}", $callback);
            } else if ($node->isFolder()) {
                $node->download($dest, $callback);
            }
        }

        $retval['success'] = true;

        return $retval;
    }

    /**
     * Search for nodes in the local cache by filters.
     *
     * @param array $filters
     *
     * @return array
     */
    public static function filter(array $filters)
    {
        return self::$cacheStore->filterNodes($filters);
    }

    /**
     * Get all children of the given `Node`.
     *
     * @return array
     */
    public function getChildren()
    {
        return self::$cacheStore->getNodeChildren($this);
    }

    /**
     * Get the MD5 checksum of the `Node`.
     *
     * @return mixed
     */
    public function getMd5()
    {
        return $this['contentProperties']['md5'];
    }

    /**
     * Retrieve the node's metadata directly from the API.
     *
     * @param bool|false $tempLink
     *
     * @return array
     */
    public function getMetadata($tempLink = false)
    {
        $retval = [
            'success' => false,
            'data'    => [],
        ];

        $query = [];

        if ($tempLink) {
            $query['tempLink'] = true;
        }

        $response = self::$httpClient->get(
            self::$account->getMetadataUrl() . "nodes/{$this['id']}",
            [
                'headers'    => [
                    'Authorization' => 'Bearer ' . self::$account->getToken()['access_token'],
                ],
                'query' => [
                    'tempLink' => $tempLink ? 'true' : 'false',
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
     * Build and return the remote directory path of the given `Node`.
     *
     * @return string
     * @throws \Exception
     */
    public function getPath()
    {
        $node = $this;
        $path = [];

        while (true) {
            $path[] = $node["name"];
            if ($node->isRoot()) {
                break;
            }

            $node = self::loadById($node["parents"][0]);
            if (is_null($node)) {
                throw new \Exception("No parent node found with ID {$node['parents'][0]}.");
            }

            if ($node->isRoot()) {
                break;
            }
        }

        $path = array_reverse($path);

        return implode('/', $path);
    }

    /**
     * Set the local storage cache.
     *
     * @param \CloudDrive\Account $account
     * @param \CloudDrive\Cache   $cacheStore
     *
     * @throws \Exception
     */
    public static function init(Account $account, Cache $cacheStore)
    {
        if (self::$initialized === true) {
            throw new \Exception("`Node` class has already been initialized.");
        }

        self::$account = $account;
        self::$cacheStore = $cacheStore;
        self::$httpClient = new Client();

        self::$initialized = true;
    }

    /**
     * Return `true` if the node is in the trash.
     *
     * @return bool
     */
    public function inTrash()
    {
        return $this['status'] === 'TRASH';
    }

    /**
     * Returns whether the `Node` is an asset or not.
     *
     * @return bool
     */
    public function isAsset()
    {
        return $this['kind'] === 'ASSET';
    }

    /**
     * Returns whether the `Node` is a file or not.
     *
     * @return bool
     */
    public function isFile()
    {
        return $this['kind'] === 'FILE';
    }

    /**
     * Returns whether the `Node` is a folder or not.
     *
     * @return bool
     */
    public function isFolder()
    {
        return $this['kind'] === 'FOLDER';
    }

    /**
     * Returns whether the `Node` is the `root` node.
     *
     * @return bool
     */
    public function isRoot()
    {
        return $this['isRoot'] ? true : false;
    }

    /**
     * Load a `Node` given an ID or remote path.
     *
     * @param string $param Parameter to find the `Node` by: ID or path
     *
     * @return \CloudDrive\Node|null
     */
    public static function load($param)
    {
        if (!($node = self::loadById($param))) {
            $node = self::loadByPath($param);
        }

        return $node;
    }

    /**
     * Find and return the `Node` matching the given ID.
     *
     * @param int|string $id ID of the node
     *
     * @return \CloudDrive\Node|null
     */
    public static function loadById($id)
    {
        return self::$cacheStore->findNodeById($id);
    }

    /**
     * Find and return `Nodes` that have the given MD5 checksum.
     *
     * @param string $md5
     *
     * @return array
     */
    public static function loadByMd5($md5)
    {
        return self::$cacheStore->findNodesByMd5($md5);
    }

    /**
     * Find all nodes whose name matches the given string.
     *
     * @param string $name
     *
     * @return array
     */
    public static function loadByName($name)
    {
        return self::$cacheStore->findNodesByName($name);
    }

    /**
     * Find and return `Node` that matches the given remote path.
     *
     * @param string $path Remote path of the `Node`
     *
     * @return \CloudDrive\Node|null
     * @throws \Exception
     */
    public static function loadByPath($path)
    {
        $path = trim($path, '/');
        if (!$path) {
            return self::loadRoot();
        }

        $info = pathinfo($path);
        $nodes = self::loadByName($info['basename']);
        if (empty($nodes)) {
            return null;
        }

        foreach ($nodes as $node) {
            if ($node->getPath() === $path) {
                return $node;
            }
        }

        return null;
    }

    /**
     * Return the root `Node`.
     *
     * @return \CloudDrive\Node
     * @throws \Exception
     */
    public static function loadRoot()
    {
        $results = self::loadByName('Cloud Drive');
        if (empty($results)) {
            throw new \Exception("No node by name 'Cloud Drive' found in the database.");
        }

        foreach ($results as $result) {
            if ($result->isRoot()) {
                return $result;
            }
        }

        throw new \Exception("Unable to find root node.");
    }

    /**
     * Move a FILE or FOLDER `Node` to a new remote location.
     *
     * @param \CloudDrive\Node $newFolder
     *
     * @return array
     * @throws \Exception
     */
    public function move(Node $newFolder)
    {
        if (!$newFolder->isFolder()) {
            throw new \Exception("New destination node is not a folder.");
        }

        if (!$this->isFile() && !$this->isFolder()) {
            throw new \Exception("Moving a node can only be performed on FILE and FOLDER kinds.");
        }

        $retval = [
            'success' => false,
            'data'    => [],
        ];

        $response = self::$httpClient->post(
            self::$account->getMetadataUrl() . "nodes/{$newFolder['id']}/children",
            [
                'headers'    => [
                    'Authorization' => 'Bearer ' . self::$account->getToken()['access_token'],
                ],
                'json'       => [
                    'fromParent' => $this['parents'][0],
                    'childId'    => $this['id'],
                ],
                'exceptions' => false,
            ]
        );

        $retval['data'] = json_decode((string)$response->getBody(), true);

        if ($response->getStatusCode() === 200) {
            $retval['success'] = true;
            $this->replace($retval['data']);
            $this->save();
        }

        return $retval;
    }

    /**
     * Replace file contents of the `Node` with the file located at the given
     * local path.
     *
     * @param string $localPath
     *
     * @return array
     */
    public function overwrite($localPath)
    {
        $retval = [
            'success' => false,
            'data'    => [],
        ];

        $response = self::$httpClient->put(
            self::$account->getContentUrl() . "nodes/{$this['id']}/content",
            [
                'headers'    => [
                    'Authorization' => 'Bearer ' . self::$account->getToken()['access_token'],
                ],
                'multipart'  => [
                    [
                        'name'     => 'content',
                        'contents' => fopen($localPath, 'r'),
                    ],
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
     * Modify the name of a remote `Node`.
     *
     * @param string $name
     *
     * @return array
     */
    public function rename($name)
    {
        $retval = [
            'success' => false,
            'data'    => [],
        ];

        $response = self::$httpClient->patch(
            self::$account->getMetadataUrl() . "nodes/{$this['id']}",
            [
                'headers' => [
                    'Authorization' => 'Bearer ' . self::$account->getToken()['access_token'],
                ],
                'json' => [
                    'name' => $name,
                ],
                'exceptions' => false,
            ]
        );

        $retval['data'] = json_decode((string)$response->getBody(), true);

        if ($response->getStatusCode() === 200) {
            $retval['success'] = true;
            $this->replace($retval['data']);
            $this->save();
        }

        return $retval;
    }

    /**
     * Restore the `Node` from the trash.
     *
     * @return array
     */
    public function restore()
    {
        $retval = [
            'success' => false,
            'data'    => [],
        ];

        if ($this['status'] === 'AVAILABLE') {
            $retval['data']['message'] = 'Node is already available.';

            return $retval;
        }

        $response = self::$httpClient->post(
            self::$account->getMetadataUrl() . "trash/{$this['id']}/restore",
            [
                'headers'    => [
                    'Authorization' => 'Bearer ' . self::$account->getToken()['access_token'],
                ],
                'exceptions' => false,
            ]
        );

        $retval['data'] = json_decode((string)$response->getBody(), true);

        if ($response->getStatusCode() === 200) {
            $retval['success'] = true;
            $this->replace($retval['data']);
            $this->save();
        }

        return $retval;
    }

    /**
     * Save the `Node` to the local cache.
     *
     * @return bool
     */
    public function save()
    {
        return self::$cacheStore->saveNode($this);
    }

    /**
     * Find all nodes that contain a string in the name.
     *
     * @param string $name
     *
     * @return array
     */
    public static function searchNodesByName($name)
    {
        return self::$cacheStore->searchNodesByName($name);
    }

    /**
     * Add the `Node` to trash.
     *
     * @return array
     */
    public function trash()
    {
        $retval = [
            'success' => false,
            'data'    => [],
        ];

        if ($this['status'] === 'TRASH') {
            $retval['data']['message'] = 'Node is already in trash.';

            return $retval;
        }

        $response = self::$httpClient->put(
            self::$account->getMetadataUrl() . "trash/{$this['id']}",
            [
                'headers'    => [
                    'Authorization' => 'Bearer ' . self::$account->getToken()['access_token'],
                ],
                'exceptions' => false,
            ]
        );

        $retval['data'] = json_decode((string)$response->getBody(), true);

        if ($response->getStatusCode() === 200) {
            $retval['success'] = true;
            $this->replace($retval['data']);
            $this->save();
        }

        return $retval;
    }
}
