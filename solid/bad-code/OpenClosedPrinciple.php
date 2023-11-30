<?php

namespace Solid\BadCode;

// Bad: Modifying existing class instead of extending

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

    // This violates the OCP if we keep modifying the class for new shapes
    public function calculateVolume()
    {
        return 0; // Volume not applicable for a circle
    }
}
