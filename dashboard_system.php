<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';

require_role('systems');

$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'update_ticket') {
        $ticketId = (int) ($_POST['ticket_id'] ?? 0);
        $status = $_POST['status'] ?? '';
        $scheduledDate = $_POST['scheduled_date'] ?: null;
        $allowedStatuses = ['abierto', 'en_progreso', 'esperando_usuario', 'resuelto', 'cerrado'];

        if ($ticketId <= 0 || !in_array($status, $allowedStatuses, true)) {
            $error = 'No fue posible actualizar el caso.';
        } else {
            $stmt = $mysqli->prepare('UPDATE tickets SET status = ?, scheduled_date = ? WHERE id = ?');
            $stmt->bind_param('ssi', $status, $scheduledDate, $ticketId);
            $stmt->execute();
            $stmt->close();
            $success = 'Caso actualizado correctamente.';
        }
    }
}

$query = 'SELECT t.id, t.title, t.description, t.priority, t.status, t.created_at, t.scheduled_date,
                 u.full_name AS requester_name, u.area AS requester_area,
                 (SELECT COUNT(*) FROM ticket_messages m WHERE m.ticket_id = t.id) AS chat_messages
          FROM tickets t
          INNER JOIN users u ON u.id = t.requester_id
          ORDER BY FIELD(t.priority, "critica", "alta", "media", "baja"), t.created_at DESC';

$tickets = $mysqli->query($query)->fetch_all(MYSQLI_ASSOC);

$stats = [
    'total' => count($tickets),
    'abierto' => 0,
    'en_progreso' => 0,
    'resuelto' => 0,
    'critica' => 0,
];

