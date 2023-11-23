<?php

namespace Solid\GoodCode;

// Good: No client should be forced to depend on methods it does not use

interface Worker
{
    public function work();
}

interface Eater
{
    public function eat();
}

class HumanWorker implements Worker, Eater
{
    public function work()
    {
        // Human working
    }

    public function eat()
    {
        // Human eating
    }
}
