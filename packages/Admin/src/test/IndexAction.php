<?php

namespace Skolica\Admin\test;

use GuzzleHttp\Client;

class IndexAction
{
    private $httpClient;

    public function __construct(Client $httpClient)
    {
        $this->httpClient = $httpClient;
    }

    public function __invoke()
    {
        return 'hello world';
    }
}