# Catálogo de Plantas

Aplicación PHP para gestionar un catálogo visual de plantas por usuario.

## Requisitos

- PHP 8.2+
- Extensiones PHP: `pdo_mysql`, `gd`, `curl`, `openssl`, `exif`, `mbstring`, `json`
- MySQL o MariaDB

## Configuración

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

## Persistencia

La aplicación usa MySQL como fuente de datos. No hay persistencia en JSON..
