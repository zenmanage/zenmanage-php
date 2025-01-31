<?php

namespace Zenmanage;

use \Zenmanage\Flags\Flags;
use \Zenmanage\Shared\HttpClient;

class Zenmanage
{    
    public Flags $flags;

    public function __construct($config = null)
    {
        $config = new Config($config);
        $client = new HttpClient($config);

        $this->flags = new Flags($client);
    }
}
