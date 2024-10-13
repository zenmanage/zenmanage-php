<?php

namespace Zenmanage\Shared;

use \GuzzleHttp\Exception\ClientException;
use \Zenmanage\Exceptions\FlagNotFoundException;
use \Zenmanage\Exceptions\InvalidTokenException;

class HttpClient
{
    private $client;
    private $config;

    public function __construct($config)
    {
        $this->config = $config;

        $options = array_merge($config->get('guzzle'), [
            'base_uri' => $config->get('api_endpoint')
        ]);

        $this->client = new \GuzzleHttp\Client($options);
    }

    public function get($endpoint, array $headers = null)
    {
        return $this->request('GET', $endpoint, null, $headers);
    }

    public function put($endpoint, $payload = [])
    {
        return $this->request('PUT', $endpoint, $payload);
    }

    public function post($endpoint, $payload = [])
    {
        return $this->request('POST', $endpoint, $payload);
    }

    public function delete($endpoint, $payload = [])
    {
        return $this->request('DELETE', $endpoint, $payload);
    }

    private function request($method, $endpoint, $payload = null, array $headers = null)
    {
        $options = [
            'body' => $payload != null ? json_encode($payload) : null,
            'headers' => [
                'Accept' => 'application/json',
                'Content-Type' => 'application/json; charset=UTF-8',
                'X-API-KEY' => $this->config->get('environment_token'),
                ...$headers != null ? $headers : [],
            ]
        ];

        $endpoint = "v1{$endpoint}";

        try
        {
            $response = $this->client->request($method, $endpoint, $options);
            return $response->getBody()->getContents();
        }
        catch (ClientException $e)
        {
            $response = $e->getResponse();

            switch ($response->getStatusCode())
            {
                case 401:
                    throw new InvalidTokenException($e);
                    
                case 404:
                    throw new FlagNotFoundException($e);

                default:
                    throw $e;
            }
        }
    }
}
