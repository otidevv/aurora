<?php
// Carga la información institucional del Diplomado en TIC (DTIC) en el "Aula general del diplomado":
//   - resumen del curso (tarjetas del catálogo),
//   - sección 0 renombrada "Presentación" con los datos generales,
//   - página "Plan del diplomado" (fundamentación, objetivos, requisitos, perfil del egresado).
// Es idempotente: si la página ya existe, actualiza su contenido.
//
// Uso:  php cargar_info_diplomado.php [shortname]      (por defecto DTIC-TIC-GENERAL)
define('CLI_SCRIPT', true);
require __DIR__ . '/../../config.php';
require_once($CFG->dirroot . '/course/lib.php');
require_once($CFG->dirroot . '/course/modlib.php');
require_once($CFG->libdir . '/resourcelib.php');

$shortname = $argv[1] ?? 'DTIC-TIC-GENERAL';
\core\session\manager::set_user(get_admin());
$course = $DB->get_record('course', ['shortname' => $shortname], '*', MUST_EXIST);

// --- Datos del programa ------------------------------------------------------------
$datos = [
    'Título'               => 'Tecnologías de la Información y Comunicación',
    'Código'               => 'DTIC',
    'Subtítulo'            => 'Diplomado de Posgrado',
    'Facultad'             => 'Facultad de Ingeniería',
    'Modalidad'            => 'Semipresencial · Google Meet',
    'Horario'              => 'Viernes y sábado',
    'Admisión'             => 'Admisión 2026-II',
];

$resumen = 'Forma profesionales capaces de diseñar, implementar y gestionar soluciones TIC: redes, bases de datos, '
    . 'desarrollo de software, inteligencia artificial, ciberseguridad e Internet de las Cosas.';

$fundamentacion = 'La sociedad actual se encuentra inmersa en un acelerado proceso de digitalización y transformación '
    . 'tecnológica, donde las Tecnologías de la Información y Comunicación (TIC) juegan un rol fundamental en la '
    . 'educación, la industria, la gestión empresarial, el gobierno y la salud. El diplomado integra la teoría, el '
    . 'desarrollo de habilidades técnicas y su aplicación en los sectores público y privado, capacitando a los '
    . 'profesionales en el uso adecuado de estas tecnologías.';

$objetivogeneral = 'Brindar a los participantes los conocimientos y habilidades necesarios en el campo de las '
    . 'tecnologías de la información y la comunicación (TIC), permitiéndoles diseñar, implementar y gestionar '
    . 'soluciones tecnológicas innovadoras que mejoren los procesos organizacionales y optimicen el uso de los '
    . 'recursos tecnológicos.';

$objetivosespecificos = [
    'Dominar los fundamentos teóricos y prácticos de las TIC para comprender su impacto en diferentes sectores y '
        . 'aplicarlas a la resolución de problemas específicos.',
    'Desarrollar habilidades técnicas en el uso y gestión de sistemas de información, redes y bases de datos, '
        . 'asegurando su integración eficiente en la infraestructura tecnológica.',
    'Evaluar e implementar soluciones innovadoras que utilicen TIC para mejorar la productividad, la seguridad de '
        . 'la información y la competitividad en un entorno en constante evolución.',
];

$requisitos = [
    'Título profesional o grado de bachiller en carreras reconocidas por la SUNEDU (o revalidadas).',
    'Solicitud de inscripción dirigida a la Escuela de Posgrado.',
    'Fotocopia autenticada o legalizada del Título Profesional o Grado de Bachiller.',
    'Fotocopia simple del DNI.',
    'Dos fotografías recientes tamaño carné, a color y fondo blanco.',
    'Recibo de pago por los derechos del diplomado.',
];

$perfil = [
    'Conocimientos actualizados en inteligencia artificial, big data, ciberseguridad y redes.',
    'Competencia en el uso de herramientas y plataformas relevantes del campo TIC.',
    'Capacidad para implementar mejoras en sistemas y procesos tecnológicos.',
    'Pensamiento crítico y creatividad para proponer nuevas soluciones.',
    'Compromiso ético con la privacidad y la seguridad de la información.',
];

// Plana docente (numeración oficial del programa; los cursos DTIC-M4/M5 en Aurora llevan
// los nombres al revés: M4 = Inteligencia Artificial, M5 = Ciberseguridad).
$docentes = [
    ['Módulo 1', 'Redes y seguridad',        'M.Sc. Mario Jesús Ormachea Mejía',    'mormachea@unamad.edu.pe'],
    ['Módulo 2', 'Bases de datos',           'M.Sc. Frank Arpita Salcedo',          'farpita@unamad.edu.pe'],
    ['Módulo 3', 'Desarrollo de software',   'M.Sc. Jaffet Sillo Sosa',             'jsillo@unamad.edu.pe'],
    ['Módulo 4', 'Ciberseguridad',           'M.Sc. Danger David Castellón Apaza',  'dcastellon@unamad.edu.pe'],
    ['Módulo 5', 'Inteligencia Artificial',  'Dr. Aldo Alarcón Sucasaca',           'aalarcon@unamad.edu.pe'],
    ['Módulo 6', 'Internet de las Cosas',    'M.Sc. Jaime César Prieto Luna',       'jprieto@unamad.edu.pe'],
];

