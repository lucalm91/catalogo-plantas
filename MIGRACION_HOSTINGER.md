# Migración a Hostinger

## 1. Contraseñas locales

Edita `.env` y rellena:

```ini
DB_PASSWORD=PON_AQUI_TU_PASSWORD_MYSQL
OPENAI_API_KEY=PON_AQUI_TU_API_KEY_OPENAI
```

No pongas contraseñas en los scripts. El túnel SSH pedirá la contraseña si tu clave SSH local no autentica sola.

## 2. Abrir túnel MySQL local

Deja esta terminal abierta:

```powershell
.\tools\open-db-tunnel.ps1
```

Esto publica la base remota de Hostinger en:

```text
127.0.0.1:3307
```

## 3. Crear esquema e importar JSON

Con el túnel abierto y `.env` configurado:

```powershell
php .\tools\migrate-json-to-mysql.php --reset
```

`--reset` vacía las tablas antes de importar. Si no quieres vaciar, ejecútalo sin esa opción.

## 4. Configuración en Hostinger

Al subir el proyecto, crea un `.env` en el servidor con los valores de `.env.hostinger.example`, usando:

```ini
DB_HOST=localhost
DB_PORT=3306
DB_DATABASE=u557144898_plantas
DB_USERNAME=u557144898_user_plantas
DB_PASSWORD=tu_password_mysql
```

## 5. SSH directo

Para abrir SSH normal:

```powershell
.\tools\open-ssh.ps1
```

Equivale a:

```powershell
ssh -p 65002 u557144898@92.113.28.187
```
