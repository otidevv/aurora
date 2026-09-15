#!/usr/bin/env python3
"""Prueba de Google Meet con autograbación para Aurora (UNAMAD).

Usa la cuenta de servicio de Google Workspace (delegación a nivel de dominio) para actuar
en nombre de un docente y:

  verificar     comprueba las credenciales y, con --docente, la delegación de dominio
  crear         crea un espacio Meet del docente con grabación automática activada
  grabaciones   lista las grabaciones de un espacio (archivos en el Drive del docente)
                y opcionalmente las comparte con todo el dominio unamad.edu.pe

Ejemplos:
  python prueba_meet_autograbacion.py verificar
  python prueba_meet_autograbacion.py verificar --docente docente@unamad.edu.pe
  python prueba_meet_autograbacion.py crear --docente docente@unamad.edu.pe --titulo "Cálculo I - Semana 3"
  python prueba_meet_autograbacion.py grabaciones --docente docente@unamad.edu.pe --espacio spaces/abc-defg-hij
  python prueba_meet_autograbacion.py grabaciones --docente docente@unamad.edu.pe --espacio spaces/abc-defg-hij --compartir-dominio

Requisitos previos (los hace el administrador de Google Workspace, ver README.md):
  * APIs habilitadas en el proyecto: Google Meet REST API y Google Drive API.
  * Delegación a nivel de dominio para la cuenta de servicio con los SCOPES de abajo.
  * El docente debe tener una licencia que permita grabar en Meet.
"""
from __future__ import annotations

import argparse
import json
import os
import sys
from pathlib import Path

from google.auth.transport.requests import Request
from google.oauth2 import service_account
from googleapiclient.discovery import build
from googleapiclient.errors import HttpError

# Consolas de Windows en cp1252: forzamos UTF-8 para que salgan bien los acentos.
for _flujo in (sys.stdout, sys.stderr):
    if hasattr(_flujo, "reconfigure"):
        _flujo.reconfigure(encoding="utf-8", errors="replace")

CREDENCIALES_POR_DEFECTO = Path(r"C:\Users\PC\Downloads\unamad-oti-auth-f7c2ff150df6.json")
DOMINIO = "unamad.edu.pe"

# Permisos autorizados por el administrador en Admin Console > Seguridad > Controles de API >
# Delegación de dominio (deben coincidir exactamente; un scope no autorizado invalida el token).
SCOPES = [
    "https://www.googleapis.com/auth/meetings.space.created",   # crear espacios Meet
    "https://www.googleapis.com/auth/meetings.space.settings",  # configurar el espacio (autograbación)
    "https://www.googleapis.com/auth/meetings.space.readonly",  # leer reuniones y grabaciones
    "https://www.googleapis.com/auth/drive.readonly",           # leer metadatos de los archivos de grabación
    "https://www.googleapis.com/auth/calendar.events",          # (opcional) eventos de calendario con Meet
]
# Para compartir grabaciones con el dominio hace falta este scope adicional (aún no autorizado).
SCOPE_DRIVE_ESCRITURA = "https://www.googleapis.com/auth/drive"


# ---------------------------------------------------------------------------
# Utilidades
# ---------------------------------------------------------------------------
def ruta_credenciales(arg: str | None) -> Path:
    ruta = Path(arg or os.environ.get("AURORA_GOOGLE_CREDENCIALES") or CREDENCIALES_POR_DEFECTO)
    if not ruta.is_file():
        sys.exit(f"No se encuentra el archivo de credenciales: {ruta}")
    return ruta


def credenciales(ruta: Path, docente: str | None, scopes: list[str] | None = None):
    """Credenciales de la cuenta de servicio, actuando como el docente si se indica."""
    creds = service_account.Credentials.from_service_account_file(str(ruta), scopes=scopes or SCOPES)
    if docente:
        if not docente.lower().endswith("@" + DOMINIO):
            sys.exit(f"El docente debe ser una cuenta @{DOMINIO}: {docente}")
        creds = creds.with_subject(docente)
    return creds


def servicio_meet(creds):
    return build("meet", "v2", credentials=creds, cache_discovery=False)


def servicio_drive(creds):
    return build("drive", "v3", credentials=creds, cache_discovery=False)


