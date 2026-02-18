# Mesa de Ayuda TI (PHP + MySQL)

Sistema completo para gestión de casos de soporte entre distintas áreas de una empresa y el área de Sistemas.

## Funcionalidades
- Registro e inicio de sesión de usuarios solicitantes.
- Usuario de Sistemas para administrar casos.
- Creación de casos con prioridad y descripción.
- Seguimiento de estado del caso y fecha planificada de resolución.
- Respuestas del equipo de Sistemas dentro de cada caso.
- Dashboard del área de Sistemas con vista tipo calendario:
  - Día de registro del caso.
  - Día planificado para atención/arreglo.

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
- `dashboard_system.php`: tablero de Sistemas + calendario y gestión
- `assets/js/calendar.js`: render del calendario dinámico
- `database.sql`: esquema y datos iniciales
