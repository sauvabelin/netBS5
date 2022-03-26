<?php

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;

class NextcloudApiCall {

    private $http;
    private $ncUrl;
    private $ncUser;
    private $ncPass;
    
    public function __construct(
        string $ncUrl,
        string $ncUser,
        string $ncPass,
        HttpClientInterface $http)
    {
        $this->http = $http;
        $this->ncUrl = $ncUrl;
        $this->ncUser = $ncUser;
        $this->ncPass = $ncPass;
    }

    public function getClient() {
        return $this->http->withOptions([
            'base_uri' => $this->ncUrl,
            'auth_basic' => [$this->ncUser, $this->ncPass],
            'headers' => [
                'Content-Type' => 'application/json',
                'OCS-APIRequest' => 'true',
            ]
        ]);
    }
}