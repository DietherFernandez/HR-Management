<?php
// Generate PNG icons gamit ang GD (XAMPP PHP)
$colors = [192 => '#2563eb', 512 => '#2563eb'];
foreach ($colors as $size => $hex) {
    $img = imagecreatetruecolor($size, $size);
    // background: navy rounded square
    $bg = imagecolorallocate($img, 0x1e, 0x3a, 0x5f);
    imagefill($img, 0, 0, $bg);
    // inner blue square
    $blue = imagecolorallocate($img, 0x25, 0x63, 0xeb);
    $pad = (int)($size * 0.15);
    imagefilledrectangle($img, $pad, $pad, $size - $pad, $size - $pad, $blue);
    // "HR" text
    $white = imagecolorallocate($img, 255, 255, 255);
    imagestring($img, 5, (int)($size * 0.32), (int)($size * 0.42), 'HR', $white);
    imagepng($img, __DIR__ . "/../frontend/public/icons/icon-{$size}.png");
    imagedestroy($img);
    echo "Created icon-{$size}.png\n";
}
