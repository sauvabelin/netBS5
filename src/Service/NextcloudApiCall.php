<?php

namespace App\Service;

use App\Model\NextcloudDiscussion;
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

    public function query(string $verb, string $path, array $data = []) {

        if ($this->env === 'dev') {
            dump("Nextcloud Query", $verb, $path, $data);
            return null;
        }

        return $this->http->request($verb, $path, [
            'base_uri' => $this->ncUrl,
            'verify_peer' => false,
            'auth_basic' => [$this->ncUser, $this->ncPass],
            'headers' => [
                'OCS-APIRequest' => 'true',
                'Accept' => 'application/json',
            ],
            'json' => $data,
        ]);
    }

    public function runQuery(string $verb, string $path, array $data = []) {
        $res = $this->query($verb, $path, $data);
        $resData = json_decode($res->getContent(true), true);

        if (!$resData['ocs']['meta']['status'] > 299) {
            throw new \Exception("Error in nextcloud response: " . $res->getContent());
        }

        return $resData['ocs']['data'];
    }
}