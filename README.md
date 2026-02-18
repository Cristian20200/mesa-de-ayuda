# Mesa de Ayuda TI (PHP + MySQL)

Sistema profesional de gestión de casos entre todas las áreas de una empresa y el área de Sistemas.

## Funcionalidades
- Registro e inicio de sesión de usuarios solicitantes.
- Usuario de Sistemas para administrar casos.
- Creación de casos con prioridad y categoría.
- Seguimiento de estado del caso y fecha planificada de resolución.
- Respuestas del equipo de Sistemas dentro de cada caso.
- Dashboard del área de Sistemas con vista tipo calendario:
  - Día de registro del caso.
  - Día planificado para atención/arreglo.
- Bandeja inteligente para Sistemas:
  - Filtros por texto, estado y prioridad.
  - Sugerencias automáticas de plan de acción según descripción del caso.
  - Plantillas rápidas de respuesta para acelerar atención.
- Interfaz visual mejorada con tarjetas KPI y diseño más moderno.

## Requisitos
- PHP 8+
- MySQL 8+
- Extensión `mysqli`

## Instalación rápida
1. Importa la base de datos:
   ```bash
   mysql -u root -p < database.sql
   ```
2. Ajusta credenciales en `includes/config.php`.
3. Ejecuta servidor local:
   ```bash
   php -S 0.0.0.0:8000
   ```
4. Abre `http://localhost:8000`.

## Credenciales demo de Sistemas
- Correo: `sistemas@empresa.com`
- Contraseña: `Sistemas123!`

## Estructura
- `index.php`: login
- `register.php`: alta de solicitantes
- `dashboard_user.php`: creación y seguimiento de casos
- `dashboard_system.php`: tablero avanzado de Sistemas + calendario y gestión
- `assets/js/calendar.js`: render del calendario dinámico
- `assets/js/system-dashboard.js`: filtros y asistente operativo de Sistemas
- `database.sql`: esquema y datos iniciales