foreach ($tickets as $ticket) {
    if (isset($stats[$ticket['status']])) {
        $stats[$ticket['status']]++;
    }
    if ($ticket['priority'] === 'critica') {
        $stats['critica']++;
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Centro de Operaciones TI</title>
    <link rel="stylesheet" href="assets/css/styles.css">
</head>
<body>
<div class="app-shell">
    <aside class="sidebar system">
        <h2>Ops TI</h2>
        <nav>
            <a class="active" href="dashboard_system.php">Dashboard</a>
            <a href="#calendar">Calendario</a>
            <a href="#queue">Bandeja inteligente</a>
            <a href="logout.php">Cerrar sesión</a>
        </nav>
    </aside>

    <main class="content glow">
        <header class="topbar hero">
            <div>
                <h1>Centro de Operaciones TI</h1>
                <p class="muted">Panel profesional, inteligente y de alto rendimiento para soporte.</p>
            </div>
            <div class="hero-badges">
                <span class="pill">SLA activo</span>
                <span class="pill">Monitoreo operativo</span>
            </div>
        </header>

        <?php if ($error): ?><div class="alert error"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>
        <?php if ($success): ?><div class="alert success"><?php echo htmlspecialchars($success); ?></div><?php endif; ?>

        <section class="stats-grid neon">
            <article class="stat-card"><h3>Casos totales</h3><p><?php echo $stats['total']; ?></p></article>
            <article class="stat-card"><h3>Abiertos</h3><p><?php echo $stats['abierto']; ?></p></article>
            <article class="stat-card"><h3>En progreso</h3><p><?php echo $stats['en_progreso']; ?></p></article>
            <article class="stat-card"><h3>Resueltos</h3><p><?php echo $stats['resuelto']; ?></p></article>
            <article class="stat-card danger"><h3>Críticos</h3><p><?php echo $stats['critica']; ?></p></article>
        </section>

        <section class="card" id="calendar">
            <div class="section-header">
                <h2>Calendario operativo</h2>
                <p>Registro y programación de intervención.</p>
            </div>
            <div id="calendarWidget" class="calendar"></div>
        </section>

        <section class="card" id="queue">
            <div class="section-header">
                <h2>Bandeja inteligente de casos</h2>
                <div class="filters">
                    <input type="text" id="filterText" placeholder="Buscar por título, área o solicitante">
                    <select id="filterStatus">
                        <option value="">Todos los estados</option>
                        <option value="abierto">Abierto</option>
                        <option value="en_progreso">En progreso</option>
                        <option value="esperando_usuario">Esperando usuario</option>
                        <option value="resuelto">Resuelto</option>
                        <option value="cerrado">Cerrado</option>
                    </select>
                    <select id="filterPriority">
                        <option value="">Todas las prioridades</option>
                        <option value="critica">Crítica</option>
                        <option value="alta">Alta</option>
                        <option value="media">Media</option>
                        <option value="baja">Baja</option>
                    </select>
                </div>
            </div>

            <div class="ticket-list" id="ticketList">
                <?php foreach ($tickets as $ticket): ?>
                    <article class="ticket-item"
                        data-title="<?php echo htmlspecialchars(strtolower($ticket['title'])); ?>"
                        data-area="<?php echo htmlspecialchars(strtolower($ticket['requester_area'])); ?>"
                        data-requester="<?php echo htmlspecialchars(strtolower($ticket['requester_name'])); ?>"
                        data-status="<?php echo htmlspecialchars($ticket['status']); ?>"
                        data-priority="<?php echo htmlspecialchars($ticket['priority']); ?>"
                        data-description="<?php echo htmlspecialchars(strtolower($ticket['description'])); ?>"
                    >
                        <div class="row-split">
                            <h3>#<?php echo (int) $ticket['id']; ?> · <?php echo htmlspecialchars($ticket['title']); ?></h3>
                            <span class="badge priority-<?php echo htmlspecialchars($ticket['priority']); ?>"><?php echo htmlspecialchars(strtoupper($ticket['priority'])); ?></span>
                        </div>
                        <p><strong>Área:</strong> <?php echo htmlspecialchars($ticket['requester_area']); ?> | <strong>Solicitante:</strong> <?php echo htmlspecialchars($ticket['requester_name']); ?></p>
                        <p><?php echo nl2br(htmlspecialchars($ticket['description'])); ?></p>
                        <p><strong>Estado:</strong> <span class="badge <?php echo htmlspecialchars($ticket['status']); ?>"><?php echo htmlspecialchars(str_replace('_', ' ', $ticket['status'])); ?></span></p>
                        <p><strong>Registro:</strong> <?php echo htmlspecialchars(date('d/m/Y H:i', strtotime($ticket['created_at']))); ?> | <strong>Planificada:</strong> <?php echo $ticket['scheduled_date'] ? htmlspecialchars(date('d/m/Y', strtotime($ticket['scheduled_date']))) : 'Sin fecha'; ?></p>
                        <p><strong>Mensajes de chat:</strong> <?php echo (int) $ticket['chat_messages']; ?></p>

                        <div class="quick-actions">
                            <button class="btn secondary suggest-btn" type="button">Sugerir plan de acción</button>
                            <a class="btn ghost" href="ticket_chat.php?ticket_id=<?php echo (int) $ticket['id']; ?>">Abrir chat del caso</a>
                        </div>

                        <form method="POST" class="form-inline">
                            <input type="hidden" name="action" value="update_ticket">
                            <input type="hidden" name="ticket_id" value="<?php echo (int) $ticket['id']; ?>">
                            <select name="status" required>
                                <?php $statuses = ['abierto' => 'Abierto', 'en_progreso' => 'En progreso', 'esperando_usuario' => 'Esperando usuario', 'resuelto' => 'Resuelto', 'cerrado' => 'Cerrado'];
                                foreach ($statuses as $key => $label): ?>
                                    <option value="<?php echo $key; ?>" <?php echo $ticket['status'] === $key ? 'selected' : ''; ?>><?php echo $label; ?></option>
                                <?php endforeach; ?>
                            </select>
                            <input type="date" name="scheduled_date" value="<?php echo htmlspecialchars((string) $ticket['scheduled_date']); ?>">
                            <button class="btn primary" type="submit">Actualizar</button>
                        </form>
                    </article>
                <?php endforeach; ?>
                <?php if (!$tickets): ?><p class="muted">No existen casos todavía.</p><?php endif; ?>
            </div>

            <aside class="card assistant-panel" id="assistantPanel">
                <h3>Asistente de resolución</h3>
                <div id="assistantContent" class="assistant-content"><p class="muted">Selecciona “Sugerir plan de acción” en un ticket.</p></div>
            </aside>
        </section>
    </main>
</div>

<script>
window.ticketEvents = <?php echo json_encode(array_map(static fn(array $ticket): array => [
    'id' => (int) $ticket['id'],
    'title' => $ticket['title'],
    'status' => $ticket['status'],
    'created_at' => $ticket['created_at'],
    'scheduled_date' => $ticket['scheduled_date'],
], $tickets), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
</script>
<script src="assets/js/calendar.js"></script>
<script src="assets/js/system-dashboard.js"></script>
</body>
</html>
