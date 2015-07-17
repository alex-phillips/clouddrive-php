<?php
/**
 * @author Alex Phillips <ahp118@gmail.com>
 * Date: 7/10/15
 * Time: 3:38 PM
 */

namespace CloudDrive;

use GuzzleHttp\Client;

/**
 * Class that handles all communication for accessing and altering nodes,
 * retrieving account information, and managing the local cache store.
 *
 * @package CloudDrive
 */
class CloudDrive
{
    /**
     * @var \CloudDrive\Account
     */
    private $account;

    /**
     * @var \GuzzleHttp\Client
     */
    private $client;

    /**
     * @var string
     */
    private $clientId;

    /**
     * @var string
     */
    private $clientSecret;

    /**
     * @var \CloudDrive\Cache
     */
    private $cache;

    /**
     * @var string
     */
    private $email;

    /**
     * Construct a new instance of `CloudDrive`. This handles all communication
     * for accessing and altering nodes, retrieving account information, and
     * managing the local cache store.
     *
     * @param string       $email        The email for the account to connec to
     * @param string       $clientId     Amazon CloudDrive API client ID credential
     * @param string       $clientSecret Amazon CloudDrive API client secret credential
     * @param Cache        $cacheStore   Local cache storage object
     * @param Account|null $account      `Account` object. If not passed in, this will
     *                                   be created using the email and credentials used
     *                                   here
     */
    public function __construct($email, $clientId, $clientSecret, Cache $cacheStore, Account $account = null)
    {
        $this->email = $email;
        $this->clientId = $clientId;
        $this->clientSecret = $clientSecret;
        $this->cache = $cacheStore;

        $this->client = new Client();

        if (is_null($account)) {
            $account = new Account($this->email, $this->clientId, $this->clientSecret, $this->cache);
        }

        $this->account = $account;
    }

