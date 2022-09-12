<?php

$dir = 'image/';

$images = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir));

foreach ($images as $img) {
    if (exif_imagetype($img)) {
        echo '<img src="' . $img . '"width="40%" alt="' . $img . '">';
    }
}
