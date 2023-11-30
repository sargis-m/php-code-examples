<?php

namespace Solid\BadCode;

// Bad: A class with multiple responsibilities

class Circle
{
    public function calculateAreaAndPrint($radius)
    {
        $area = 3.14 * $radius ** 2;
        echo "Circle area: $area";
    }
}
