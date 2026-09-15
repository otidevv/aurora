<?php
// Usage: php resize_image.php <source> <dest.jpg> [maxwidth=2200] [quality=82]
// Downscales a JPEG/PNG with GD so it is light enough to serve as a page background.
[$src, $dest] = [$argv[1] ?? null, $argv[2] ?? null];
$maxwidth = (int) ($argv[3] ?? 2200);
$quality = (int) ($argv[4] ?? 82);
if (!$src || !$dest || !is_file($src)) {
    fwrite(STDERR, "usage: resize_image.php <source> <dest.jpg> [maxwidth] [quality]\n");
    exit(1);
}
[$w, $h, $type] = getimagesize($src);
$img = match ($type) {
    IMAGETYPE_JPEG => imagecreatefromjpeg($src),
    IMAGETYPE_PNG => imagecreatefrompng($src),
    IMAGETYPE_WEBP => imagecreatefromwebp($src),
    default => null,
};
if (!$img) {
    fwrite(STDERR, "unsupported image type\n");
    exit(1);
}
if ($w > $maxwidth) {
    $nw = $maxwidth;
    $nh = (int) round($h * $maxwidth / $w);
    $out = imagecreatetruecolor($nw, $nh);
    imagecopyresampled($out, $img, 0, 0, 0, 0, $nw, $nh, $w, $h);
    imagedestroy($img);
    $img = $out;
}
imageinterlace($img, true);
imagejpeg($img, $dest, $quality);
imagedestroy($img);
printf("%s: %dx%d -> %s (%d KB)\n", basename($src), $w, $h, basename($dest), filesize($dest) / 1024);
