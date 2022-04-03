<?php

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;

class NextcloudApiCall {

    private $http;
    private $ncUrl;
    private $ncUser;
    private $ncPass;
    private $env;
    
    public function __construct(
        string $ncUrl,
        string $ncUser,
        string $ncPass,
        string $env,
        HttpClientInterface $http)
    {
        $this->http = $http;
        $this->ncUrl = $ncUrl;
        $this->ncUser = $ncUser;
        $this->ncPass = $ncPass;
        $this->env = $env;
    }

    public function query(string $verb, string $path, array $data) {

        if ($this->env === 'dev') {
            dump("Nextcloud Query", $verb, $path, $data);
            return null;
        }

        return $this->http->request($verb, $path, [
            'base_uri' => $this->ncUrl,
            'auth_basic' => [$this->ncUser, $this->ncPass],
            'headers' => [
                'Content-Type' => 'application/json',
                'OCS-APIRequest' => 'true',
            ],
            'json' => $data,
        ]);
    }
}