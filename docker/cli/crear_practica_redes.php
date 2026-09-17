<?php
// Crea en el Módulo 1 (Redes y Seguridad) la "Práctica 1: Redes y telecomunicaciones": cuestionario de
// opción múltiple con fecha de apertura y cierre, 2 intentos (se toma la nota más alta) y nota sobre 20.
// Las preguntas se guardan en el banco de preguntas del curso. Si el cuestionario ya existe, no hace nada.
//
// Uso:  php crear_practica_redes.php [--simular] ["AAAA-MM-DD HH:MM" apertura] ["AAAA-MM-DD HH:MM" cierre] [seccion]
//       --simular: lo crea y comprueba dentro de una transacción que se deshace al final.
//       (horas de America/Lima; por defecto 2026-09-18 19:00 -> 2026-09-20 23:59, sección 1)
define('CLI_SCRIPT', true);
require __DIR__ . '/../../config.php';
require_once($CFG->dirroot . '/course/lib.php');
require_once($CFG->dirroot . '/course/modlib.php');
require_once($CFG->dirroot . '/mod/quiz/lib.php');
require_once($CFG->dirroot . '/mod/quiz/locallib.php');
require_once($CFG->libdir . '/questionlib.php');
require_once($CFG->libdir . '/gradelib.php');

use mod_quiz\question\display_options;
use mod_quiz\grade_calculator;
use mod_quiz\quiz_settings;
use core_question\local\bank\question_bank_helper;

$args = array_slice($argv, 1);
$simular = in_array('--simular', $args, true);
$args = array_values(array_diff($args, ['--simular']));
$argv = array_merge([$argv[0]], $args);

$tz = new DateTimeZone('America/Lima');
$open = (new DateTime($argv[1] ?? '2026-09-18 19:00', $tz))->getTimestamp();
$close = (new DateTime($argv[2] ?? '2026-09-20 23:59', $tz))->getTimestamp();
$sectionnum = (int) ($argv[3] ?? 1);
if ($close <= $open) {
    fwrite(STDERR, "la fecha de cierre debe ser posterior a la de apertura\n");
    exit(1);
}

$quizname = 'Práctica 1: Redes y telecomunicaciones';
$maxgrade = 20;

// Pregunta => [respuesta correcta, [distractores], retroalimentación general].
$preguntas = [
    ['¿En qué capa del modelo OSI opera principalmente un router?',
        'Capa 3 – Red', ['Capa 2 – Enlace de datos', 'Capa 4 – Transporte', 'Capa 1 – Física'],
        'El router toma decisiones de reenvío con direcciones lógicas (IP), propias de la capa de red.'],
    ['¿Cuántas direcciones de host utilizables tiene una subred /26?',
        '62', ['64', '30', '126'],
        'Un /26 deja 6 bits de host: 2^6 = 64 direcciones, menos la de red y la de difusión = 62.'],
    ['¿Qué protocolo obtiene la dirección MAC que corresponde a una dirección IPv4 de la red local?',
        'ARP', ['DNS', 'DHCP', 'ICMP'],
        'ARP (Address Resolution Protocol) pregunta por difusión "¿quién tiene esta IP?" y guarda la MAC en su tabla.'],
    ['¿Qué puerto TCP usa por defecto HTTPS?',
        '443', ['80', '22', '8080'],
        'HTTP usa el 80, SSH el 22 y HTTPS (HTTP sobre TLS) el 443.'],
    ['¿Cuál es la diferencia principal entre TCP y UDP?',
        'TCP establece una conexión y garantiza la entrega ordenada; UDP envía datagramas sin conexión ni confirmación',
        ['UDP garantiza el orden de llegada y TCP no', 'TCP no utiliza números de puerto',
            'UDP cifra los datos y TCP los envía en claro'],
        'TCP usa el saludo de tres vías, acuses de recibo y retransmisiones; UDP es más ligero y se usa en voz y vídeo en tiempo real.'],
    ['¿Qué tipo de fibra óptica es la adecuada para enlaces de larga distancia?',
        'Fibra monomodo', ['Fibra multimodo', 'Cable UTP categoría 6', 'Cable coaxial RG-6'],
        'La fibra monomodo tiene un núcleo muy fino (≈9 µm) con un solo modo de propagación, lo que reduce la dispersión y permite decenas de kilómetros.'],
    ['La máscara 255.255.255.240 equivale al prefijo:',
        '/28', ['/27', '/29', '/26'],
        '240 = 11110000: 24 bits de los tres primeros octetos + 4 bits = /28 (14 hosts utilizables).'],
    ['¿Qué dispositivo separa dominios de colisión pero, por defecto, no separa dominios de difusión (broadcast)?',
        'Switch', ['Router', 'Hub', 'Repetidor'],
        'Cada puerto de un switch es un dominio de colisión, pero reenvía las difusiones a toda la VLAN. El router sí separa dominios de difusión.'],
    ['¿Qué técnica de multiplexación transmite varias señales por el mismo medio asignando a cada una una banda de frecuencia distinta?',
        'FDM (multiplexación por división de frecuencia)',
        ['TDM (multiplexación por división de tiempo)', 'PCM (modulación por impulsos codificados)',
            'CSMA/CD (acceso múltiple con detección de colisiones)'],
        'FDM reparte el ancho de banda en canales de frecuencia; TDM reparte el tiempo en ranuras; PCM es una técnica de digitalización.'],
    ['¿Qué protocolo asigna automáticamente direcciones IP y otros parámetros de red a los equipos?',
        'DHCP', ['ARP', 'SNMP', 'FTP'],
        'DHCP entrega IP, máscara, puerta de enlace y DNS mediante el proceso DORA (Discover, Offer, Request, Acknowledge).'],
];

