<?php

namespace App\Manager;

use Psr\Log\LoggerInterface;

class BreadcrumbManager
{

    private $logger;

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }
}
