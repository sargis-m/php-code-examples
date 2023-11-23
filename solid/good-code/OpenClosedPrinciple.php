<?php

namespace Solid\GoodCode;

// Good: Open for extension, closed for modification

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

class AreaCalculator
{
    public function calculate(Shape $shape)
    {
        return $shape->calculateArea();
    }
}