// --- Helpers HTML ------------------------------------------------------------------
$e = fn(string $s): string => s($s);
$lista = fn(array $items): string => '<ul>' . implode('', array_map(fn($i) => '<li>' . $e($i) . '</li>', $items)) . '</ul>';

// --- 1. Resumen del curso (se muestra en el catálogo / tarjetas) ------------------
update_course((object) [
    'id' => $course->id,
    'summary' => '<p>' . $e($resumen) . '</p>'
        . '<p>Espacio común para todos los participantes del diplomado: avisos, cronograma, normativa y material '
        . 'transversal. Cada módulo tiene su propio curso.</p>',
    'summaryformat' => FORMAT_HTML,
]);
echo "resumen del curso actualizado\n";

// --- 2. Sección 0: "Presentación" con los datos generales ------------------------
$filas = '';
foreach ($datos as $k => $v) {
    $filas .= '<tr><th scope="row" class="text-nowrap pr-3">' . $e($k) . '</th><td>' . $e($v) . '</td></tr>';
}
$seccion0 = '<p class="lead">' . $e($resumen) . '</p>'
    . '<table class="table table-sm table-borderless w-auto mb-3"><tbody>' . $filas . '</tbody></table>'
    . '<p>Consulta la página <strong>Plan del diplomado</strong> para ver la fundamentación, los objetivos, '
    . 'los requisitos del postulante y el perfil del egresado.</p>';

$section0 = $DB->get_record('course_sections', ['course' => $course->id, 'section' => 0], '*', MUST_EXIST);
course_update_section($course, $section0, [
    'name' => 'Presentación',
    'summary' => $seccion0,
    'summaryformat' => FORMAT_HTML,
]);
echo "sección 0 -> 'Presentación' con los datos generales\n";

// --- 3. Página "Plan del diplomado" -----------------------------------------------
$contenido = '<h3>Fundamentación</h3><p>' . $e($fundamentacion) . '</p>'
    . '<h3>Objetivo general</h3><p>' . $e($objetivogeneral) . '</p>'
    . '<h3>Objetivos específicos</h3>' . $lista($objetivosespecificos)
    . '<h3>Requisitos del postulante</h3>' . $lista($requisitos)
    . '<h3>Perfil del egresado</h3>' . $lista($perfil);

$filasdoc = '';
foreach ($docentes as [$modulo, $tema, $docente, $correo]) {
    $filasdoc .= '<tr><td class="text-nowrap">' . $e($modulo) . '</td><td>' . $e($tema) . '</td>'
        . '<td>' . $e($docente) . '</td>'
        . '<td><a href="mailto:' . $e($correo) . '">' . $e($correo) . '</a></td></tr>';
}
$contenido .= '<h3>Plana docente</h3>'
    . '<div class="table-responsive"><table class="table table-sm table-striped">'
    . '<thead><tr><th>Módulo</th><th>Tema</th><th>Docente</th><th>Correo</th></tr></thead>'
    . '<tbody>' . $filasdoc . '</tbody></table></div>';

$nombrepagina = 'Plan del diplomado';
$intro = '<p>Fundamentación, objetivos, requisitos de postulación y perfil del egresado del diplomado.</p>';

$existente = $DB->get_record('page', ['course' => $course->id, 'name' => $nombrepagina]);
if ($existente) {
    $existente->intro = $intro;
    $existente->introformat = FORMAT_HTML;
    $existente->content = $contenido;
    $existente->contentformat = FORMAT_HTML;
    $existente->timemodified = time();
    $DB->update_record('page', $existente);
    $cmid = get_coursemodule_from_instance('page', $existente->id, $course->id, false, MUST_EXIST)->id;
    echo "página existente actualizada (cmid {$cmid})\n";
} else {
    $module = $DB->get_record('modules', ['name' => 'page'], '*', MUST_EXIST);
    $moduleinfo = (object) [
        'course' => $course->id,
        'module' => $module->id,
        'modulename' => 'page',
        'section' => 0,
        'visible' => 1,
        'visibleoncoursepage' => 1,
        'name' => $nombrepagina,
        'intro' => $intro,
        'introformat' => FORMAT_HTML,
        'page' => ['text' => $contenido, 'format' => FORMAT_HTML, 'itemid' => 0],
        'display' => RESOURCELIB_DISPLAY_OPEN,
        'printintro' => 0,
        'printlastmodified' => 1,
        'cmidnumber' => '',
        'groupmode' => 0,
        'groupingid' => 0,
        'completion' => 0,
    ];
    $info = add_moduleinfo($moduleinfo, $course);
    $cmid = $info->coursemodule;
    echo "página creada (cmid {$cmid})\n";
}

rebuild_course_cache($course->id, true);
echo "ver: {$CFG->wwwroot}/course/view.php?id={$course->id}\n";
echo "página: {$CFG->wwwroot}/mod/page/view.php?id={$cmid}\n";
