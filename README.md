# Catalogo de Plantas

Aplicacion PHP para gestionar un catalogo visual de plantas por usuario.

## Requisitos

- PHP 8.2+
- Extensiones PHP: `pdo_mysql`, `gd`, `curl`, `openssl`, `exif`, `mbstring`, `json`
- MySQL o MariaDB

## Configuracion

1. Copia `.env.example` a `.env`.
2. Rellena las credenciales de base de datos y `OPENAI_API_KEY` si vas a usar IA.
3. Crea las tablas:

```powershell
php .\tools\install-schema.php
```

## Desarrollo Local

Sirve la carpeta con PHP:

```powershell
php -S 127.0.0.1:8000
```

Abre:

```text
http://127.0.0.1:8000
```

Si subes imagenes desde Hostinger, la base de datos local las vera por el tunel MySQL, pero los archivos quedan en el servidor remoto. Sincroniza las imagenes remotas antes de revisar localmente:

```powershell
.\tools\sync-remote-images.ps1
```

## Estructura

- `index.php`, `login.php`, `logout.php`: paginas publicas.
- `api/`: endpoints agrupados por dominio (`plants`, `zones`, `images`, `history`, `ai`).
- `assets/`: JavaScript y CSS del frontend.
- `includes/`: configuracion compartida y acceso a datos.
- `database/`: esquema MySQL.
- `images/`: imagenes subidas y optimizadas.
- `tools/`: scripts de mantenimiento para CLI.

## Persistencia

La aplicacion usa MySQL como fuente de datos. No hay persistencia en JSON..