    /**
     * Build and return the remote directory path of the given node.
     *
     * @param \CloudDrive\Node $node The node to build the path for
     *
     * @return string
     * @throws \Exception
     */
    private function buildNodePath(Node $node)
    {
        $path = [];

        while (true) {
            $path[] = $node["name"];
            if ($node["isRoot"] === true) {
                break;
            }

            $node = $this->findNodeById($node["parents"][0]);
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
     * Recursively create a remote directory path. If parts of the path already
     * exist, it will continue until the entire path exists.
     *
     * @param string $path The directory path to create
     *
     * @return array
     * @throws \Exception
     */
    public function createDirectoryPath($path)
    {
        $retval = [
            'success' => true,
            'data' => [],
        ];

        $path = $this->getPathArray($path);
        $previousNode = $this->getRootNode();

        $match = null;
        foreach ($path as $index => $folder) {
            $xary = array_slice($path, 0, $index + 1);
            if (!($match = $this->findNodeByPath(implode('/', $xary)))) {
                $response = $this->createFolder($folder, $previousNode['id']);
                if (!$response['success']) {
                    return $response;
                }

                $match = $response['data'];
            }

            $previousNode = $match;
        }

        if (is_null($match)) {
            $retval['data'] = $previousNode;
        } else {
            $retval['data'] = $match;
        }

        return $retval;
    }

    /**
     * Create a new remote node nested under the provided parents (created under
     * root node if none given).
     *
     * @param string $name    Name of the new remote folder
     * @param null   $parents Parent IDs to give the folder
     *
     * @return array
     * @throws \Exception
     */
    public function createFolder($name, $parents = null)
    {
        $retval = [
            'success' => false,
            'data' => [],
        ];

        if (is_null($parents)) {
            $parents = $this->getRootNode()['id'];
        }

        if (!is_array($parents)) {
            $parents = [$parents];
        }

        $response = $this->client->post("{$this->account->getMetadataUrl()}nodes", [
            'headers' => [
                'Authorization' => "Bearer {$this->account->getToken()["access_token"]}",
            ],
            'json' => [
                'name' => $name,
                'parents' => $parents,
                'kind' => 'FOLDER',
            ],
            'exceptions' => false,
        ]);

        $retval['data'] = json_decode((string)$response->getBody(), true);

        if ($response->getStatusCode() === 201) {
            $retval['success'] = true;
            $this->cache->saveNode(new Node($retval['data']));
        }

        return $retval;
    }

    /**
     * Find and return nodes that have the given MD5.
     *
     * @param string $md5 MD5 checksum of the node
     *
     * @return mixed
     */
    public function findNodeByMd5($md5)
    {
        return $this->cache->findNodeByMd5($md5);
    }

    /**
     * Find and return any nodes that match the given name.
     *
     * @param string $name Name of the node to find
     *
     * @return mixed
     */
    public function findNodesByName($name)
    {
        return $this->cache->findNodesByName($name);
    }

    /**
     * Find and return the node matching the given ID.
     *
     * @param string $id ID of the node
     *
     * @return mixed
     */
    public function findNodeById($id)
    {
        return $this->cache->findNodeById($id);
    }

    /**
     * @param $path
     *
     * @return null|\CloudDrive\Node
     * @throws \Exception
     */
    public function findNodeByPath($path)
    {
        $path = trim($path, '/');
        if (!$path) {
            return $this->getRootNode();
        }

        $info = pathinfo($path);
        $nodes = $this->findNodesByName($info['basename']);
        if (empty($nodes)) {
            return null;
        }

        foreach ($nodes as $node) {
            if ($this->buildNodePath($node) === $path) {
                return $node;
            }
        }

        return null;
    }

    /**
     * Retrieve the associated `Account` object
     *
     * @return \CloudDrive\Account
     */
    public function getAccount()
    {
        return $this->account;
    }

    /**
     * Get all children of the given `Node`.
     *
     * @param string|\CloudDrive\Node $node The node to get children of
     *
     * @return mixed
     */
    public function getChildren($node)
    {
        if (!($node instanceof Node)) {
            $node = $this->findNodeByPath($node);
        }

        return $this->cache->getNodeChildren($node);
    }

    /**
     * Convert a given path string into an array of directory names.
     *
     * @param string|array $path
     *
     * @return array
     */
    public function getPathArray($path)
    {
        if (is_array($path)) {
            return $path;
        }

        return array_filter(explode('/', $path));
    }

    /**
     * Properly format a string or array of folders into a path string.
     *
     * @param string|array $path The remote path to format
     *
     * @return string
     */
    public function getPathString($path)
    {
        if (is_string($path)) {
            return trim($path, '/');
        }

        return trim(implode('/', $path));
    }

    /**
     * Return the root node.
     *
     * @return \CloudDrive\Node
     * @throws \Exception
     */
    public function getRootNode()
    {
        $results = $this->findNodesByName('ROOT');
        if (empty($results)) {
            throw new \Exception("Node node by name 'ROOT' found in the database.");
        }

        foreach ($results as $result) {
            if ($result["isRoot"] === true) {
                return $result;
            }
        }

        throw new \Exception("Unable to find root node.");
    }

    /**
     * Determine if a node matching the given path exists remotely. If a local
     * path is given, the MD5 will be compared as well.
     *
     * @param string      $remotePath The remote path to check
     * @param null|string $localPath  Local path of file to compare MD5
     *
     * @return array
     * @throws \Exception'
     */
    public function nodeExists($remotePath, $localPath = null)
    {
        if (is_null($file = $this->findNodeByPath($remotePath))) {
            if (!is_null($localPath)) {
                if (!is_null($file = $this->findNodeByMd5(md5_file($localPath)))) {
                    $path = $this->buildNodePath($file);

                    return [
                        'success' => true,
                        'data' => [
                            'message' => "File with same MD5 exists at $path: " . json_encode($file),
                            'path_match' => false,
                            'md5_match' => true,
                        ],
                    ];
                }
            }

            return [
                'success' => false,
                'data' => [
                    'message' => "File $remotePath does not exist.",
                    'path_match' => false,
                    'md5_match' => false,
                ]
            ];
        }

        $retval = [
            'success' => true,
            'data' => [
                'message' => "File $remotePath exists.",
                'path_match' => true,
                'md5_match' => false,
                'node' => $file,
            ],
        ];

        if (!is_null($localPath)) {
            if (!is_null($file['contentProperties']['md5'])) {
                if (md5_file($localPath) !== $file['contentProperties']['md5']) {
                    $retval['data']['message'] = "File $remotePath exists but does not match local checksum.";
                } else {
                    $retval['data']['message'] = "File $remotePath exists and is identical to local copy.";
                    $retval['data']['md5_match'] = true;
                }
            } else {
                $retval['data']['message'] = "File $remotePath exists but no checksum is available.";
            }
        }

        return $retval;
    }

    /**
     * Overwrite remote node with the file located at the given local path.
     *
     * @param string           $localPath  Local path of file to overwrite remote node
     *                                     node with
     * @param \CloudDrive\Node $remoteNode Remote node to overwrite
     *
     * @return array
     */
    public function overwriteFile($localPath, Node $remoteNode)
    {
        $retval = [
            'success' => false,
            'data' => [],
        ];

        $response = $this->client->put("{$this->account->getContentUrl()}nodes/{$remoteNode['id']}/content", [
            'headers' => [
                'Authorization' => "Bearer {$this->account->getToken()['access_token']}",
            ],
            'multipart' => [
                [
                    'name' => 'content',
                    'contents' => fopen($localPath, 'r'),
                ],
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
     * Upload a local directory to Amazon Cloud Drive.
     *
     * @param string     $localPath      Local path of directory to upload
     * @param string     $remoteFolder   Remote folder to place the directory in
     * @param bool|false $overwrite      Flag to overwrite files if they exist remotely
     * @param bool|false $outputProgress `echo` out progress of each file
     *
     * @return array
     * @throws \Exception
     */
    public function uploadDirectory($localPath, $remoteFolder, $overwrite = false, $outputProgress = false)
    {
        $localPath = realpath($localPath);

        $remoteFolder = $this->getPathArray($remoteFolder);
        $tmp = $this->getPathArray($localPath);
        $remoteFolder[] = array_pop($tmp);
        $remoteFolder = $this->getPathString($remoteFolder);

        $retval = [];

        $di = new \RecursiveDirectoryIterator($localPath);
        foreach (new \RecursiveDirectoryIterator($di) as $file) {
            if (is_dir($file)) {
                continue;
            }

            $info = pathinfo($file);
            $remotePath = str_replace($localPath, $remoteFolder, $info['dirname']);

            $response = $this->uploadFile($file->getPathname(), $remotePath, $overwrite);
            if ($outputProgress === true) {
                if ($response['success'] === true) {
                    echo "Successfully uploaded file $file: " . json_encode($response['data']) . "\n";
                } else {
                    echo "Failed to upload file $file: " . json_encode($response['data']) . "\n";
                }
            }

            $retval[] = $response;

            /*
             * Since uploading a directory can take a while (depending on number/size of files)
             * we will check if we need to renew our authorization after each file upload to
             * make sure our authentication doesn't expire.
             */
            if (time() - $this->account->getToken()['last_authorized'] > $this->account->getToken()['expires_in']) {
                $response = $this->account->renewAuthorization();
                if ($response['success'] === false) {
                    throw new \Exception("Failed to renew account authorization.");
                }
            }
        }

        return $retval;
    }

    /**
     * Upload a single file to Amazon Cloud Drive.
     *
     * @param string     $localPath  The local path to the file to upload
     * @param string     $remotePath The remote folder to upload the file to
     * @param bool|false $overwrite  Whether to overwrite the file if it already
     *                               exists remotely
     *
     * @return array
     */
    public function uploadFile($localPath, $remotePath, $overwrite = false)
    {
        $retval = [
            'success' => false,
            'data' => [],
        ];

        $info = pathinfo($localPath);
        $remotePath = $this->getPathString($this->getPathArray($remotePath));

        $response = $this->createDirectoryPath($remotePath);
        if ($response['success'] === false) {
            return $response;
        }

        $remoteFolder = $response['data'];

        $response = $this->nodeExists("$remotePath/{$info['basename']}", $localPath);
        if ($response['success'] === true) {
            if ($overwrite === false) {
                $retval['data'] = $response['data'];

                return $retval;
            }

            if ($response['data']['md5_match'] === true) {
                if ($remotePath === '') {
                    $remotePath = '/';
                }
                $retval['data']['message'] = "Identical file exists at $remotePath";

                return $retval;
            }

            return $this->overwriteFile($localPath, $response['data']['node']);
        }

        $response = $this->client->post("{$this->account->getContentUrl()}nodes", [
            'headers' => [
                'Authorization' => "Bearer {$this->account->getToken()['access_token']}",
            ],
            'multipart' => [
                [
                    'name' => 'metadata',
                    'contents' => json_encode([
                        'kind' => 'FILE',
                        'name' => $info['basename'],
                        'parents' => [
                            $remoteFolder['id'],
                        ]
                    ]),
                ],
                [
                    'name' => 'contents',
                    'contents' => fopen($localPath, 'r'),
                ],
            ],
            'exceptions' => false,
        ]);

        $retval['data'] = json_decode((string)$response->getBody(), true);

        if ($response->getStatusCode() === 201) {
            $retval['success'] = true;
            $this->cache->saveNode(new Node($retval['data']));
        }

        return $retval;
    }
}
