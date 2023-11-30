<?php

namespace Solid\BadCode;

// Bad: Violating LSP by breaking the substitution principle

class Circle
{
    private $radius;

    public function __construct($radius)
    {
        $this->radius = $radius;
    }

    public function calculateArea()
    {
        return 3.14 * $this->radius ** 2;
    }
}

class Square extends Circle
{
    // This violates LSP, as a Square is not substitutable for a Circle
}
