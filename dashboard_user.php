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
        $success = 'Caso creado correctamente.';
    }
}

$stmt = $mysqli->prepare(
    'SELECT t.id, t.title, t.priority, t.status, t.created_at, t.scheduled_date,
            (SELECT COUNT(*) FROM ticket_messages m WHERE m.ticket_id = t.id) AS chat_messages
     FROM tickets t
     WHERE t.requester_id = ?
     ORDER BY t.created_at DESC'
);
$stmt->bind_param('i', $userId);
$stmt->execute();
$tickets = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Portal del Solicitante | Mesa de Ayuda</title>
    <link rel="stylesheet" href="assets/css/styles.css">
</head>
<body>
<div class="app-shell">
    <aside class="sidebar">
        <h2>Mi Portal TI</h2>
        <nav>
            <a class="active" href="dashboard_user.php">Dashboard</a>
            <a href="#new-ticket">Crear caso</a>
            <a href="#tickets">Mis casos</a>
            <a href="logout.php">Cerrar sesión</a>
        </nav>
    </aside>

    <main class="content">
        <header class="topbar glass">
            <div>
                <h1>Bienvenido, <?php echo htmlspecialchars($_SESSION['user']['full_name']); ?></h1>
                <p class="muted">Área: <?php echo htmlspecialchars($_SESSION['user']['area'] ?? ''); ?></p>
            </div>
            <span class="pill">Canal directo con Sistemas</span>
        </header>

        <?php if ($error): ?><div class="alert error"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>
        <?php if ($success): ?><div class="alert success"><?php echo htmlspecialchars($success); ?></div><?php endif; ?>

        <section class="card" id="new-ticket">
            <h2>Crear nuevo caso</h2>
            <form method="POST" class="form-grid form-grid-2">
                <label>Título<input type="text" name="title" required></label>
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
                        <option value="red">Red/Conectividad</option>
                        <option value="solicitud">Solicitud de servicio</option>
                    </select>
                </label>
                <label class="full">Descripción<textarea name="description" rows="5" required></textarea></label>
                <button class="btn primary" type="submit">Registrar caso</button>
            </form>
        </section>

        <section class="card" id="tickets">
            <h2>Mis casos y chats</h2>
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
                        <p><strong>Mensajes del chat:</strong> <?php echo (int) $ticket['chat_messages']; ?></p>
                        <a class="btn secondary" href="ticket_chat.php?ticket_id=<?php echo (int) $ticket['id']; ?>">Abrir chat del caso</a>
                    </article>
                <?php endforeach; ?>
                <?php if (!$tickets): ?><p class="muted">Aún no has creado casos.</p><?php endif; ?>
            </div>
        </section>
    </main>
</div>
</body>
</html>
