# 🛠️ SETUP.md — Guía de configuración completa

Esta guía te explica paso a paso cómo poner en marcha BookingApp desde cero, cómo obtener cada API y cómo configurarlo todo correctamente.

---

## Índice

1. [Requisitos previos](#1-requisitos-previos)
2. [Preparar el servidor y la base de datos](#2-preparar-el-servidor-y-la-base-de-datos)
3. [Configurar `config.php`](#3-configurar-configphp)
4. [API de Twilio — WhatsApp](#4-api-de-twilio--whatsapp)
5. [API de Google Calendar — Meet](#5-api-de-google-calendar--meet)
6. [Webhook de Meta Lead Ads](#6-webhook-de-meta-lead-ads)
7. [Configurar el Cron Job](#7-configurar-el-cron-job)
8. [Primer acceso al panel de administración](#8-primer-acceso-al-panel-de-administración)
9. [Conectar el anuncio de Meta con la landing](#9-conectar-el-anuncio-de-meta-con-la-landing)
10. [Verificación final](#10-verificación-final)

---

## 1. Requisitos previos

Antes de empezar necesitas tener:

- Un **servidor web** con PHP 8.1+ (hosting compartido, VPS o local con XAMPP/Laragon)
- Una **base de datos MySQL 8.0+** o MariaDB 10.5+
- Acceso a **cron jobs** en tu servidor (disponible en casi todos los hostings)
- Un **dominio con HTTPS** en producción (obligatorio para los webhooks de Meta y Twilio)
- Cuentas en: **Twilio**, **Google Cloud** y **Meta for Developers** (todas gratuitas para empezar)

---

## 2. Preparar el servidor y la base de datos

### 2.1 Subir los archivos

Sube todos los archivos del proyecto a la carpeta pública de tu servidor (normalmente `public_html`, `htdocs` o `www`). Puedes ponerlo en la raíz o en una subcarpeta.

### 2.2 Crear la base de datos

Desde el panel de tu hosting (cPanel, Plesk…) o desde la terminal:

```sql
CREATE DATABASE booking_app CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'booking_user'@'localhost' IDENTIFIED BY 'tu_contraseña_segura';
GRANT ALL PRIVILEGES ON booking_app.* TO 'booking_user'@'localhost';
FLUSH PRIVILEGES;
```

### 2.3 Importar el schema

Desde phpMyAdmin: selecciona la base de datos → Importar → sube el archivo `sql/schema.sql`.

O desde terminal:
```bash
mysql -u booking_user -p booking_app < sql/schema.sql
```

Esto crea las 6 tablas y el usuario admin por defecto (`admin` / `admin123`).

---

## 3. Configurar `config.php`

Copia el archivo `config.php` que ya existe en el proyecto y rellena **todos** los valores. Este archivo está en el `.gitignore` para que nunca se suba con tus credenciales reales.

```php
// Base de datos
define('DB_HOST', 'localhost');
define('DB_NAME', 'booking_app');
define('DB_USER', 'booking_user');
define('DB_PASS', 'tu_contraseña_segura');

// URL de tu app (sin barra final)
define('APP_URL', 'https://tudominio.com');

// Nombre de tu negocio y teléfono
define('BUSINESS_NAME', 'Mi Empresa');
define('BUSINESS_PHONE', '+34600000000');

// Zona horaria (lista completa: https://www.php.net/manual/es/timezones.europe.php)
define('APP_TIMEZONE', 'Europe/Madrid');
```

---

## 4. API de Twilio — WhatsApp

Twilio es el servicio que envía los mensajes de WhatsApp. Tiene un **sandbox gratuito** para pruebas y planes de pago para producción.

### 4.1 Crear cuenta en Twilio

1. Ve a [twilio.com](https://www.twilio.com/try-twilio) y crea una cuenta gratuita
2. Verifica tu número de teléfono durante el registro

### 4.2 Obtener las credenciales

Una vez dentro de la consola de Twilio:

1. En la pantalla principal (Home) verás directamente:
   - **Account SID** → algo como `ACxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx`
   - **Auth Token** → haz clic en el ojo para verlo
2. Copia ambos valores

### 4.3 Activar el Sandbox de WhatsApp (para pruebas)

1. En el menú izquierdo ve a **Messaging → Try it out → Send a WhatsApp message**
2. Verás un número de WhatsApp de Twilio (normalmente `+1 415 523 8886`)
3. Te pedirá que envíes un mensaje de activación desde tu WhatsApp personal al número de Twilio (ej: `join sunshine-fig`)
4. Una vez activado, ese número es tu `TWILIO_WHATSAPP_FROM`

### 4.4 Para producción (número real)

Cuando quieras usar un número de WhatsApp propio (no el sandbox):
1. En Twilio ve a **Messaging → Senders → WhatsApp Senders**
2. Solicita un **WhatsApp Business Profile** — requiere tener una cuenta de WhatsApp Business verificada
3. El proceso puede tardar varios días; Twilio te guía paso a paso

### 4.5 Copiar los valores en `config.php`

```php
define('TWILIO_ACCOUNT_SID',   'ACxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx');
define('TWILIO_AUTH_TOKEN',    'tu_auth_token_aqui');
define('TWILIO_WHATSAPP_FROM', 'whatsapp:+14155238886'); // Sandbox
// En producción: 'whatsapp:+34XXXXXXXXX' con tu número verificado
```

> **Coste aproximado:** El sandbox es gratuito. En producción, Twilio cobra alrededor de 0,005 € por mensaje enviado (depende del país).

---

## 5. API de Google Calendar — Meet

Google Calendar API permite crear reuniones con sala de Meet incluida de forma automática. Es **completamente gratuita**.

### 5.1 Crear proyecto en Google Cloud

1. Ve a [console.cloud.google.com](https://console.cloud.google.com)
2. Haz clic en el selector de proyecto (arriba a la izquierda) → **Nuevo proyecto**
3. Ponle un nombre (ej: `BookingApp`) y haz clic en **Crear**

### 5.2 Activar la API de Google Calendar

1. Con el proyecto seleccionado, ve al menú izquierdo → **APIs y servicios → Biblioteca**
2. Busca `Google Calendar API`
3. Haz clic en ella y luego en **Habilitar**

### 5.3 Crear credenciales OAuth 2.0

1. Ve a **APIs y servicios → Credenciales**
2. Haz clic en **+ Crear credenciales → ID de cliente OAuth**
3. Si te pide configurar la pantalla de consentimiento:
   - Elige **Externo** y haz clic en **Crear**
   - Rellena los campos básicos (nombre de la app, email de soporte)
   - En **Ámbitos** no añadas nada extra por ahora
   - En **Usuarios de prueba** añade tu email de Gmail
   - Guarda y vuelve a **Credenciales**
4. En **Tipo de aplicación** selecciona **Aplicación web**
5. En **Orígenes de JavaScript autorizados** añade: `https://tudominio.com`
6. En **URIs de redireccionamiento autorizados** añade: `https://tudominio.com/admin/google_callback.php`
7. Haz clic en **Crear**
8. Se mostrará una ventana con tu **Client ID** y **Client Secret** → cópialos

### 5.4 Obtener los tokens de acceso (primer uso)

La app usa OAuth 2.0 con refresh token para no tener que autenticarse cada vez. Solo necesitas hacer esto **una vez**:

1. Visita esta URL en tu navegador (reemplaza `CLIENT_ID` y `REDIRECT_URI`):
```
https://accounts.google.com/o/oauth2/v2/auth?client_id=CLIENT_ID&redirect_uri=REDIRECT_URI&response_type=code&scope=https://www.googleapis.com/auth/calendar&access_type=offline&prompt=consent
```
2. Inicia sesión con la cuenta de Google cuyo calendario quieres usar
3. Acepta los permisos
4. Serás redirigido a `https://tudominio.com/admin/google_callback.php?code=XXXXXXX`
5. Necesitas intercambiar ese `code` por tokens. Haz esta petición POST (puedes usar curl o Postman):
```bash
curl -X POST https://oauth2.googleapis.com/token \
  -d "code=EL_CODE_DE_LA_URL" \
  -d "client_id=TU_CLIENT_ID" \
  -d "client_secret=TU_CLIENT_SECRET" \
  -d "redirect_uri=TU_REDIRECT_URI" \
  -d "grant_type=authorization_code"
```
6. Recibirás un JSON con `access_token`, `refresh_token` y `expires_in`
7. Crea el archivo `google_tokens.json` en la raíz del proyecto con este contenido:
```json
{
  "access_token": "ya29.XXXXXXX",
  "refresh_token": "1//XXXXXXX",
  "expires_in": 3599,
  "expires_at": 1700000000
}
```
> El campo `expires_at` es el timestamp Unix actual + `expires_in`. Puedes obtenerlo en [unixtimestamp.com](https://www.unixtimestamp.com/).

La app renovará el `access_token` automáticamente usando el `refresh_token` cuando caduque.

### 5.5 Copiar los valores en `config.php`

```php
define('GOOGLE_CLIENT_ID',     'XXXXXXXX.apps.googleusercontent.com');
define('GOOGLE_CLIENT_SECRET', 'GOCSPX-XXXXXXXXXXXXXXXX');
define('GOOGLE_REDIRECT_URI',  'https://tudominio.com/admin/google_callback.php');
define('GOOGLE_CALENDAR_ID',   'primary'); // O el ID de un calendario específico
define('GOOGLE_TOKENS_FILE',   __DIR__ . '/google_tokens.json');
```

> **Coste:** Completamente gratuito. La API de Google Calendar tiene un límite de 1.000.000 de peticiones/día, más que suficiente.

---

## 6. Webhook de Meta Lead Ads

El webhook recibe los datos de los leads en tiempo real cuando alguien rellena tu formulario de Lead Ads en Facebook o Instagram.

### 6.1 Crear una App en Meta for Developers

1. Ve a [developers.facebook.com](https://developers.facebook.com)
2. Haz clic en **Mis Apps → Crear app**
3. Selecciona **Otro** como tipo y luego **Siguiente**
4. Ponle un nombre (ej: `BookingApp Webhook`) y haz clic en **Crear app**

### 6.2 Añadir el producto Webhooks

1. Dentro de tu app, en el panel izquierdo ve a **Añadir producto**
2. Busca **Webhooks** y haz clic en **Configurar**
3. En el menú desplegable selecciona **Página** (Page)
4. Haz clic en **Suscribirse a este objeto**

### 6.3 Configurar la URL del webhook

En el formulario que aparece:

- **URL de devolución de llamada:** `https://tudominio.com/webhook.php`
- **Token de verificación:** Pon una cadena aleatoria y segura, ej: `mi_token_secreto_2025`

> ⚠️ Este token tiene que coincidir exactamente con `META_VERIFY_TOKEN` en `config.php`

Haz clic en **Verificar y guardar** — Meta hará una petición GET a tu webhook para verificarlo. Si todo está bien, se confirmará al instante.

### 6.4 Suscribirse al campo `leadgen`

Una vez verificado:

1. Busca el campo **leadgen** en la lista de campos disponibles
2. Haz clic en **Suscribirse**

### 6.5 Obtener el App Secret

1. En el menú izquierdo ve a **Configuración → Básica**
2. Verás el **ID de la app** y el **Secreto de la app** (haz clic en "Mostrar")
3. Copia el secreto

### 6.6 Conectar con tu Página de Facebook

1. Ve a **Herramientas → Explorador de la API Graph**
2. Genera un **Token de acceso de página** para la página de Facebook desde donde lanzas tus anuncios
3. Para tokens de larga duración, sigue la [documentación de Meta](https://developers.facebook.com/docs/facebook-login/guides/access-tokens/get-long-lived)

### 6.7 Copiar los valores en `config.php`

```php
define('META_VERIFY_TOKEN',      'mi_token_secreto_2025');
define('META_APP_SECRET',        'el_secreto_de_tu_app');
define('META_PAGE_ACCESS_TOKEN', 'EAAxxxxxxxxxxxxxxxx'); // Token de página
```

### 6.8 Enlazar el formulario de Lead Ads con la landing

En tu anuncio de Meta, en el formulario de Lead Ads puedes añadir una **URL de destino** (Thank You screen). Usa esta URL para llevar al usuario a tu landing con su lead_id:

```
https://tudominio.com/index.php?lead_id={{lead.id}}
```

Meta sustituirá `{{lead.id}}` por el ID real del lead automáticamente.

---

## 7. Configurar el Cron Job

El cron job ejecuta el archivo `cron/reminders.php` cada 15 minutos para revisar si hay recordatorios que enviar.

### En hosting compartido (cPanel)

1. Entra en cPanel → **Trabajos Cron** (o "Cron Jobs")
2. En "Añadir nuevo trabajo Cron" selecciona **cada 15 minutos** (o escribe manualmente)
3. En el comando:
```bash
php /home/TU_USUARIO/public_html/cron/reminders.php >> /home/TU_USUARIO/booking_cron.log 2>&1
```
> Ajusta la ruta absoluta a donde tengas el proyecto. La puedes ver en cPanel en la barra superior.

### En VPS / servidor propio

Edita el crontab con `crontab -e` y añade:
```bash
*/15 * * * * php /var/www/html/booking-app/cron/reminders.php >> /var/log/booking_cron.log 2>&1
```

### Alternativa: Cron via URL (si no tienes acceso a cron jobs)

El archivo `reminders.php` acepta una clave secreta por URL. Edita la línea en el archivo:
```php
if (isset($_GET['cron_key']) && $_GET['cron_key'] !== 'CAMBIA_ESTA_CLAVE_SECRETA') {
```
Cambia `CAMBIA_ESTA_CLAVE_SECRETA` por una clave larga aleatoria. Luego usa un servicio externo como [cron-job.org](https://cron-job.org) (gratuito) para llamar a:
```
https://tudominio.com/cron/reminders.php?cron_key=TU_CLAVE_SECRETA
```
cada 15 minutos.

---

## 8. Primer acceso al panel de administración

1. Ve a `https://tudominio.com/admin/`
2. Inicia sesión con:
   - Usuario: `admin`
   - Contraseña: `admin123`
3. **Inmediatamente** ve a **Configuración → Cambiar contraseña** y establece una contraseña segura
4. Ve a **Disponibilidad** y configura tu horario semanal con los días y horas que quieras

---

## 9. Conectar el anuncio de Meta con la landing

### Flujo completo

```
Anuncio Meta
    ↓
Usuario rellena formulario de Lead Ads en Meta
    ↓
Meta envía datos al webhook (webhook.php) en tiempo real
    ↓
Meta redirige al usuario a: https://tudominio.com/index.php?lead_id=XXXXX
    ↓
Usuario reserva su cita en la landing
    ↓
process_booking.php asocia la cita al lead por lead_id
```

### Configurar la URL de destino en Meta Ads

En el formulario de Lead Ads, en la pantalla de agradecimiento (Thank You Screen):
- **Tipo de botón:** Visitar sitio web
- **URL:** `https://tudominio.com/index.php?lead_id={{lead.id}}`

---

## 10. Verificación final

Antes de lanzar, comprueba estos puntos:

### ✅ Lista de verificación

- [ ] La base de datos se ha creado e importado correctamente
- [ ] `config.php` tiene todas las credenciales reales
- [ ] `google_tokens.json` existe y tiene los tokens válidos
- [ ] La landing `https://tudominio.com/` carga correctamente
- [ ] El calendario muestra los días disponibles
- [ ] Se puede completar una reserva de prueba
- [ ] El WhatsApp de confirmación llega correctamente
- [ ] El panel admin carga en `https://tudominio.com/admin/`
- [ ] La contraseña por defecto ha sido cambiada
- [ ] La disponibilidad horaria está configurada a tu gusto
- [ ] El webhook de Meta está verificado (icono verde en Meta Developers)
- [ ] El cron job está activo (puedes verificarlo ejecutando manualmente el script)
- [ ] El sitio tiene HTTPS activo

### 🔍 Cómo probar el flujo completo

1. Abre `https://tudominio.com/index.php`
2. Rellena el formulario con tu propio nombre y teléfono
3. Selecciona cualquier día y hora disponibles
4. Confirma la reserva
5. Comprueba que te llega el WhatsApp de confirmación
6. Entra al panel admin y verifica que la cita aparece
7. Espera a que el cron corra (o ejecútalo manualmente) y verifica el log

---

## 🆘 Problemas frecuentes

| Problema | Causa probable | Solución |
|---|---|---|
| El calendario no carga | Error de BD o permisos | Revisa los datos de `config.php` y los permisos del usuario MySQL |
| No llega el WhatsApp | Credenciales Twilio incorrectas | Verifica Account SID y Auth Token en la consola de Twilio |
| El número de sandbox no recibe | No has enviado el mensaje de activación | Envía el mensaje `join XXXX` al número de Twilio Sandbox |
| El webhook de Meta falla | URL no accesible o token incorrecto | Verifica que la URL es HTTPS y que `META_VERIFY_TOKEN` coincide |
| Google Meet da error | Tokens caducados o sin configurar | Repite el proceso de obtención de tokens del paso 5.4 |
| El cron no envía recordatorios | Ruta incorrecta o PHP no encontrado | Usa la ruta absoluta de PHP: `which php` en el servidor |
| Error 403 en el cron via URL | Clave incorrecta | Asegúrate de que la clave en la URL coincide con la del archivo |

---

## 📞 Soporte

Si tienes dudas o problemas, abre un Issue en el repositorio de GitHub con:
1. Descripción del problema
2. Mensaje de error exacto (de los logs de PHP o del cron)
3. Versión de PHP y MySQL de tu servidor
