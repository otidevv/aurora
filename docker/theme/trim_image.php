<?php
// Recorta el margen transparente de un PNG y lo reduce a una altura máxima.
//
// Uso: php trim_image.php <origen.png> <destino.png> [altura_max=320] [alfa_min=40] [margen=8]
//   alfa_min: 0-127 en escala GD invertida se convierte internamente; píxeles con opacidad
//   menor que alfa_min/255 se consideran fondo (ignora motas casi invisibles).
[$src, $dest] = [$argv[1] ?? null, $argv[2] ?? null];
$maxheight = (int) ($argv[3] ?? 320);
$minalpha = (int) ($argv[4] ?? 40);
$pad = (int) ($argv[5] ?? 8);
if (!$src || !$dest || !is_file($src)) {
    fwrite(STDERR, "uso: trim_image.php <origen.png> <destino.png> [altura_max] [alfa_min] [margen]\n");
    exit(1);
}

$img = imagecreatefrompng($src);
imagesavealpha($img, true);
$w = imagesx($img);
$h = imagesy($img);

// GD: alpha 0 = opaco, 127 = transparente.
$limit = 127 - (int) round($minalpha * 127 / 255);
$top = $h;
$left = $w;
$bottom = -1;
$right = -1;
for ($y = 0; $y < $h; $y++) {
    for ($x = 0; $x < $w; $x++) {
        $alpha = (imagecolorat($img, $x, $y) >> 24) & 0x7F;
        if ($alpha < $limit) {
            if ($y < $top) { $top = $y; }
            if ($y > $bottom) { $bottom = $y; }
            if ($x < $left) { $left = $x; }
            if ($x > $right) { $right = $x; }
        }
    }
}
if ($bottom < 0) {
    fwrite(STDERR, "la imagen es completamente transparente\n");
    exit(1);
}
$left = max(0, $left - $pad);
$top = max(0, $top - $pad);
$right = min($w - 1, $right + $pad);
$bottom = min($h - 1, $bottom + $pad);
$cw = $right - $left + 1;
$ch = $bottom - $top + 1;

$scale = min(1, $maxheight / $ch);
$nw = (int) round($cw * $scale);
$nh = (int) round($ch * $scale);
$out = imagecreatetruecolor($nw, $nh);
imagealphablending($out, false);
imagesavealpha($out, true);
imagefill($out, 0, 0, imagecolorallocatealpha($out, 0, 0, 0, 127));
imagecopyresampled($out, $img, 0, 0, $left, $top, $nw, $nh, $cw, $ch);
imagepng($out, $dest, 9);

printf("%s: %dx%d, recorte %dx%d en (%d,%d) -> %s %dx%d (%d KB)\n",
    basename($src), $w, $h, $cw, $ch, $left, $top, basename($dest), $nw, $nh, filesize($dest) / 1024);
