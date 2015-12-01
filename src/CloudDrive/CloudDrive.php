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
    private $httpClient;

    /**
     * @var string
     */
    private $clientId;

    /**
     * @var string
     */
    private $clientSecret;

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
        $this->httpClient = new Client();

        if (is_null($account)) {
            $account = new Account($this->email, $this->clientId, $this->clientSecret, $cacheStore);
        }

        $this->account = $account;
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
            'success'      => true,
            'data'         => [],
            'resonse_code' => null,
        ];

        $path = $this->getPathArray($path);
        $previousNode = Node::loadRoot();

        $match = null;
        foreach ($path as $index => $folder) {
            $xary = array_slice($path, 0, $index + 1);
            if (!($match = Node::loadByPath(implode('/', $xary)))) {
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
            'success'       => false,
            'data'          => [],
            'response_code' => null,
        ];

        if (is_null($parents)) {
            $parents = Node::loadRoot()['id'];
        }

        if (!is_array($parents)) {
            $parents = [$parents];
        }

        $response = $this->httpClient->post(
            "{$this->account->getMetadataUrl()}nodes",
            [
                'headers'    => [
                    'Authorization' => "Bearer {$this->account->getToken()["access_token"]}",
                ],
                'json'       => [
                    'name'    => $name,
                    'parents' => $parents,
                    'kind'    => 'FOLDER',
                ],
                'exceptions' => false,
            ]
        );

        $retval['data'] = json_decode((string)$response->getBody(), true);

        if (($retval['response_code'] = $response->getStatusCode()) === 201) {
            $retval['success'] = true;
            (new Node($retval['data']))->save();
        }

        return $retval;
    }

    /**
     * Retrieve the associated `Account` object.
     *
     * @return \CloudDrive\Account
     */
    public function getAccount()
    {
        return $this->account;
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
        if (is_null($file = Node::loadByPath($remotePath))) {
            if (!is_null($localPath)) {
                if (!empty($nodes = Node::loadByMd5(md5_file($localPath)))) {
                    $ids = [];
                    foreach ($nodes as $node) {
                        $ids[] = $node['id'];
                    }

                    return [
                        'success' => true,
                        'data'    => [
                            'message'    => "File(s) with same MD5: " . implode(', ', $ids),
                            'path_match' => false,
                            'md5_match'  => true,
                            'nodes'      => $nodes,
                        ],
                    ];
                }
            }

            return [
                'success' => false,
                'data'    => [
                    'message'    => "File $remotePath does not exist.",
                    'path_match' => false,
                    'md5_match'  => false,
                ]
            ];
        }

        $retval = [
            'success' => true,
            'data'    => [
                'message'    => "File $remotePath exists.",
                'path_match' => true,
                'md5_match'  => false,
                'node'       => $file,
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
     * Upload a local directory to Amazon Cloud Drive.
     *
     * @param string        $localPath    Local path of directory to upload
     * @param string        $remoteFolder Remote folder to place the directory in
     * @param bool          $overwrite    Flag to overwrite files if they exist remotely
     * @param callable|null $callback     Callable to perform after each file upload
     *
     * @return array
     * @throws \Exception
     */
    public function uploadDirectory($localPath, $remoteFolder, $overwrite = false, $callback = null)
    {
        $localPath = realpath($localPath);

        $remoteFolder = $this->getPathArray($remoteFolder);
        $tmp = $this->getPathArray($localPath);
        $remoteFolder[] = array_pop($tmp);
        $remoteFolder = $this->getPathString($remoteFolder);

        $retval = [];

        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($localPath),
            \RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($files as $name => $file) {
            if (is_dir($file)) {
                continue;
            }

            $info = pathinfo($file);
            $remotePath = str_replace($localPath, $remoteFolder, $info['dirname']);

            $attempts = 0;
            while (true) {
                if ($attempts > 1) {
                    throw new \Exception(
                        "Failed to upload file '{$file->getPathName()}' after reauthentication. " .
                        "Upload may take longer than the access token is valid for."
                    );
                }

                $response = $this->uploadFile($file->getPathname(), $remotePath, $overwrite);

                if ($response['success'] === false && $response['response_code'] === 401) {
                    $auth = $this->account->authorize();
                    if ($auth['success'] === false) {
                        throw new \Exception("Failed to renew account authorization.");
                    }

                    $attempts++;
                    continue;
                }

                break;
            }

            if (is_callable($callback)) {
                call_user_func($callback, $response, [
                    'file'          => $file,
                    'local_path'    => $localPath,
                    'name'          => $name,
                    'remote_folder' => $remoteFolder,
                    'remote_path'   => $remotePath,
                ]);
            }

            $retval[] = $response;
        }

        return $retval;
    }

    /**
     * Upload a single file to Amazon Cloud Drive.
     *
     * @param string     $localPath     The local path to the file to upload
     * @param string     $remotePath    The remote folder to upload the file to
     * @param bool|false $overwrite     Whether to overwrite the file if it already
     *                                  exists remotely
     * @param bool       $suppressDedup Disables checking for duplicates when uploading
     *
     * @return array
     */
    public function uploadFile($localPath, $remotePath, $overwrite = false, $suppressDedup = false)
    {
        $retval = [
            'success'       => false,
            'data'          => [],
            'response_code' => null,
        ];

        $info = pathinfo($localPath);
        $remotePath = $this->getPathString($this->getPathArray($remotePath));

        if (!($remoteFolder = Node::loadByPath($remotePath))) {
            $response = $this->createDirectoryPath($remotePath);
            if ($response['success'] === false) {
                return $response;
            }

            $remoteFolder = $response['data'];
        }

        $response = $this->nodeExists("$remotePath/{$info['basename']}", $localPath);
        if ($response['success'] === true) {
            $pathMatch = $response['data']['path_match'];
            $md5Match = $response['data']['md5_match'];

            if ($pathMatch === true && $md5Match === true) {
                // Skip if path and MD5 match
                $retval['data'] = $response['data'];

                return $retval;
            } else if ($pathMatch === true && $md5Match === false) {
                // If path is the same and checksum differs, only overwrite
                if ($overwrite === true) {
                    return $response['data']['node']->overwrite($localPath);
                }

                $retval['data'] = $response['data'];

                return $retval;
            } else if ($pathMatch === false && $md5Match === true) {
                // If path differs and checksum is the same, check for dedup
                if ($suppressDedup === false) {
                    $retval['data'] = $response['data'];

                    return $retval;
                }
            }
        }

        $suppressDedup = $suppressDedup ? '?suppress=deduplication' : '';

        $response = $this->httpClient->post(
            "{$this->account->getContentUrl()}nodes{$suppressDedup}",
            [
                'headers'    => [
                    'Authorization' => "Bearer {$this->account->getToken()['access_token']}",
                ],
                'multipart'  => [
                    [
                        'name'     => 'metadata',
                        'contents' => json_encode(
                            [
                                'kind'    => 'FILE',
                                'name'    => $info['basename'],
                                'parents' => [
                                    $remoteFolder['id'],
                                ]
                            ]
                        ),
                    ],
                    [
                        'name'     => 'contents',
                        'contents' => fopen($localPath, 'r'),
                    ],
                ],
                'exceptions' => false,
            ]
        );

        $retval['data'] = json_decode((string)$response->getBody(), true);
        $retval['response_code'] = $response->getStatusCode();

        if (($retval['response_code'] = $response->getStatusCode()) === 201) {
            $retval['success'] = true;
            (new Node($retval['data']))->save();
        }

        return $retval;
    }
}
