<?php

namespace Solid\BadCode;

// Bad: High-level module depending on low-level module

class FileLogger
{
    public function log($message)
    {
        // Log to file
    }
}

class UserManager
{
    private $logger;

    // High-level module UserManager depends on a low-level module FileLogger
    public function __construct()
    {
        $this->logger = new FileLogger();
    }

    public function registerUser($username, $password)
    {
        // Register user logic
        $this->logger->log("User registered: $username");
    }
}
