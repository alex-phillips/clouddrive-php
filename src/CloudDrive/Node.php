<?php
/**
 * @author Alex Phillips <ahp118@gmail.com>
 * Date: 7/10/15
 * Time: 3:36 PM
 */

namespace CloudDrive;

use ArrayAccess;
use GuzzleHttp\Client;
use IteratorAggregate;
use JsonSerializable;
use Countable;
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
     * @var \CloudDrive\Account
     */
    protected static $account;

    /**
     * @var \CloudDrive\Cache
     */
    protected static $cacheStore;

    /**
     * @var \GuzzleHttp\Client
     */
    protected static $httpClient;

    /**
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
     * Get all children of the given `Node`.
     *
     * @return array
     */
    public function getChildren()
    {
        return self::$cacheStore->getNodeChildren($this);
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
            if ($node["isRoot"] === true) {
                break;
            }

            $node = self::loadById($node["parents"][0]);
            if (is_null($node)) {
                throw new \Exception("No parent node found with ID {$node['parents'][0]}.");
            }

            if ($node['isRoot'] === true) {
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
     * Load a `Node` given an ID, MD5, or remote path.
     *
     * @param string $param Parameter to find the `Node` by: ID, MD5, or path
     *
     * @return \CloudDrive\Node|null
     */
    public static function load($param)
    {
        if (!($node = self::loadById($param))) {
            if (!($node = self::loadByMd5($param))) {
                if (!($node = self::loadByPath($param))) {
                    $node = null;
                }
            }
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
     * Find and return `Nodes` that have the given MD5.
     *
     * @param string $md5 MD5 checksum of the node
     *
     * @return \CloudDrive\Node|null
     */
    public static function loadByMd5($md5)
    {
        return self::$cacheStore->findNodeByMd5($md5);
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
        $nodes = self::$cacheStore->findNodesByName($info['basename']);
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
        $results = self::$cacheStore->findNodesByName('ROOT');
        if (empty($results)) {
            throw new \Exception("No node by name 'ROOT' found in the database.");
        }

        foreach ($results as $result) {
            if ($result["isRoot"] === true) {
                return $result;
            }
        }

        throw new \Exception("Unable to find root node.");
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

        $response = self::$httpClient->post(self::$account->getMetadataUrl() . "trash/{$this['id']}/restore", [
            'headers'    => [
                'Authorization' => 'Bearer ' . self::$account->getToken()['access_token'],
            ],
            'exceptions' => false,
        ]);

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

        $response = self::$httpClient->put(self::$account->getMetadataUrl() . "trash/{$this['id']}", [
            'headers'    => [
                'Authorization' => 'Bearer ' . self::$account->getToken()['access_token'],
            ],
            'exceptions' => false,
        ]);

        $retval['data'] = json_decode((string)$response->getBody(), true);

        if ($response->getStatusCode() === 200) {
            $retval['success'] = true;
            $this->replace($retval['data']);
            $this->save();
        }

        return $retval;
    }
}
