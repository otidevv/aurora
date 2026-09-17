<?php
// Genera las portadas (1200x675) de los cursos del Diplomado en TIC con GD, siguiendo la
// paleta del login Aurora (granate UNAMAD #A6192E, dorado, hexágonos). Se guardan en
// docker/theme/portadas/<shortname>.jpg y luego se aplican con set_course_image.php.
//
// Uso:  php generar_portadas.php [directorio_salida]
// No requiere Moodle; solo la extensión GD y una fuente TTF.

$out = rtrim($argv[1] ?? __DIR__ . '/../theme/portadas', '/');
@mkdir($out, 0775, true);

$fontcandidates = [
    getenv('HOME') . '/.local/share/fonts/carlito/Carlito-Bold.ttf',
    '/usr/share/fonts/truetype/crosextra/Carlito-Bold.ttf',
    '/usr/share/fonts/truetype/liberation/LiberationSans-Bold.ttf',
    '/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf',
];
$font = null;
foreach ($fontcandidates as $f) {
    if (is_readable($f)) { $font = $f; break; }
}
if (!$font) { fwrite(STDERR, "no se encontró una fuente TTF en negrita\n"); exit(1); }
$fontregular = str_replace(['-Bold', 'Bold'], ['-Regular', 'Regular'], $font);
if (!is_readable($fontregular)) { $fontregular = $font; }

// [shortname, etiqueta superior, título, color base (fondo), color de acento]
$cursos = [
    ['DTIC-TIC-GENERAL', 'Diplomado en TIC',          'Aula general del diplomado', '#A6192E', '#F2B233'],
    ['DTIC-M1',          'Diplomado en TIC · Módulo 1', 'Redes y seguridad',        '#1F4E79', '#5DADE2'],
    ['DTIC-M2',          'Diplomado en TIC · Módulo 2', 'Bases de datos',           '#0B6E4F', '#5AD8A6'],
    ['DTIC-M3',          'Diplomado en TIC · Módulo 3', 'Desarrollo de software',   '#5B2A86', '#B57BEE'],
    ['DTIC-M4',          'Diplomado en TIC · Módulo 4', 'Inteligencia Artificial',  '#B4530A', '#F5A65B'],
    ['DTIC-M5',          'Diplomado en TIC · Módulo 5', 'Ciberseguridad',           '#7A0C1F', '#F2B233'],
    ['DTIC-M6',          'Diplomado en TIC · Módulo 6', 'Internet de las Cosas',    '#0F6E7A', '#66D9E8'],
];

$W = 1200; $H = 675;

function hex2rgb(string $hex): array {
    $hex = ltrim($hex, '#');
    return [hexdec(substr($hex, 0, 2)), hexdec(substr($hex, 2, 2)), hexdec(substr($hex, 4, 2))];
}
function mix(array $a, array $b, float $t): array {
    return [(int) round($a[0] + ($b[0] - $a[0]) * $t), (int) round($a[1] + ($b[1] - $a[1]) * $t), (int) round($a[2] + ($b[2] - $a[2]) * $t)];
}
function hexagon(int $cx, int $cy, int $r): array {
    $pts = [];
    for ($i = 0; $i < 6; $i++) {
        $a = M_PI / 3 * $i + M_PI / 6;
        $pts[] = (int) round($cx + $r * cos($a));
        $pts[] = (int) round($cy + $r * sin($a));
    }
    return $pts;
}
// Ajusta el tamaño de fuente para que el texto quepa en $maxwidth (y lo parte en 2 líneas si hace falta).
function fit_text(string $font, string $text, int $maxwidth, int $size, int $minsize = 44): array {
    while ($size > $minsize) {
        $box = imagettfbbox($size, 0, $font, $text);
        if (($box[2] - $box[0]) <= $maxwidth) { return [$size, [$text]]; }
        $size -= 2;
    }
    // Dos líneas: partir por la mitad de las palabras.
    $words = explode(' ', $text);
    $half = (int) ceil(count($words) / 2);
    $lines = [implode(' ', array_slice($words, 0, $half)), implode(' ', array_slice($words, $half))];
    $size = 72;
    while ($size > 40) {
        $ok = true;
        foreach ($lines as $l) {
            $box = imagettfbbox($size, 0, $font, $l);
            if (($box[2] - $box[0]) > $maxwidth) { $ok = false; break; }
        }
        if ($ok) { break; }
        $size -= 2;
    }
    return [$size, $lines];
}

foreach ($cursos as [$short, $etiqueta, $titulo, $base, $acento]) {
    $im = imagecreatetruecolor($W, $H);
    imageantialias($im, true);
    $rgbbase = hex2rgb($base);
    $dark = mix($rgbbase, [0, 0, 0], 0.45);

    // Degradado diagonal de base -> oscuro.
    for ($y = 0; $y < $H; $y++) {
        $c = mix($rgbbase, $dark, $y / $H);
        imageline($im, 0, $y, $W, $y, imagecolorallocate($im, ...$c));
    }

    // Colmena de hexágonos translúcidos a la derecha (motivo del login Aurora).
    $r = 78; $dx = (int) round($r * sqrt(3)); $dy = (int) round($r * 1.5);
    $light = hex2rgb($acento);
    for ($row = -1; $row < 7; $row++) {
        for ($col = 0; $col < 6; $col++) {
            $cx = 700 + $col * $dx + (($row % 2) ? (int) ($dx / 2) : 0);
            $cy = 40 + $row * $dy;
            $depth = max(0, min(1, ($cx - 650) / 550));
            $alpha = (int) (127 - 8 - $depth * 52); // más opaco hacia la derecha (0 = opaco, 127 = invisible)
            if ($alpha < 20) { $alpha = 20; }
            $fill = imagecolorallocatealpha($im, $light[0], $light[1], $light[2], $alpha);
            imagefilledpolygon($im, hexagon($cx, $cy, $r - 6), $fill);
        }
    }

    // Hexágono acento sólido con la sigla del módulo.
    $acc = imagecolorallocate($im, ...hex2rgb($acento));
    imagefilledpolygon($im, hexagon(1040, 520, 96), $acc);
    $sigla = str_starts_with($short, 'DTIC-M') ? 'M' . substr($short, 6) : 'TIC';
    $box = imagettfbbox(64, 0, $font, $sigla);
    imagettftext($im, 64, 0, (int) (1040 - ($box[2] - $box[0]) / 2), 520 + 24, imagecolorallocate($im, ...$dark), $font, $sigla);

    // Barra dorada + etiqueta superior.
    $white = imagecolorallocate($im, 255, 255, 255);
    $soft = imagecolorallocatealpha($im, 255, 255, 255, 30);
    imagefilledrectangle($im, 72, 96, 132, 102, $acc);
    imagettftext($im, 22, 0, 72, 150, $soft, $fontregular, mb_strtoupper($etiqueta));

    // Título.
    [$size, $lines] = fit_text($font, $titulo, 600, 84);
    $y = 260;
    foreach ($lines as $line) {
        imagettftext($im, $size, 0, 70, $y, $white, $font, $line);
        $y += (int) ($size * 1.25);
    }

    // Pie: institución.
    imagettftext($im, 20, 0, 72, $H - 72, $soft, $fontregular, 'UNIVERSIDAD NACIONAL AMAZÓNICA DE MADRE DE DIOS');
    imagettftext($im, 20, 0, 72, $H - 44, $soft, $fontregular, 'Escuela de Posgrado · Aurora · aurora.unamad.edu.pe');

    $file = "$out/$short.jpg";
    imagejpeg($im, $file, 88);
    imagedestroy($im);
    echo "$file\n";
}