def explicar_error(err: HttpError) -> str:
    """Traduce los errores más habituales a una pista accionable."""
    try:
        detalle = json.loads(err.content.decode("utf-8"))["error"]
        mensaje = detalle.get("message", "")
        estado = detalle.get("status", "")
    except Exception:  # noqa: BLE001
        mensaje, estado = str(err), ""
    pistas = {
        "SERVICE_DISABLED": "La API no está habilitada en el proyecto unamad-oti-auth. "
                            "Habilita 'Google Meet REST API' y 'Google Drive API' en Google Cloud Console.",
        "PERMISSION_DENIED": "Falta la delegación de dominio para esta cuenta de servicio o el docente no "
                             "tiene licencia para grabar. Revisa Admin Console > Seguridad > Controles de API.",
        "unauthorized_client": "La delegación de dominio no incluye estos scopes para el client_id de la "
                               "cuenta de servicio.",
    }
    for clave, pista in pistas.items():
        if clave in estado or clave in mensaje:
            return f"{mensaje}\n  -> {pista}"
    return mensaje


# ---------------------------------------------------------------------------
# Comandos
# ---------------------------------------------------------------------------
def cmd_verificar(args) -> None:
    ruta = ruta_credenciales(args.credenciales)
    datos = json.loads(ruta.read_text(encoding="utf-8"))
    print("Archivo de credenciales :", ruta)
    print("Tipo                    :", datos.get("type"))
    print("Proyecto                :", datos.get("project_id"))
    print("Cuenta de servicio      :", datos.get("client_email"))
    print("Client ID (delegación)  :", datos.get("client_id"))
    print("Scopes a autorizar      :")
    for scope in SCOPES:
        print("   ", scope)
    print("Scopes en una línea     :", ",".join(SCOPES))

    # 1) La clave privada funciona: pedimos un token para la propia cuenta de servicio.
    creds = credenciales(ruta, None)
    try:
        creds.refresh(Request())
        print("Clave privada           : válida (token obtenido)")
    except Exception as exc:  # noqa: BLE001
        sys.exit(f"Clave privada           : ERROR al obtener token -> {exc}")

    # 2) ¿Están habilitadas las APIs en el proyecto? (llamadas de solo lectura con la propia cuenta)
    try:
        servicio_drive(creds).about().get(fields="user").execute()
        print("Google Drive API        : habilitada")
    except HttpError as err:
        print("Google Drive API        :", "NO habilitada" if "SERVICE_DISABLED" in str(err.content) else
              f"respuesta {err.resp.status} ({explicar_error(err)})")
    try:
        servicio_meet(creds).spaces().get(name="spaces/aaa-bbbb-ccc").execute()
        print("Google Meet REST API    : habilitada")
    except HttpError as err:
        if "SERVICE_DISABLED" in str(err.content):
            print("Google Meet REST API    : NO habilitada ->", explicar_error(err))
        else:
            # 403/404 sin SERVICE_DISABLED: la API responde, solo que la cuenta de servicio no es un usuario Meet.
            print(f"Google Meet REST API    : habilitada (responde {err.resp.status}, normal sin --docente)")

    # 3) Con --docente, probamos la delegación de dominio.
    if args.docente:
        creds_docente = credenciales(ruta, args.docente)
        try:
            creds_docente.refresh(Request())
            print(f"Delegación de dominio   : OK, se puede actuar como {args.docente}")
        except Exception as exc:  # noqa: BLE001
            sys.exit(
                f"Delegación de dominio   : ERROR actuando como {args.docente}\n  {exc}\n"
                "  -> El administrador debe autorizar el Client ID de arriba con esos scopes."
            )
    else:
        print("Delegación de dominio   : no probada (añade --docente correo@unamad.edu.pe)")


def cmd_crear(args) -> None:
    ruta = ruta_credenciales(args.credenciales)
    meet = servicio_meet(credenciales(ruta, args.docente))

    cuerpo = {
        "config": {
            # TRUSTED: entra directo cualquier cuenta del dominio; externos piden acceso.
            "accessType": "TRUSTED",
            "entryPointAccess": "ALL",
            "artifactConfig": {
                "recordingConfig": {"autoRecordingGeneration": "ON"},
            },
        }
    }
    if args.transcripcion:
        cuerpo["config"]["artifactConfig"]["transcriptionConfig"] = {"autoTranscriptionGeneration": "ON"}

    try:
        espacio = meet.spaces().create(body=cuerpo).execute()
    except HttpError as err:
        sys.exit(f"No se pudo crear el espacio Meet:\n  {explicar_error(err)}")

    config = espacio.get("config", {})
    grabacion = config.get("artifactConfig", {}).get("recordingConfig", {}).get("autoRecordingGeneration")
    print("Espacio Meet creado")
    print("  Docente (propietario) :", args.docente)
    print("  Título                :", args.titulo or "(sin título, se define en Moodle)")
    print("  Nombre del espacio    :", espacio.get("name"))
    print("  Código                :", espacio.get("meetingCode"))
    print("  Enlace                :", espacio.get("meetingUri"))
    print("  Acceso                :", config.get("accessType"))
    print("  Autograbación         :", grabacion or "NO CONFIRMADA (revisa licencia del docente)")
    print()
    print("Guarda el 'Nombre del espacio' para consultar luego las grabaciones con el comando 'grabaciones'.")


