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
 * Cadenas en español para mod_clasemeet.
 *
 * @package    mod_clasemeet
 * @copyright  2026 Universidad Nacional Amazónica de Madre de Dios
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['accesstype'] = 'Quién entra directamente';
$string['accesstype_desc'] = 'TRUSTED: las cuentas del dominio institucional entran directo, el resto pide acceso. OPEN: cualquiera con el enlace. RESTRICTED: solo personas invitadas.';
$string['autorecording'] = 'Grabar automáticamente';
$string['autorecording_help'] = 'Cada reunión que se realice en este espacio Meet empieza a grabarse sola. La grabación se guarda en el Google Drive del docente y aparece aquí cuando Google termina de procesarla.';
$string['autotranscription'] = 'Transcribir automáticamente';
$string['clasemeet:addinstance'] = 'Añadir una nueva clase Meet';
$string['clasemeet:manage'] = 'Gestionar una clase Meet y sincronizar sus grabaciones';
$string['clasemeet:view'] = 'Ver una clase Meet';
$string['clasemeetname'] = 'Nombre de la clase';
$string['createdfor'] = 'El Meet se creará a nombre de <strong>{$a}</strong>, que será quien conserve las grabaciones.';
$string['credentialsfile'] = 'Archivo de clave de la cuenta de servicio';
$string['credentialsfile_desc'] = 'Ruta absoluta, dentro de moodledata, del JSON de la cuenta de servicio de Google con delegación de dominio.';
$string['date'] = 'Fecha';
$string['domain'] = 'Dominio institucional';
$string['domain_desc'] = 'Solo los usuarios cuyo correo pertenece a este dominio de Google Workspace pueden crear clases Meet.';
$string['duration'] = 'Duración';
$string['errorapi'] = 'Google Meet no aceptó la solicitud: {$a}';
$string['errorcredentials'] = 'Falta o no es válido el archivo de clave de la cuenta de servicio: {$a}';
$string['errornodomainuser'] = 'El correo de tu cuenta en Moodle ({$a->email}) no pertenece al dominio {$a->domain}, por lo que no se puede crear un Meet a tu nombre.';
$string['errornospace'] = 'Esta clase todavía no tiene espacio Meet. Edita la actividad y guárdala de nuevo para crearlo.';
$string['errortoken'] = 'Google no emitió un token de acceso para {$a->email}: {$a->error}';
$string['inprogress'] = 'Grabando ahora';
$string['join'] = 'Entrar a la clase';
$string['processing'] = 'Procesando; disponible en unos minutos';
$string['lastsync'] = 'Última comprobación de grabaciones: {$a}';
$string['meetingcode'] = 'Código de la reunión';
$string['modulename'] = 'Clase Meet';
$string['modulename_help'] = 'Crea un espacio de Google Meet para la clase a nombre del docente. Si la grabación automática está activa, cada sesión se graba y las grabaciones se listan en la actividad para que los estudiantes que no pudieron asistir las vean.';
$string['modulenameplural'] = 'Clases Meet';
$string['never'] = 'nunca';
$string['norecordings'] = 'Todavía no hay grabaciones. Aparecen unos minutos después de terminar una sesión grabada.';
$string['notshared'] = 'Solo puede abrirla el docente';
$string['notshared_help'] = 'El archivo está en el Drive del docente y aún no se ha compartido con el dominio.';
$string['sharingdisabled'] = 'Las grabaciones no se comparten con los estudiantes porque la opción "Compartir grabaciones con todo el dominio" está desactivada en los ajustes del plugin.';
$string['sharingscopemissing'] = 'Las grabaciones no se pueden compartir automáticamente con los estudiantes: la delegación de dominio de la cuenta de servicio no incluye el permiso <code>{$a}</code>. Pide al administrador de Google Workspace que lo añada; mientras tanto, abre la grabación con "Ver" y compártela desde Drive con el dominio.';
$string['opennewtab'] = 'Se abre en una pestaña nueva';
$string['owner'] = 'Docente';
$string['pluginadministration'] = 'Administración de Clase Meet';
$string['pluginname'] = 'Clase Meet';
$string['privacy:metadata:clasemeet'] = 'Clases Meet creadas por docentes.';
$string['privacy:metadata:clasemeet:owneremail'] = 'Correo de Google Workspace al que pertenece el espacio Meet.';
$string['privacy:metadata:clasemeet:ownerid'] = 'El docente que creó la clase y es propietario del espacio Meet.';
$string['privacy:metadata:google'] = 'Los espacios Meet se crean en los servidores de Google a nombre del docente; las grabaciones se guardan en su Google Drive.';
$string['recording'] = 'Grabación';
$string['recordingautomatic'] = 'Esta clase se graba automáticamente';
$string['recordings'] = 'Grabaciones de la clase';
$string['recordingsfound'] = 'Se encontraron {$a} grabación(es) nueva(s).';
$string['schedule'] = 'Horario de la clase';
$string['schedule_help'] = 'Fecha y hora de inicio y fin de la clase. Se añade al calendario del curso en Moodle y al Google Calendar del docente con el enlace del Meet. El enlace funciona en cualquier momento; el horario indica a los estudiantes cuándo conectarse.';
$string['errortimeend'] = 'La hora de fin debe ser posterior a la de inicio.';
$string['status_ended'] = 'Finalizada';
$string['status_live'] = 'En curso';
$string['status_upcoming'] = 'Próxima';
$string['timeend'] = 'Fin';
$string['timestart'] = 'Inicio';
$string['scopes'] = 'Permisos delegados';
$string['scopes_desc'] = 'Scopes OAuth separados por comas autorizados para la cuenta de servicio en la consola de administración de Workspace. Deben coincidir exactamente.';
$string['sharewithdomain'] = 'Compartir grabaciones con todo el dominio';
$string['sharewithdomain_desc'] = 'Da acceso de lectura a todas las cuentas del dominio institucional sobre los archivos de grabación (requiere el scope https://www.googleapis.com/auth/drive en la delegación).';
$string['shared'] = 'Compartida con el dominio';
$string['state_ENDED'] = 'Finalizada, procesando';
$string['state_FILE_GENERATED'] = 'Lista';
$string['state_STARTED'] = 'Grabando';
$string['sync'] = 'Buscar grabaciones nuevas';
$string['tasksyncrecordings'] = 'Sincronizar grabaciones de Clases Meet';
$string['watch'] = 'Ver';
