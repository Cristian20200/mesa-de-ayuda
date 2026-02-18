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

    if ($action === 'add_response') {
        $ticketId = (int) ($_POST['ticket_id'] ?? 0);
        $message = trim($_POST['message'] ?? '');
        $systemUserId = (int) $_SESSION['user']['id'];

        if ($ticketId <= 0 || !$message) {
            $error = 'Debes escribir un mensaje para responder.';
        } else {
            $stmt = $mysqli->prepare('INSERT INTO ticket_responses (ticket_id, responder_id, message) VALUES (?, ?, ?)');
            $stmt->bind_param('iis', $ticketId, $systemUserId, $message);
            $stmt->execute();
            $stmt->close();
            $success = 'Respuesta guardada correctamente.';
        }
    }
}

$query = 'SELECT t.id, t.title, t.description, t.priority, t.status, t.created_at, t.scheduled_date,
                 u.full_name AS requester_name, u.area AS requester_area,
                 (SELECT r.message FROM ticket_responses r WHERE r.ticket_id = t.id ORDER BY r.created_at DESC LIMIT 1) AS last_response
          FROM tickets t
          INNER JOIN users u ON u.id = t.requester_id
          ORDER BY t.created_at DESC';

$tickets = $mysqli->query($query)->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel Sistemas | Mesa de Ayuda</title>
    <link rel="stylesheet" href="assets/css/styles.css">
</head>
<body>
    <header class="topbar">
        <h1>Dashboard Sistemas</h1>
        <div>
            <span><?php echo htmlspecialchars($_SESSION['user']['full_name']); ?></span>
            <a href="logout.php" class="btn ghost">Salir</a>
        </div>
    </header>

    <main class="layout">
        <?php if ($error): ?><div class="alert error"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>
        <?php if ($success): ?><div class="alert success"><?php echo htmlspecialchars($success); ?></div><?php endif; ?>

        <section class="card">
            <div class="section-header">
                <h2>Calendario de casos</h2>
                <p>Visualiza cuándo se registró cada caso y su fecha planificada.</p>
            </div>
            <div id="calendar" class="calendar"></div>
        </section>

        <section class="card">
            <h2>Gestión de casos</h2>
            <div class="ticket-list">
                <?php foreach ($tickets as $ticket): ?>
                    <article class="ticket-item">
                        <h3>#<?php echo (int) $ticket['id']; ?> · <?php echo htmlspecialchars($ticket['title']); ?></h3>
                        <p><strong>Área:</strong> <?php echo htmlspecialchars($ticket['requester_area']); ?> | <strong>Solicitante:</strong> <?php echo htmlspecialchars($ticket['requester_name']); ?></p>
                        <p><?php echo nl2br(htmlspecialchars($ticket['description'])); ?></p>
                        <p><strong>Estado:</strong> <?php echo htmlspecialchars($ticket['status']); ?> | <strong>Prioridad:</strong> <?php echo htmlspecialchars($ticket['priority']); ?></p>
                        <p><strong>Registro:</strong> <?php echo htmlspecialchars(date('d/m/Y H:i', strtotime($ticket['created_at']))); ?> | <strong>Planificada:</strong> <?php echo $ticket['scheduled_date'] ? htmlspecialchars(date('d/m/Y', strtotime($ticket['scheduled_date']))) : 'Sin fecha'; ?></p>
                        <p><strong>Última respuesta:</strong> <?php echo htmlspecialchars($ticket['last_response'] ?: 'Sin respuestas'); ?></p>

                        <form method="POST" class="form-inline">
                            <input type="hidden" name="action" value="update_ticket">
                            <input type="hidden" name="ticket_id" value="<?php echo (int) $ticket['id']; ?>">
                            <select name="status" required>
                                <?php
                                    $statuses = ['abierto' => 'Abierto', 'en_progreso' => 'En progreso', 'esperando_usuario' => 'Esperando usuario', 'resuelto' => 'Resuelto', 'cerrado' => 'Cerrado'];
                                    foreach ($statuses as $key => $label):
                                ?>
                                    <option value="<?php echo $key; ?>" <?php echo $ticket['status'] === $key ? 'selected' : ''; ?>><?php echo $label; ?></option>
                                <?php endforeach; ?>
                            </select>
                            <input type="date" name="scheduled_date" value="<?php echo htmlspecialchars((string) $ticket['scheduled_date']); ?>">
                            <button class="btn primary" type="submit">Actualizar</button>
                        </form>

                        <form method="POST" class="form-inline">
                            <input type="hidden" name="action" value="add_response">
                            <input type="hidden" name="ticket_id" value="<?php echo (int) $ticket['id']; ?>">
                            <input type="text" name="message" placeholder="Escribe una respuesta para el usuario" required>
                            <button class="btn secondary" type="submit">Responder</button>
                        </form>
                    </article>
                <?php endforeach; ?>
                <?php if (!$tickets): ?><p class="muted">No existen casos todavía.</p><?php endif; ?>
            </div>
        </section>
    </main>

    <script>
        window.ticketEvents = <?php
            echo json_encode(array_map(static fn(array $ticket): array => [
                'id' => (int) $ticket['id'],
                'title' => $ticket['title'],
                'status' => $ticket['status'],
                'created_at' => $ticket['created_at'],
                'scheduled_date' => $ticket['scheduled_date'],
            ], $tickets), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        ?>;
    </script>
    <script src="assets/js/calendar.js"></script>
</body>
</html>
