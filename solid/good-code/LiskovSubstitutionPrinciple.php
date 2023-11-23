<?php

namespace Solid\GoodCode;

// Good: Subtypes must be substitutable for their base types

interface Shape
{
    public function calculateArea();
}

class Circle implements Shape
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

class Square implements Shape
{
    private $side;

    public function __construct($side)
    {
        $this->side = $side;
    }

    public function calculateArea()
    {
        return $this->side ** 2;
    }
}