\core\session\manager::set_user(get_admin());
$course = $DB->get_record('course', ['shortname' => 'DTIC-M1'], '*', MUST_EXIST);
$transaction = $simular ? $DB->start_delegated_transaction() : null;

if ($existing = $DB->get_record('quiz', ['course' => $course->id, 'name' => $quizname])) {
    $cm = get_coursemodule_from_instance('quiz', $existing->id, $course->id);
    echo "ya existe: {$CFG->wwwroot}/mod/quiz/view.php?id={$cm->id}\n";
    exit(0);
}

// --- Cuestionario ---------------------------------------------------------------------
$review = function(string $prefix, array $when) {
    $out = [];
    foreach (['during', 'immediately', 'open', 'closed'] as $w) {
        $out[$prefix . $w] = in_array($w, $when) ? 1 : 0;
    }
    return $out;
};
$moduleinfo = (object) array_merge([
    'course' => $course->id,
    'module' => $DB->get_field('modules', 'id', ['name' => 'quiz'], MUST_EXIST),
    'modulename' => 'quiz',
    'section' => $sectionnum,
    'visible' => 1,
    'visibleoncoursepage' => 1,
    'name' => $quizname,
    'intro' => '<p>Práctica de <strong>opción múltiple</strong> sobre fundamentos de redes y telecomunicaciones: '
        . 'modelo OSI, direccionamiento IPv4, protocolos, medios de transmisión y multiplexación.</p>'
        . '<ul><li>10 preguntas, una sola respuesta correcta en cada una (2 puntos por pregunta).</li>'
        . '<li>Nota mínima para aprobar: <strong>14 sobre 20</strong>.</li>'
        . '<li>Tienes <strong>2 intentos</strong>; se registra la <strong>nota más alta</strong>.</li>'
        . '<li>Cada intento dura como máximo 30 minutos y se envía solo al terminar el tiempo.</li>'
        . '<li>Al enviar verás solo tu nota; las respuestas correctas se muestran cuando cierre la práctica.</li></ul>',
    'introformat' => FORMAT_HTML,
    'showdescription' => 0,
    'timeopen' => $open,
    'timeclose' => $close,
    'timelimit' => 30 * MINSECS,
    'overduehandling' => 'autosubmit',
    'graceperiod' => 0,
    'preferredbehaviour' => 'deferredfeedback',
    'canredoquestions' => 0,
    'attempts' => 2,
    'attemptonlast' => 0,
    'grademethod' => QUIZ_GRADEHIGHEST,
    'decimalpoints' => 2,
    'questiondecimalpoints' => -1,
    'questionsperpage' => 1,
    'navmethod' => 'free',
    'shuffleanswers' => 1,
    'sumgrades' => 0,
    'grade' => $maxgrade,
    'quizpassword' => '',
    'subnet' => '',
    'browsersecurity' => '-',
    'delay1' => 0,
    'delay2' => 0,
    'showuserpicture' => 0,
    'showblocks' => 0,
    'completionattemptsexhausted' => 0,
    'completionminattempts' => 0,
    'allowofflineattempts' => 0,
    'cmidnumber' => '',
    'groupmode' => 0,
    'groupingid' => 0,
    'completion' => 0,
],
    // Mientras la práctica está abierta el estudiante solo ve su nota: no puede abrir la revisión ni saber
    // qué preguntas falló (con 4 opciones, en el segundo intento le bastaría descartar).
    // Revisión completa, respuestas correctas y explicaciones: solo cuando cierra.
    $review('attempt', ['during', 'closed']),
    $review('correctness', ['closed']),
    $review('maxmarks', ['during', 'immediately', 'open', 'closed']),
    $review('marks', ['immediately', 'open', 'closed']),
    $review('specificfeedback', ['closed']),
    $review('generalfeedback', ['closed']),
    $review('rightanswer', ['closed']),
    $review('overallfeedback', ['immediately', 'open', 'closed']),
);
$info = add_moduleinfo($moduleinfo, $course);
$quiz = $DB->get_record('quiz', ['id' => $info->instance], '*', MUST_EXIST);
$quiz->cmid = $info->coursemodule;
echo "cuestionario creado (cmid {$info->coursemodule})\n";

