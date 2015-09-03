# CloudDrive PHP

NOTE: This project is in active development.

CloudDrive-PHP is an SDK and CLI application for interacting with Amazon's [Cloud Drive](https://www.amazon.com/clouddrive/home).

The project originally started out as an application to manage storage in Cloud Drive from the command line but figured other's may want to take advantage of the API calls and develop their own software using it, so I made sure to build the library so it can be built upon and make the CLI application an included tool that could also be used as an example for implementation.

## Install

Via Composer (for use in a project)

```
$ composer require alex-phillips/clouddrive
```

Install globally to run the CLI from any location (as long as the global composer `bin` directory is in your `$PATH`).

```
$ composer global require alex-phillips/clouddrive
```

## CLI

### Setup

The first run of the CLI needs to authenticate your Amazon Cloud Drive account with the application using your Amazon Cloud Drive credentials. Use the `config` command to set these credentials as well as the email associated with your Amazon account.

```
$ clouddrive config email me@example.com
$ clouddrive config client-id CLIENT_ID
$ clouddrive config client-secret CLIENT_SECRET
```

Once the credentials are set, simply run the `init` command. This will provide you with an authentication URL to visit. Paste the URL you are redirected to into the terminal and press enter.

```
$ clouddrive init
Initial authorization required.
Navigate to the following URL and paste in the redirect URL here.
https://www.amazon.com/ap/oa?client_id=CLIENT_ID&scope=clouddrive%3Aread_all%20clouddrive%3Awrite&response_type=code&redirect_uri=http://localhost
...
Successfully authenticated with Amazon CloudDrive.
```

After you have been authenticated, run the `sync` command to sync your local cache with the current state of your Cloud Drive.

### Usage

The CLI application relies on your local cache being in sync with your Cloud Drive, so run the `sync` command periodically to retrieve any changes since the last sync. You can view all available commands and usage of each command using the `-h` flag at any point.

```
Cloud Drive version 0.1.0

Usage:
  command [options] [arguments]

Options:
  -h, --help            Display this help message
  -q, --quiet           Do not output any message
  -V, --version         Display this application version
      --ansi            Force ANSI output
      --no-ansi         Disable ANSI output
  -n, --no-interaction  Do not ask any interactive question
  -v|vv|vvv, --verbose  Increase the verbosity of messages: 1 for normal output, 2 for more verbose output and 3 for debug

Available commands:
  cat         Output a file to the standard output stream
  clear-cache Clear the local cache
  clearcache  Clear the local cache
  config      Read, write, and remove config options
  download    Download remote file or folder to specified local path
  du          Display disk usage for the given node
  find        Find nodes by name or MD5 checksum
  help        Displays help for a command
  init        Initialize the command line application for use with an Amazon account
  link        Generate a temporary, pre-authenticated download link
  list        Lists commands
  ls          List all remote nodes inside of a specified directory
  metadata    Retrieve the metadata (JSON) of a node by its path
  mkdir       Create a new remote directory given a path
  mv          Move a node to a new remote folder
  pending     List the nodes that have a status of 'PENDING'
  quota       Show Cloud Drive quota
  rename      Rename remote node
  renew       Renew authorization
  resolve     Return a node's remote path by its ID
  restore     Restore a remote node from the trash
  rm          Move a remote Node to the trash
  sync        Sync the local cache with Amazon CloudDrive
  trash       List the nodes that are in trash
  tree        Print directory tree of the given node
  upload      Upload local file or folder to remote directory
  usage       Show Cloud Drive usage
 ```

## SDK

### SDK Responses

All of the method calls return a reponse in a REST API-like structure with the exception of those methods that return `Node` objects.

Example response:
```php
[
    'result' => true,
    'data' => [
        'message' => 'The response was successful'
    ]
]
```

Every API-like reponse will have at least 2 keys: `success` and `data`. `Success` is a boolean on whether or not the request was completed successfully and the data contains various information related to the request.

** NOTE: ** The response for `nodeExists` will return `success = true` if the node exists, `false` if the node doesn't exist.

Various method calls that return `Node` objects such as `findNodeByPath` and `findNodeById` will return either a `Node` object or `null` if the node was not found.

### Getting started

The first thing to do is create a new `CloudDrive` object using the email of the account you wish you talk with and the necessary API credentials for Cloud Drive and a `Cache` object for storing local data. (Currently there is only a SQLite cache store).

```php
$clouddrive = new CloudDrive\CloudDrive($email, $clientId, $clientSecret, new \CloudDrive\Cache\SQLite($email));
$response = $clouddrive->getAccount()->authorize();
```

The first time you go to authorize the account, the `response` will "fail" and the `data` key in the response will contain an `auth_url`. This is required during the initial authorization to grant access to your application with Cloud Drive. Simply navigate to the URL, you will be redirected to a "localhost" URL which will contain the necessary code for access.

Next, call authorization once more with the redirected URL passed in:

```php
$clouddrive = new CloudDrive\CloudDrive($email, $clientId, $clientSecret, new \CloudDrive\Cache\SQLite($email));
$response = $clouddrive->getAccount()->authorize($redirectUrl);
```

The response will now be successful and the access token will be stored in the cache. From now on, when the account needs to renew its authorization, it will do so automatically with its 'refresh token' inside of the `authorize` method.

### Local Cache

There is currently support for MySQL (and MariaDB) and SQLite3 for the local cache store. Simply instantiate these with the necessary parameters. If you are using MySQL, make sure the database is created. The initialization of the cache store will automatically create the necessary tables.

```
$cacheStore = new \CloudDrive\Cache\SQLite('my-cache', './.cache');
```

### Node

Once you have authenticated the `Account` object and created a local cache, initialize the `Node` object to utilize these.


```
Node::init($account, $cache);
```

Now all static `Node` methods will be available to retrieve, find, and manipulate `Node` objects.

```
$results = Node::loadByName('myfile.txt');
```

Various `Node` methods will either return an array if multiple nodes are able to be returned and a `Node` object if only 1 is meant to be returned (i.e., lookup by ID). If no nodes are found, then the methods will return an empty array or `null` value respectively.

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) for details.

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
