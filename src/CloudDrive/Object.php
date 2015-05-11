<?php
/**
 * @author Alex Phillips <aphillips@cbcnewmedia.com>
 * Date: 5/9/15
 * Time: 10:47 AM
 */

namespace CloudDrive;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\TransferException;
use GuzzleHttp\Message\RequestInterface;

abstract class Object
{
    /**
     * @var \GuzzleHttp\Client
     */
    protected $httpClient;

    public function __construct()
    {
        $this->httpClient = new Client();
    }

    public function sendRequest(RequestInterface $request)
    {
        $request->getQuery()->setEncodingType('RFC1738');

        try {
            $response = $this->httpClient->send($request);

            return [
                'success' => true,
                'data' => json_decode($response->getBody(), true),
            ];
        } catch (TransferException $e) {
            return [
                'success' => false,
                'data' => json_decode((string)$e->getResponse()->getBody(), true),
            ];
        }
    }
}
