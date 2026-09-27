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

/**
 * Cadenas en español.
 *
 * @package    local_turnitinlimit
 * @copyright  2026 Universidad Nacional Amazónica de Madre de Dios
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'Límite de envíos a Turnitin';
$string['enabled'] = 'Aplicar el límite';
$string['enabled_desc'] = 'Si se desactiva, los archivos se envían a Turnitin sin límite, como hace el plugin de Turnitin de fábrica.';
$string['maxsends'] = 'Envíos por estudiante y actividad';
$string['maxsends_desc'] = 'Cantidad máxima de archivos que un estudiante puede enviar a Turnitin en una misma tarea, foro o taller. Cada archivo subido o reemplazado cuenta como un envío. Los que superen el límite se conservan en Moodle pero no reciben informe de similitud. Un docente o administrador puede autorizar envíos adicionales con el script docker/cli/turnitin_autorizar_envios.php.';
$string['limitreached'] = 'Este archivo no se envió a Turnitin: se alcanzó el límite de {$a} envíos en esta actividad. Si necesitas otro envío, pídeselo a tu docente.';
$string['taskenforce'] = 'Aplicar el límite de envíos a Turnitin a la cola';
$string['privacy:metadata:table'] = 'Archivos que no se enviaron a Turnitin por superar el límite de envíos.';
$string['privacy:metadata:userid'] = 'Estudiante dueño del archivo.';
$string['privacy:metadata:cm'] = 'Actividad en la que se subió el archivo.';
$string['privacy:metadata:timecreated'] = 'Momento en que se bloqueó el envío.';
$string['privacy:metadata:extra'] = 'Envíos adicionales autorizados al usuario en una actividad.';
$string['menulink'] = 'Envíos a Turnitin';
$string['pagetitle'] = 'Envíos a Turnitin: {$a}';
$string['pageintro'] = 'Cada estudiante puede enviar hasta {$a} archivos a Turnitin en esta actividad. Cada archivo subido o reemplazado cuenta como un envío. Con "Autorizar 1 envío más" el estudiante gana un envío adicional y, si tenía un archivo bloqueado, se envía de inmediato.';
$string['student'] = 'Estudiante';
$string['sendsused'] = 'Envíos usados';
$string['limit'] = 'Límite';
$string['blocked'] = 'Bloqueados';
$string['latest'] = 'Último archivo';
$string['grantone'] = 'Autorizar 1 envío más';
$string['granted'] = '{$a->name}: {$a->amount} envío(s) adicional(es) autorizado(s). Archivos reenviados a Turnitin: {$a->requeued}.';
$string['similarity'] = 'similitud {$a} %';
$string['status_success'] = 'Enviado a Turnitin';
$string['status_queued'] = 'En cola';
$string['status_error'] = 'No enviado';
$string['nostudents'] = 'No hay estudiantes matriculados en esta actividad.';
$string['turnitinlimit:manage'] = 'Ver los envíos a Turnitin de los estudiantes y autorizar envíos adicionales';
$string['studentstatuslabel'] = 'Envíos a Turnitin';
$string['studentstatus'] = '{$a->used} de {$a->limit}';
$string['studentstatusleft'] = 'Te quedan {$a->left}. Cada archivo que subas o reemplaces cuenta como un envío y recibe su informe de similitud.';
$string['studentstatusnone'] = 'Ya no tienes envíos disponibles: los archivos nuevos se guardan, pero no reciben informe de similitud. Si necesitas otro, pídeselo a tu docente.';