def cmd_grabaciones(args) -> None:
    ruta = ruta_credenciales(args.credenciales)
    creds = credenciales(ruta, args.docente)
    meet = servicio_meet(creds)
    drive = None
    if args.compartir_dominio:
        # Compartir requiere el scope de escritura en Drive; se pide con un token aparte.
        creds_drive = credenciales(ruta, args.docente, [SCOPE_DRIVE_ESCRITURA])
        try:
            creds_drive.refresh(Request())
            drive = servicio_drive(creds_drive)
        except Exception as exc:  # noqa: BLE001
            print(f"No se compartirán las grabaciones: el scope {SCOPE_DRIVE_ESCRITURA} no está "
                  f"autorizado en la delegación de dominio ({exc.__class__.__name__}).")

    try:
        registros = meet.conferenceRecords().list(filter=f'space.name = "{args.espacio}"').execute()
    except HttpError as err:
        sys.exit(f"No se pudieron listar las reuniones del espacio:\n  {explicar_error(err)}")

    conferencias = registros.get("conferenceRecords", [])
    if not conferencias:
        print("Este espacio todavía no tiene reuniones registradas.")
        return

    total = 0
    for conf in conferencias:
        print(f"Reunión {conf['name']}")
        print(f"  Inicio: {conf.get('startTime')}  Fin: {conf.get('endTime', 'en curso')}")
        try:
            grabaciones = meet.conferenceRecords().recordings().list(parent=conf["name"]).execute()
        except HttpError as err:
            print(f"  No se pudieron listar grabaciones: {explicar_error(err)}")
            continue
        for grab in grabaciones.get("recordings", []):
            total += 1
            destino = grab.get("driveDestination", {})
            print(f"  Grabación {grab['name'].rsplit('/', 1)[-1]}  estado={grab.get('state')}")
            print(f"    Drive file id : {destino.get('file')}")
            print(f"    Ver/descargar : {destino.get('exportUri')}")
            if drive and destino.get("file") and grab.get("state") == "FILE_GENERATED":
                try:
                    drive.permissions().create(
                        fileId=destino["file"],
                        body={"type": "domain", "role": "reader", "domain": DOMINIO},
                        sendNotificationEmail=False,
                    ).execute()
                    print(f"    Compartida    : lectura para todo @{DOMINIO}")
                except HttpError as err:
                    print(f"    No se pudo compartir: {explicar_error(err)}")
        if not grabaciones.get("recordings"):
            print("  Sin grabaciones (aparecen unos minutos después de terminar la reunión).")
    print(f"\nTotal de grabaciones: {total}")


# ---------------------------------------------------------------------------
def main(argv: list[str] | None = None) -> None:
    parser = argparse.ArgumentParser(description=__doc__, formatter_class=argparse.RawDescriptionHelpFormatter)
    parser.add_argument("--credenciales", help="Ruta al JSON de la cuenta de servicio "
                                                "(o variable AURORA_GOOGLE_CREDENCIALES)")
    sub = parser.add_subparsers(dest="comando", required=True)

    p = sub.add_parser("verificar", help="Valida credenciales y delegación de dominio")
    p.add_argument("--docente", help="Correo @unamad.edu.pe para probar la delegación")
    p.set_defaults(func=cmd_verificar)

    p = sub.add_parser("crear", help="Crea un espacio Meet con autograbación")
    p.add_argument("--docente", required=True, help="Correo @unamad.edu.pe del docente propietario")
    p.add_argument("--titulo", help="Título informativo de la clase")
    p.add_argument("--transcripcion", action="store_true", help="Activar también transcripción automática")
    p.set_defaults(func=cmd_crear)

    p = sub.add_parser("grabaciones", help="Lista (y comparte) las grabaciones de un espacio")
    p.add_argument("--docente", required=True, help="Correo del docente propietario del espacio")
    p.add_argument("--espacio", required=True, help="Nombre del espacio, p. ej. spaces/abc-defg-hij")
    p.add_argument("--compartir-dominio", action="store_true",
                   help=f"Dar acceso de lectura a todo @{DOMINIO} sobre los archivos de grabación")
    p.set_defaults(func=cmd_grabaciones)

    args = parser.parse_args(argv)
    args.func(args)


if __name__ == "__main__":
    main()
