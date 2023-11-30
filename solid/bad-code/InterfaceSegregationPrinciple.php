<?php

namespace Solid\BadCode;

// Bad: Forcing a class to implement unnecessary methods

interface Worker
{
    public function work();

    public function eat(); // Forcing every worker to implement eating
}

class Robot implements Worker
{
    public function work()
    {
        // Robot working
    }

    public function eat()
    {
        // Robot cannot eat, so this is unnecessary
    }
}
