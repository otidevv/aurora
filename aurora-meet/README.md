# Aurora · Google Meet con autograbación

Prueba de concepto para que los docentes creen clases en Google Meet que se graben solas y cuyas
grabaciones queden disponibles para los estudiantes que no pudieron asistir.

## Cómo funciona

1. Una **cuenta de servicio** de Google Workspace (`servicios-aurora@unamad-oti-auth.iam.gserviceaccount.com`)
   actúa en nombre del docente mediante *delegación a nivel de dominio*.
2. Con la **Google Meet REST API** se crea un *espacio* Meet del docente con
   `artifactConfig.recordingConfig.autoRecordingGeneration = ON`. Cada reunión en ese espacio se graba
   automáticamente al empezar.
3. Google guarda la grabación en el Drive del docente (carpeta "Meet Recordings") minutos después de
   terminar. La API devuelve el id del archivo y su enlace; el script puede compartirlo en modo lectura
   con todo el dominio `unamad.edu.pe` para que los estudiantes lo vean.

## Requisitos (administrador de Google Workspace)

- **APIs habilitadas** en el proyecto `unamad-oti-auth`: *Google Meet REST API* y *Google Drive API*.
- **Delegación de dominio**: Admin Console > Seguridad > Controles de API > Delegación de dominio >
  Añadir, con el Client ID de la cuenta de servicio y estos scopes:

  ```
  https://www.googleapis.com/auth/meetings.space.created,https://www.googleapis.com/auth/meetings.space.settings,https://www.googleapis.com/auth/meetings.space.readonly,https://www.googleapis.com/auth/drive.readonly,https://www.googleapis.com/auth/calendar.events
  ```

  Estado 2026-09-15: autorizados esos cinco scopes más `https://www.googleapis.com/auth/drive`, que
  permite compartir las grabaciones en lectura con todo el dominio (opción activada en el plugin).

  (`python prueba_meet_autograbacion.py verificar` imprime el Client ID y los scopes.)
- **Licencia con grabación** para los docentes: Google Workspace for Education Plus o el complemento
  *Teaching and Learning Upgrade*. Con Education Fundamentals la autograbación no está disponible y la
  API lo indicará.
- El archivo JSON de la cuenta de servicio es una **clave privada**: no se copia al repositorio ni al
  contenedor de Moodle. Su ruta se pasa con `--credenciales` o la variable `AURORA_GOOGLE_CREDENCIALES`.

## Uso

```sh
pip install -r requirements.txt

# 1. Credenciales y delegación
python prueba_meet_autograbacion.py verificar
python prueba_meet_autograbacion.py verificar --docente docente@unamad.edu.pe

# 2. Crear la clase (espacio Meet con autograbación)
python prueba_meet_autograbacion.py crear --docente docente@unamad.edu.pe --titulo "Cálculo I - Semana 3"

# 3. Tras la clase: listar grabaciones y compartirlas con el dominio
python prueba_meet_autograbacion.py grabaciones --docente docente@unamad.edu.pe --espacio spaces/abc-defg-hij --compartir-dominio
```

## Integración en Moodle: plugin `mod_clasemeet` (hecho el 2026-09-15)

La misma lógica está implementada en PHP como actividad de Moodle en `public/mod/clasemeet`
("Clase Meet" en el cuadro *Añadir una actividad o un recurso*):

- Al guardar la actividad, Moodle crea el espacio Meet con autograbación **a nombre del docente** que la
  crea (su correo debe ser del dominio institucional) y muestra el botón "Entrar a la clase".
- La tarea programada `mod_clasemeet	ask\sync_recordings` (cada 15 min) y el botón "Buscar grabaciones
  nuevas" consultan las grabaciones de cada espacio y las listan en la actividad. Si en Administración >
  Plugins > Clase Meet se activa "Compartir grabaciones con todo el dominio" (requiere el scope
  `https://www.googleapis.com/auth/drive` en la delegación), además las comparte en lectura con el dominio.
- **Horario de la clase** (inicio y fin obligatorios): se guarda en la actividad, crea el evento en el
  calendario del curso en Moodle y un evento en el Google Calendar del docente con el enlace del Meet
  (scope `calendar.events`). La actividad muestra el horario y el estado Próxima / En curso / Finalizada.
- Capacidades: `mod/clasemeet:addinstance` y `mod/clasemeet:manage` para profesor editor y gestor;
  `mod/clasemeet:view` para estudiantes.
- La clave de la cuenta de servicio vive en `/var/www/moodledata/clasemeet/service-account.json`
  (dentro del volumen moodledata, permisos 600). Ajustes en Administración del sitio > Plugins >
  Módulos de actividad > Clase Meet.
- Pruebas por CLI: `docker/cli/probar_clasemeet.php` crea una Clase Meet programada en un curso como un docente
  dado; `docker/cli/matricular_usuario.php` matricula usuarios (p. ej. el estudiante de prueba `estudiante`).
- Pendiente para producción: copia de seguridad/restauración (backup) y pruebas PHPUnit.

## Espacios creados

`espacios.json` guarda los espacios Meet creados (curso, docente, nombre del espacio y enlace).
Primer espacio de prueba: `spaces/zTe7820cLhAB` → https://meet.google.com/aur-vrmr-ogm, enlazado en el
curso MEET-PRUEBA de Moodle con `docker/cli/agregar_enlace_meet.php`.
