# CloudDrive PHP

CloudDrive-PHP is an SDK and CLI application for interacting with Amazon's [Cloud Drive](https://www.amazon.com/clouddrive/home).

The project originally started out as an application to manage storage in Cloud Drive from the command line but figured other's may want to take advantage of the API calls and develop their own software using it, so I made sure to build the library so it can be built upon and make the CLI application an included tool that could also be used as an example for implementation.

## SDK Usage

### SDK Responses

All of the method calls return a reponse in a REST API-like structure with the exception of those methods that return `Node` objects.

Example response:
```php
array(
    'result' => true,
    'data' => [
        'message' => 'The response was successful'
    ]
)
```

Every API-like reponse will have 2 keys: `success` and `data`. `Success` is a boolean on whether or not the request was completed successfully and the data contains various information related to the request.

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
