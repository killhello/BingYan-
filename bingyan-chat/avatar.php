<?php
$name = isset($_GET['name']) ? $_GET['name'] : '';
if (empty($name)) {
    header('Content-Type: image/png');
    readfile(__DIR__ . '/photo/default.png');
    exit();
}

$name = preg_replace('/[^a-zA-Z0-9_\x{4e00}-\x{9fa5}]/u', '', $name);
if (empty($name)) {
    header('Content-Type: image/png');
    readfile(__DIR__ . '/photo/default.png');
    exit();
}

$filePath = __DIR__ . '/photo/' . $name . '.png';
if (file_exists($filePath)) {
    header('Content-Type: image/png');
    header('Cache-Control: public, max-age=86400');
    readfile($filePath);
    exit();
}

header('Content-Type: image/png');
$firstChar = strtoupper(mb_substr($name, 0, 1));
$colors = array(
    array(102, 126, 234), array(72, 187, 120), array(237, 137, 54),
    array(239, 68, 68), array(168, 85, 247), array(236, 64, 122),
    array(34, 197, 94), array(59, 130, 246), array(234, 179, 8)
);
$colorIndex = abs(crc32($name)) % count($colors);
list($r, $g, $b) = $colors[$colorIndex];

$img = imagecreatetruecolor(200, 200);
$bg = imagecolorallocate($img, $r, $g, $b);
imagefill($img, 0, 0, $bg);

$textColor = imagecolorallocate($img, 255, 255, 255);
$fontSize = 80;
$fontFile = '';

if (function_exists('imagettfbbox')) {
    $possibleFonts = array(
        '/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf',
        '/usr/share/fonts/truetype/liberation/LiberationSans-Bold.ttf',
        '/usr/share/fonts/TTF/DejaVuSans-Bold.ttf',
        '/usr/share/fonts/truetype/noto/NotoSansCJK-Bold.ttc',
    );
    foreach ($possibleFonts as $f) {
        if (file_exists($f)) { $fontFile = $f; break; }
    }
}

if ($fontFile && function_exists('imagettftext')) {
    $bbox = imagettfbbox($fontSize, 0, $fontFile, $firstChar);
    $x = 100 - (($bbox[2] - $bbox[0]) / 2);
    $y = 100 + (($bbox[1] - $bbox[7]) / 2);
    imagettftext($img, $fontSize, 0, $x, $y, $textColor, $fontFile, $firstChar);
} else {
    $fontSize = 5;
    $fw = imagefontwidth($fontSize) * strlen($firstChar);
    $fh = imagefontheight($fontSize);
    $x = (200 - $fw) / 2;
    $y = (200 - $fh) / 2;
    imagestring($img, $fontSize, $x, $y, $firstChar, $textColor);
}

imagepng($img);
imagedestroy($img);