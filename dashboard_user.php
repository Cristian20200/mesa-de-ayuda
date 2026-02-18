<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';

require_role('requester');

$success = '';
$error = '';
$userId = (int) $_SESSION['user']['id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $priority = $_POST['priority'] ?? 'media';
    $category = $_POST['category'] ?? 'incidente';
    $allowedPriorities = ['baja', 'media', 'alta', 'critica'];
    $allowedCategories = ['incidente', 'acceso', 'hardware', 'software', 'red', 'solicitud'];

    if (!$title || !$description || !in_array($priority, $allowedPriorities, true) || !in_array($category, $allowedCategories, true)) {
        $error = 'Completa correctamente la información del caso.';
    } else {
        $status = 'abierto';
        $fullDescription = '[' . strtoupper($category) . '] ' . $description;
        $stmt = $mysqli->prepare('INSERT INTO tickets (requester_id, title, description, priority, status) VALUES (?, ?, ?, ?, ?)');
        $stmt->bind_param('issss', $userId, $title, $fullDescription, $priority, $status);
        $stmt->execute();
        $stmt->close();
        $success = 'Caso creado correctamente. Sistemas ya puede gestionarlo.';
    }
}

$stmt = $mysqli->prepare(
    'SELECT t.id, t.title, t.priority, t.status, t.created_at, t.scheduled_date,
            (SELECT r.message FROM ticket_responses r WHERE r.ticket_id = t.id ORDER BY r.created_at DESC LIMIT 1) AS last_response,
            (SELECT COUNT(*) FROM ticket_responses r WHERE r.ticket_id = t.id) AS response_count
     FROM tickets t
     WHERE t.requester_id = ?
     ORDER BY FIELD(t.status, "abierto", "en_progreso", "esperando_usuario", "resuelto", "cerrado"), t.created_at DESC'
);
$stmt->bind_param('i', $userId);
$stmt->execute();
$tickets = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$summary = [
    'total' => count($tickets),
    'open' => 0,
    'progress' => 0,
    'resolved' => 0,
];

foreach ($tickets as $ticket) {
    if ($ticket['status'] === 'abierto') {
        $summary['open']++;
    }
    if ($ticket['status'] === 'en_progreso' || $ticket['status'] === 'esperando_usuario') {
        $summary['progress']++;
    }
    if ($ticket['status'] === 'resuelto' || $ticket['status'] === 'cerrado') {
        $summary['resolved']++;
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel Solicitante | Mesa de Ayuda</title>
    <link rel="stylesheet" href="assets/css/styles.css">
</head>
<body>
    <header class="topbar">
        <div>
            <h1>Mesa de Ayuda TI</h1>
            <p class="muted">Portal de casos para todas las áreas</p>
        </div>
        <div>
            <span><?php echo htmlspecialchars($_SESSION['user']['full_name']); ?> · <?php echo htmlspecialchars($_SESSION['user']['area'] ?? ''); ?></span>
            <a href="logout.php" class="btn ghost">Salir</a>
        </div>
    </header>

    <main class="layout">
        <section class="stats-grid">
            <article class="stat-card"><h3>Casos totales</h3><p><?php echo $summary['total']; ?></p></article>
            <article class="stat-card"><h3>Abiertos</h3><p><?php echo $summary['open']; ?></p></article>
            <article class="stat-card"><h3>En gestión</h3><p><?php echo $summary['progress']; ?></p></article>
            <article class="stat-card"><h3>Resueltos/Cerrados</h3><p><?php echo $summary['resolved']; ?></p></article>
        </section>

        <section class="card">
            <h2>Crear nuevo caso</h2>
            <p class="muted">Describe claramente el problema y selecciona la prioridad real para que Sistemas lo atienda más rápido.</p>

            <?php if ($error): ?><div class="alert error"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>
            <?php if ($success): ?><div class="alert success"><?php echo htmlspecialchars($success); ?></div><?php endif; ?>

            <form method="POST" class="form-grid form-grid-2">
                <label>Título
                    <input type="text" name="title" required>
                </label>
                <label>Prioridad
                    <select name="priority" required>
                        <option value="baja">Baja</option>
                        <option value="media" selected>Media</option>
                        <option value="alta">Alta</option>
                        <option value="critica">Crítica</option>
                    </select>
                </label>
                <label>Categoría
                    <select name="category" required>
                        <option value="incidente">Incidente</option>
                        <option value="acceso">Accesos</option>
                        <option value="hardware">Hardware</option>
                        <option value="software">Software</option>
                        <option value="red">Red / Conectividad</option>
                        <option value="solicitud">Solicitud de servicio</option>
                    </select>
                </label>
                <label class="full">Descripción
                    <textarea name="description" rows="5" required></textarea>
                </label>
                <button type="submit" class="btn primary">Registrar caso</button>
            </form>
        </section>

        <section class="card">
            <h2>Mis casos</h2>
            <div class="ticket-list">
                <?php foreach ($tickets as $ticket): ?>
                    <article class="ticket-item">
                        <div class="row-split">
                            <h3>#<?php echo (int) $ticket['id']; ?> · <?php echo htmlspecialchars($ticket['title']); ?></h3>
                            <span class="badge <?php echo htmlspecialchars($ticket['status']); ?>"><?php echo htmlspecialchars(str_replace('_', ' ', $ticket['status'])); ?></span>
                        </div>
                        <p><strong>Prioridad:</strong> <?php echo htmlspecialchars(ucfirst($ticket['priority'])); ?></p>
                        <p><strong>Registrado:</strong> <?php echo htmlspecialchars(date('d/m/Y H:i', strtotime($ticket['created_at']))); ?></p>
                        <p><strong>Fecha planificada:</strong> <?php echo $ticket['scheduled_date'] ? htmlspecialchars(date('d/m/Y', strtotime($ticket['scheduled_date']))) : 'Sin fecha'; ?></p>
                        <p><strong>Respuestas:</strong> <?php echo (int) $ticket['response_count']; ?></p>
                        <p><strong>Última respuesta:</strong> <?php echo htmlspecialchars($ticket['last_response'] ?: 'Sin respuestas aún'); ?></p>
                    </article>
                <?php endforeach; ?>
                <?php if (!$tickets): ?><p class="muted">Aún no has creado casos.</p><?php endif; ?>
            </div>
        </section>
    </main>
</body>
</html>
