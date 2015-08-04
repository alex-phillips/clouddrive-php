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
            'success' => true,
            'data' => [],
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
            'success' => false,
            'data' => [],
        ];

        if (is_null($parents)) {
            $parents = Node::loadRoot()['id'];
        }

        if (!is_array($parents)) {
            $parents = [$parents];
        }

        $response = $this->httpClient->post("{$this->account->getMetadataUrl()}nodes", [
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
            (new Node($retval['data']))->save();
        }

        return $retval;
    }

    /**
     * Download contents of remote file node to local save path. If only the
     * local directory is given, the file will be saved as its remote name.
     *
     * @param Node   $node     The remote node to download
     * @param string $savePath The local path to save the contents to
     *
     * @return array
     */
    public function downloadFile($node, $savePath)
    {
        $retval = [
            'success' => false,
            'data' => []
        ];

        if (is_string($node)) {
            if (!($match = Node::loadByPath($node)) && (!$match = Node::loadById($node))) {
                $retval['data']['message'] = "No node found with path or ID of $node";

                return $retval;
            }

            $node = $match;
        }

        if (file_exists($savePath) && is_dir($savePath)) {
            $savePath = rtrim($savePath, '/') . "/{$node['name']}";
        }

        $response = $this->httpClient->get("{$this->account->getContentUrl()}nodes/{$node['id']}/content", [
            'headers' => [
                'Authorization' => "Bearer {$this->account->getToken()['access_token']}",
            ],
            'stream' => true,
            'exceptions' => false,
        ]);

        if ($response->getStatusCode() !== 200) {
            $retval['data'] = json_decode((string)$response->getBody(), true);

            return $retval;
        }

        $retval['success'] = true;

        $handle = fopen($savePath, 'a');
        $body = $response->getBody();
        while (!$body->eof()) {
            fwrite($handle, $body->read(1024));
        }

        fclose($handle);

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
                if (!is_null($file = Node::loadByMd5(md5_file($localPath)))) {
                    $path = $file->getPath();

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

        $response = $this->httpClient->put("{$this->account->getContentUrl()}nodes/{$remoteNode['id']}/content", [
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

            if ($outputProgress === true) {
                if ($response['success'] === true) {
                    echo "Successfully uploaded file $file: " . json_encode($response['data']) . "\n";
                } else {
                    echo "Failed to upload file $file: " . json_encode($response) . "\n";
                }
            }

            $retval[] = $response;
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
            'response_code' => null,
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

        $response = $this->httpClient->post("{$this->account->getContentUrl()}nodes", [
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
        $retval['response_code'] = $response->getStatusCode();

        if ($response->getStatusCode() === 201) {
            $retval['success'] = true;
            (new Node($retval['data']))->save();
        }

        return $retval;
    }
}
