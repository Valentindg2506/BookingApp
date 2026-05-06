# 📅 BookingApp — Sistema de Reservas con WhatsApp & Google Meet

<div align="center">

![PHP](https://img.shields.io/badge/PHP-8.1%2B-777BB4?style=for-the-badge&logo=php&logoColor=white)
![MySQL](https://img.shields.io/badge/MySQL-8.0%2B-4479A1?style=for-the-badge&logo=mysql&logoColor=white)
![License](https://img.shields.io/badge/License-MIT-00bcd4?style=for-the-badge)
![Status](https://img.shields.io/badge/Status-Production%20Ready-43a047?style=for-the-badge)

**Sistema completo de reserva de citas online, integrado con Meta Lead Ads, WhatsApp y Google Meet.**  
Pensado para negocios que captan clientes mediante publicidad en Facebook e Instagram.

</div>

---

## ✨ ¿Qué hace esta aplicación?

Cuando un usuario hace clic en tu anuncio de Meta, llega a una **landing page** donde puede:

1. **Rellenar sus datos** (nombre, email, teléfono)
2. **Elegir un día** disponible en un calendario interactivo
3. **Seleccionar la hora** de entre los slots libres
4. **Confirmar la reserva** — en ese momento ocurren automáticamente estas acciones:
   - Se crea una **sala de Google Meet** para la reunión
   - Se envía una **confirmación por WhatsApp** al cliente
   - Se registra todo en la base de datos

Además, el sistema envía recordatorios automáticos:
- **1 día antes** de la reunión → WhatsApp al cliente
- **2 horas antes** → WhatsApp con el **link directo a Google Meet**

---

## 🗂️ Módulos principales

### 🌐 Landing Pública
- Formulario multi-step con barra de progreso
- Calendario con disponibilidad en tiempo real (AJAX)
- Diseño mobile-first, sin dependencias externas
- Recibe el `lead_id` de Meta automáticamente desde la URL

### 🔔 Notificaciones WhatsApp
- Confirmación inmediata tras reservar
- Recordatorio 24 horas antes
- Recordatorio 2 horas antes con link de Google Meet
- Log completo de todos los mensajes enviados
- Powered by **Twilio WhatsApp API**

### 📹 Google Meet
- Creación automática de sala de reunión al confirmar la cita
- Link incluido en los recordatorios
- Integración via **Google Calendar API**

### 📣 Meta Lead Ads
- Webhook para recibir leads en tiempo real
- Verificación de firma HMAC-SHA256
- Los datos del lead se asocian automáticamente a la cita

### 🛠️ Panel de Administración
| Funcionalidad | Descripción |
|---|---|
| **Dashboard** | KPIs en tiempo real: citas hoy, semana, confirmadas, leads |
| **Gestión de citas** | Listado completo con filtros por estado, fecha y búsqueda |
| **Cancelar / Completar** | Acciones directas desde la tabla |
| **Disponibilidad** | Horario semanal por día con toggles y duración de slots |
| **Días bloqueados** | Bloquea festivos, vacaciones o habilita días extra |
| **Log de notificaciones** | Historial de todos los mensajes WhatsApp |
| **Seguridad** | Cambio de contraseña, información del sistema |

---

## 🏗️ Arquitectura

```
booking-app/
├── index.php               # Landing page con calendario
├── get_slots.php           # AJAX: disponibilidad
├── process_booking.php     # Procesado de reserva
├── confirm.php             # Página de confirmación
├── webhook.php             # Receptor Meta Lead Ads
├── config.php              # Configuración (NO subir con credenciales)
├── db.php                  # Conexión PDO Singleton
├── includes/
│   ├── whatsapp.php        # Twilio WhatsApp API
│   ├── google_meet.php     # Google Calendar API
│   ├── helpers.php         # Utilidades, CSRF, formateo
│   └── auth_check.php      # Middleware de autenticación
├── admin/
│   ├── index.php           # Login
│   ├── dashboard.php       # Dashboard
│   ├── appointments.php    # Gestión de citas
│   ├── availability.php    # Disponibilidad
│   ├── settings.php        # Configuración y logs
│   └── partials/           # Componentes reutilizables
├── cron/
│   └── reminders.php       # Cron job de recordatorios
└── sql/
    └── schema.sql          # Schema de base de datos
```

---

## 🗄️ Base de datos

| Tabla | Descripción |
|---|---|
| `admin_users` | Usuarios del panel de administración |
| `leads` | Leads recibidos desde Meta/Facebook Ads |
| `appointments` | Citas agendadas con todos sus datos |
| `availability_schedule` | Horario recurrente por día de la semana |
| `availability_exceptions` | Días bloqueados o habilitados puntualmente |
| `notification_logs` | Registro de todos los mensajes WhatsApp enviados |

---

## 🔒 Seguridad

- **CSRF protection** en todos los formularios con tokens de sesión
- **Prepared statements** en todas las consultas SQL (cero SQL injection)
- **Verificación HMAC-SHA256** en el webhook de Meta
- **Sesiones seguras** con `session_regenerate_id()` en el login
- **Contraseñas** almacenadas con `password_hash()` (bcrypt)
- **Sanitización** de inputs en todos los puntos de entrada
- **HTTPS obligatorio** en producción (configurar en servidor)
- `config.php` y `google_tokens.json` excluidos del repositorio vía `.gitignore`
- Anti brute-force en login (delay de 1s en intentos fallidos)
- Cron job protegido contra acceso web sin clave secreta

---

## 📋 Requisitos del sistema

- **PHP** 8.1 o superior
- **MySQL** 8.0+ / MariaDB 10.5+
- **Extensiones PHP:** `pdo_mysql`, `curl`, `json`, `openssl`
- **Servidor web:** Apache o Nginx con `.htaccess` / rewrite habilitado
- Acceso a **cron jobs** (para los recordatorios automáticos)
- Conexión a internet (para APIs de Twilio, Google y Meta)

---

## 🔌 Integraciones externas

| Servicio | Uso | Coste |
|---|---|---|
| [Twilio](https://twilio.com) | Envío de mensajes WhatsApp | Pago por uso (sandbox gratis) |
| [Google Calendar API](https://console.cloud.google.com) | Creación de salas Meet | Gratuito |
| [Meta for Developers](https://developers.facebook.com) | Recepción de leads | Gratuito |

> Para instrucciones detalladas de configuración, consulta [`SETUP.md`](SETUP.md)

---

## 📸 Vistas

### Landing pública
> Formulario de 4 pasos: datos personales → calendario → horarios → confirmación

### Panel de administración
> Dashboard con KPIs, tabla de citas con filtros, gestión de disponibilidad por día de la semana con toggles, historial completo de notificaciones WhatsApp

---

## 🤝 Contribuciones

Las contribuciones son bienvenidas. Por favor:

1. Haz un fork del repositorio
2. Crea una rama para tu feature (`git checkout -b feature/nueva-funcionalidad`)
3. Haz commit de tus cambios (`git commit -m 'Añade nueva funcionalidad'`)
4. Sube la rama (`git push origin feature/nueva-funcionalidad`)
5. Abre un Pull Request

Por favor, sigue el estilo de código existente y documenta los cambios relevantes.

---

## ⚠️ Aviso importante

Este software se proporciona tal cual, para uso en proyectos propios o comerciales bajo los términos de la licencia MIT. **No incluyas nunca credenciales reales** (`API keys`, contraseñas, tokens) en el repositorio. Usa siempre variables de entorno o el archivo `config.php` local excluido del control de versiones.

---

## 📄 Licencia

```
MIT License

Copyright (c) 2025

Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the Software is
furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all
copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
SOFTWARE.
```
