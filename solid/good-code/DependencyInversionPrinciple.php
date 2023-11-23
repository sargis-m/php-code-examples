<?php

namespace Solid\GoodCode;

// Good: High-level modules should not depend on low-level modules

interface Logger
{
    public function log($message);
}

class FileLogger implements Logger
{
    public function log($message)
    {
        // Log to file
    }
}

class UserManager
{
    private $logger;

    public function __construct(Logger $logger)
    {
        $this->logger = $logger;
    }

    public function registerUser($username, $password)
    {
        // Register user logic
        $this->logger->log("User registered: $username");
    }
}
