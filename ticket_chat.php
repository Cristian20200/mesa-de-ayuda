<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';

require_login();

$ticketId = (int) ($_GET['ticket_id'] ?? $_POST['ticket_id'] ?? 0);
if ($ticketId <= 0) {
    header('Location: ' . (($_SESSION['user']['role'] ?? '') === 'systems' ? 'dashboard_system.php' : 'dashboard_user.php'));
    exit;
}

$userId = (int) $_SESSION['user']['id'];
$role = $_SESSION['user']['role'] ?? '';

$stmt = $mysqli->prepare(
    'SELECT t.id, t.title, t.description, t.priority, t.status, t.scheduled_date, t.created_at,
            u.full_name AS requester_name, u.area AS requester_area, t.requester_id
     FROM tickets t
     INNER JOIN users u ON u.id = t.requester_id
     WHERE t.id = ? LIMIT 1'
);
$stmt->bind_param('i', $ticketId);
$stmt->execute();
$ticket = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$ticket) {
    header('Location: ' . ($role === 'systems' ? 'dashboard_system.php' : 'dashboard_user.php'));
    exit;
}

if ($role !== 'systems' && (int) $ticket['requester_id'] !== $userId) {
    header('Location: dashboard_user.php');
    exit;
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $message = trim($_POST['message'] ?? '');
    $hasFile = isset($_FILES['evidence']) && ($_FILES['evidence']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;

    if (!$message && !$hasFile) {
        $error = 'Debes escribir un mensaje o adjuntar evidencia.';
    } else {
        $stmt = $mysqli->prepare('INSERT INTO ticket_messages (ticket_id, sender_id, message) VALUES (?, ?, ?)');
        $stmt->bind_param('iis', $ticketId, $userId, $message);
        $stmt->execute();
        $messageId = (int) $stmt->insert_id;
        $stmt->close();

        if ($hasFile) {
            $upload = $_FILES['evidence'];
            if ($upload['error'] === UPLOAD_ERR_OK) {
                $allowedTypes = ['image/jpeg', 'image/png', 'image/webp', 'application/pdf'];
                $maxSize = 10 * 1024 * 1024;
                $fileType = mime_content_type($upload['tmp_name']) ?: 'application/octet-stream';

                if (!in_array($fileType, $allowedTypes, true)) {
                    $error = 'Archivo no permitido. Usa JPG, PNG, WEBP o PDF.';
                } elseif (($upload['size'] ?? 0) > $maxSize) {
                    $error = 'El archivo supera 10MB.';
                } else {
                    $ext = pathinfo($upload['name'], PATHINFO_EXTENSION);
                    $storedName = uniqid('evi_', true) . '.' . strtolower((string) $ext);
                    $relativePath = 'uploads/evidence/' . $storedName;
                    $absolutePath = __DIR__ . '/' . $relativePath;

                    if (move_uploaded_file($upload['tmp_name'], $absolutePath)) {
                        $origName = $upload['name'];
                        $stmt = $mysqli->prepare('INSERT INTO message_attachments (message_id, original_name, stored_name, file_path, file_type) VALUES (?, ?, ?, ?, ?)');
                        $stmt->bind_param('issss', $messageId, $origName, $storedName, $relativePath, $fileType);
                        $stmt->execute();
                        $stmt->close();
                    } else {
                        $error = 'No fue posible guardar el archivo adjunto.';
                    }
                }
            } else {
                $error = 'Ocurrió un error al subir la evidencia.';
            }
        }

        if (!$error) {
            $success = 'Mensaje enviado correctamente.';
            header('Location: ticket_chat.php?ticket_id=' . $ticketId . '&sent=1');
            exit;
        }
    }
}

if (isset($_GET['sent'])) {
    $success = 'Mensaje enviado correctamente.';
}

$stmt = $mysqli->prepare(
    'SELECT m.id, m.message, m.created_at, m.sender_id, u.full_name, u.role,
            a.id AS attachment_id, a.original_name, a.file_path, a.file_type
     FROM ticket_messages m
     INNER JOIN users u ON u.id = m.sender_id
     LEFT JOIN message_attachments a ON a.message_id = m.id
     WHERE m.ticket_id = ?
     ORDER BY m.created_at ASC, a.id ASC'
);
$stmt->bind_param('i', $ticketId);
$stmt->execute();
$rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$messages = [];
foreach ($rows as $row) {
    $id = (int) $row['id'];
    if (!isset($messages[$id])) {
        $messages[$id] = [
            'id' => $id,
            'message' => $row['message'],
            'created_at' => $row['created_at'],
            'sender_id' => (int) $row['sender_id'],
            'full_name' => $row['full_name'],
            'role' => $row['role'],
            'attachments' => [],
        ];
    }

    if (!empty($row['attachment_id'])) {
        $messages[$id]['attachments'][] = [
            'original_name' => $row['original_name'],
            'file_path' => $row['file_path'],
            'file_type' => $row['file_type'],
        ];
    }
}

$backUrl = $role === 'systems' ? 'dashboard_system.php' : 'dashboard_user.php';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chat del caso #<?php echo (int) $ticket['id']; ?> | Mesa de Ayuda</title>
    <link rel="stylesheet" href="assets/css/styles.css">
</head>
<body>
<div class="app-shell">
    <aside class="sidebar">
        <h2>Mesa de Ayuda</h2>
        <nav>
            <a href="<?php echo $backUrl; ?>">← Volver al panel</a>
            <a class="active" href="#">Chat del caso</a>
            <a href="#composer">Nuevo mensaje</a>
        </nav>
    </aside>

    <main class="content">
        <header class="topbar">
            <div>
                <h1>Caso #<?php echo (int) $ticket['id']; ?> · <?php echo htmlspecialchars($ticket['title']); ?></h1>
                <p class="muted">Solicitante: <?php echo htmlspecialchars($ticket['requester_name']); ?> (<?php echo htmlspecialchars($ticket['requester_area']); ?>)</p>
            </div>
            <div>
                <span class="badge priority-<?php echo htmlspecialchars($ticket['priority']); ?>"><?php echo htmlspecialchars(strtoupper($ticket['priority'])); ?></span>
                <span class="badge <?php echo htmlspecialchars($ticket['status']); ?>"><?php echo htmlspecialchars(str_replace('_', ' ', $ticket['status'])); ?></span>
            </div>
        </header>

        <?php if ($error): ?><div class="alert error"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>
        <?php if ($success): ?><div class="alert success"><?php echo htmlspecialchars($success); ?></div><?php endif; ?>

        <section class="card chat-thread">
            <h2>Conversación por caso</h2>
            <div class="chat-messages">
                <?php foreach ($messages as $msg): ?>
                    <?php $mine = $msg['sender_id'] === $userId; ?>
                    <article class="chat-bubble <?php echo $mine ? 'mine' : 'other'; ?>">
                        <header>
                            <strong><?php echo htmlspecialchars($msg['full_name']); ?></strong>
                            <span class="muted"> · <?php echo htmlspecialchars(date('d/m/Y H:i', strtotime($msg['created_at']))); ?></span>
                        </header>
                        <?php if ($msg['message'] !== ''): ?>
                            <p><?php echo nl2br(htmlspecialchars($msg['message'])); ?></p>
                        <?php endif; ?>

                        <?php if ($msg['attachments']): ?>
                            <div class="attachments">
                                <?php foreach ($msg['attachments'] as $attachment): ?>
                                    <?php $isImage = str_starts_with($attachment['file_type'], 'image/'); ?>
                                    <a href="<?php echo htmlspecialchars($attachment['file_path']); ?>" target="_blank" class="attachment-item">
                                        <?php if ($isImage): ?>
                                            <img src="<?php echo htmlspecialchars($attachment['file_path']); ?>" alt="Evidencia">
                                        <?php endif; ?>
                                        <span><?php echo htmlspecialchars($attachment['original_name']); ?></span>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </article>
                <?php endforeach; ?>
                <?php if (!$messages): ?><p class="muted">No hay mensajes aún. Inicia la conversación del caso.</p><?php endif; ?>
            </div>
        </section>

        <section class="card" id="composer">
            <h2>Enviar mensaje</h2>
            <form method="POST" enctype="multipart/form-data" class="form-grid">
                <input type="hidden" name="ticket_id" value="<?php echo (int) $ticket['id']; ?>">
                <label>Mensaje
                    <textarea name="message" rows="4" placeholder="Escribe detalles, avances, dudas o solución aplicada..."></textarea>
                </label>
                <label>Adjuntar evidencia (JPG, PNG, WEBP o PDF · máx 10MB)
                    <input type="file" name="evidence" accept=".jpg,.jpeg,.png,.webp,.pdf">
                </label>
                <button class="btn primary" type="submit">Enviar al chat del caso</button>
            </form>
        </section>
    </main>
</div>
</body>
</html>