// --- Preguntas en el banco del curso --------------------------------------------------------
$bank = question_bank_helper::get_default_open_instance_system_type($course, true);
$bankcontext = context_module::instance($bank->id);
$parent = question_get_default_category($bankcontext->id, true);
$category = $DB->get_record('question_categories', ['parent' => $parent->id, 'name' => 'Práctica 1 - Redes y telecomunicaciones']);
if (!$category) {
    $category = (object) [
        'name' => 'Práctica 1 - Redes y telecomunicaciones',
        'contextid' => $bankcontext->id,
        'info' => 'Preguntas de opción múltiple de la Práctica 1.',
        'infoformat' => FORMAT_HTML,
        'stamp' => make_unique_id_code(),
        'parent' => $parent->id,
        'sortorder' => 999,
        'idnumber' => null,
    ];
    $category->id = $DB->insert_record('question_categories', $category);
}

$qtype = question_bank::get_qtype('multichoice');
$pointsper = $maxgrade / count($preguntas);
foreach ($preguntas as $n => [$texto, $correcta, $distractores, $retro]) {
    $answers = array_merge([$correcta], $distractores);
    $form = (object) [
        'category' => $category->id . ',' . $bankcontext->id,
        'name' => sprintf('P1-%02d %s', $n + 1, core_text::substr($texto, 0, 60)),
        'questiontext' => ['text' => '<p>' . s($texto) . '</p>', 'format' => FORMAT_HTML],
        'defaultmark' => $pointsper,
        'penalty' => 0,
        'generalfeedback' => ['text' => '<p>' . s($retro) . '</p>', 'format' => FORMAT_HTML],
        'status' => \core_question\local\bank\question_version_status::QUESTION_STATUS_READY,
        'idnumber' => '',
        'single' => 1,
        'shuffleanswers' => 1,
        'answernumbering' => 'abc',
        'showstandardinstruction' => 0,
        'shownumcorrect' => 0,
        'correctfeedback' => ['text' => '¡Correcto!', 'format' => FORMAT_HTML],
        'partiallycorrectfeedback' => ['text' => '', 'format' => FORMAT_HTML],
        'incorrectfeedback' => ['text' => 'Incorrecto.', 'format' => FORMAT_HTML],
        'answer' => [],
        'fraction' => [],
        'feedback' => [],
    ];
    foreach ($answers as $i => $text) {
        $form->answer[$i] = ['text' => s($text), 'format' => FORMAT_HTML];
        $form->fraction[$i] = $i === 0 ? '1.0' : '0.0';
        $form->feedback[$i] = ['text' => '', 'format' => FORMAT_HTML];
    }
    $question = (object) ['qtype' => 'multichoice', 'category' => $category->id];
    $saved = $qtype->save_question($question, $form);
    quiz_add_quiz_question($saved->id, $quiz, 0, $pointsper);
    echo "  pregunta " . ($n + 1) . " (id {$saved->id})\n";
}

// Una pregunta por página, en orden aleatorio para cada intento (las opciones también se mezclan).
quiz_repaginate_questions($quiz->id, 1);
$DB->set_field('quiz_sections', 'shufflequestions', 1, ['quizid' => $quiz->id]);
$calculator = grade_calculator::create(quiz_settings::create($quiz->id));
$calculator->recompute_quiz_sumgrades();
$calculator->update_quiz_maximum_grade($maxgrade);
$gradeitem = grade_item::fetch(['itemtype' => 'mod', 'itemmodule' => 'quiz', 'iteminstance' => $quiz->id,
    'courseid' => $course->id, 'itemnumber' => 0]);
$gradeitem->gradepass = 14; // Nota mínima aprobatoria (escala vigesimal).
$gradeitem->update();
rebuild_course_cache($course->id, true);

$quiz = $DB->get_record('quiz', ['id' => $quiz->id]);
echo "apertura: " . userdate($quiz->timeopen) . "\n";
echo "cierre:   " . userdate($quiz->timeclose) . "\n";
echo "intentos: {$quiz->attempts} (nota más alta), preguntas: " . $DB->count_records('quiz_slots', ['quizid' => $quiz->id])
    . ", suma: {$quiz->sumgrades}, nota máxima: {$quiz->grade}\n";
echo "ver: {$CFG->wwwroot}/mod/quiz/view.php?id={$info->coursemodule}\n";

if ($transaction) {
    try {
        $transaction->rollback(new \moodle_exception('generalexceptionmessage', 'error', '', 'simulación'));
    } catch (\moodle_exception $e) {
        rebuild_course_cache($course->id, true);
        echo "SIMULACIÓN: todo deshecho\n";
    }
}
