<?php

namespace Solid\GoodCode;

// Good: Each class has a single responsibility

class Circle
{
    public function calculateArea($radius)
    {
        return 3.14 * $radius ** 2;
    }
}

class CirclePrinter
{
    public function printArea($radius)
    {
        $circle = new Circle();
        $area = $circle->calculateArea($radius);
        echo "Circle area: $area";
    }
}
