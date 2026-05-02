# Hostinger

El proyecto ya usa MySQL como fuente de datos. Los antiguos JSON de plantas e historial no forman parte del runtime.

## Configuración Local

Edita `.env` y rellena:

```ini
DB_PASSWORD=PON_AQUI_TU_PASSWORD_MYSQL
OPENAI_API_KEY=PON_AQUI_TU_API_KEY_OPENAI
```

No pongas contraseñas en scripts ni comandos.

## Túnel MySQL Local

Deja esta terminal abierta:

```powershell
.\tools\open-db-tunnel.ps1
```

Esto publica la base remota de Hostinger en:

```text
127.0.0.1:3307
```

## Instalar Esquema

Solo si partes de una base vacía:

```powershell
php .\tools\install-schema.php
```

## Configuración En Hostinger

El `.env` del servidor debe usar:

```ini
APP_ENV=production
APP_STORAGE=mysql
DB_HOST=localhost
DB_PORT=3306
DB_DATABASE=u557144898_plantas
DB_USERNAME=u557144898_user_plantas
DB_PASSWORD=tu_password_mysql
```

## SSH Directo

```powershell
.\tools\open-ssh.ps1
```

Equivale a:

```powershell
ssh -i ~/.ssh/codex_hostinger_studio -p 65002 u557144898@92.113.28.187
```
