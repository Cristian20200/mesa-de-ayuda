# Mesa de Ayuda TI (PHP + MySQL)

Sistema profesional de gestión de casos entre todas las áreas de una empresa y el área de Sistemas.

## Funcionalidades principales
- Registro e inicio de sesión de usuarios solicitantes.
- Usuario de Sistemas para administrar casos.
- Creación de casos con prioridad y categoría.
- Seguimiento de estado del caso y fecha planificada.
- Dashboard de Sistemas con calendario operativo.
- Filtros inteligentes y sugerencias rápidas para soporte.
- **Chat por caso** entre solicitante y Sistemas.
- **Adjuntos de evidencia** por mensaje (JPG, PNG, WEBP, PDF hasta 10MB).
- Menú lateral en paneles para navegación más clara.

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
3. Verifica permisos de escritura sobre `uploads/evidence/`.
4. Ejecuta servidor local:
   ```bash
   php -S 0.0.0.0:8000
   ```
5. Abre `http://localhost:8000`.

## Credenciales demo de Sistemas
- Correo: `sistemas@empresa.com`
- Contraseña: `Sistemas123!`

## Estructura clave
- `dashboard_user.php`: panel del solicitante y acceso al chat por caso.
- `dashboard_system.php`: panel avanzado de Sistemas + calendario + filtros.
- `ticket_chat.php`: conversación por caso con adjuntos.
- `assets/js/system-dashboard.js`: filtros y asistente operativo.
- `assets/js/calendar.js`: calendario dinámico.
- `database.sql`: esquema completo (incluye mensajes y adjuntos).
