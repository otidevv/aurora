<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

namespace local_turnitinlimit;

use core\hook\output\before_footer_html_generation;

/**
 * Muestra al estudiante, en la página de la actividad, cuántos envíos a Turnitin lleva y cuántos le quedan.
 *
 * @package    local_turnitinlimit
 * @copyright  2026 Universidad Nacional Amazónica de Madre de Dios
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class hook_callbacks {
    /**
     * Inserta una fila "Envíos a Turnitin" en la tabla de estado de la entrega (o un aviso si no hay tabla).
     *
     * @param before_footer_html_generation $hook
     */
    public static function before_footer_html_generation(before_footer_html_generation $hook): void {
        global $PAGE, $USER, $DB;

        if (!get_config('local_turnitinlimit', 'enabled')) {
            return;
        }
        $cm = $PAGE->cm;
        if (!$cm || !in_array($cm->modname, ['assign', 'forum', 'workshop'], true)) {
            return;
        }
        if ($PAGE->url->compare(new \moodle_url('/mod/' . $cm->modname . '/view.php'), URL_MATCH_BASE) === false) {
            return;
        }
        $context = \context_module::instance($cm->id);
        if (!isloggedin() || isguestuser() || has_capability('local/turnitinlimit:manage', $context)) {
            return; // Solo estudiantes.
        }
        // Solo si la actividad tiene Turnitin activado.
        $usetii = $DB->get_field('plagiarism_turnitin_config', 'value', ['cm' => $cm->id, 'name' => 'use_turnitin']);
        if (!$usetii) {
            return;
        }

        $used = limiter::sends_used($cm->id, $USER->id);
        $limit = limiter::limit_for($cm->id, $USER->id);
        $left = max(0, $limit - $used);
        $a = (object) ['used' => $used, 'limit' => $limit, 'left' => $left];
        $label = get_string('studentstatuslabel', 'local_turnitinlimit');
        $text = get_string('studentstatus', 'local_turnitinlimit', $a);
        $detail = $left > 0
            ? get_string('studentstatusleft', 'local_turnitinlimit', $a)
            : get_string('studentstatusnone', 'local_turnitinlimit');
        $class = $left > 0 ? ($left === 1 ? 'text-warning' : 'text-success') : 'text-danger';

        $html = \html_writer::tag('span', s($text), ['class' => 'fw-bold ' . $class])
            . ' ' . \html_writer::tag('span', s($detail), ['class' => 'text-muted small']);
        $payload = json_encode(['label' => $label, 'html' => $html], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);

        $hook->add_html(<<<HTML
<script>
(function () {
    var data = {$payload};
    var table = document.querySelector('.submissionstatustable table.generaltable');
    if (table) {
        var body = table.tBodies[0] || table;
        var tr = document.createElement('tr');
        var th = document.createElement('th'); th.className = 'cell c0'; th.scope = 'row'; th.textContent = data.label;
        var td = document.createElement('td'); td.className = 'cell c1 lastcol'; td.innerHTML = data.html;
        tr.appendChild(th); tr.appendChild(td);
        body.insertBefore(tr, body.firstChild);
        return;
    }
    var main = document.querySelector('#region-main [role="main"], #region-main');
    if (main) {
        var box = document.createElement('div');
        box.className = 'alert alert-info';
        box.innerHTML = '<strong>' + data.label + ':</strong> ' + data.html;
        main.insertBefore(box, main.firstChild);
    }
})();
</script>
HTML);
    }
}
