<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';

require_login();

$userId = (int) ($_SESSION['user']['id'] ?? 0);
$role = $_SESSION['user']['role'] ?? '';

function can_access_ticket(mysqli $mysqli, int $ticketId, int $userId, string $role): ?array
{
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
        return null;
    }

    if ($role !== 'systems' && (int) $ticket['requester_id'] !== $userId) {
        return null;
    }

    return $ticket;
}

function save_attachment(mysqli $mysqli, array $upload, int $messageId, ?string &$error): void
{
    $allowedTypes = ['image/jpeg', 'image/png', 'image/webp', 'application/pdf'];
    $maxSize = 10 * 1024 * 1024;
    $fileType = mime_content_type($upload['tmp_name']) ?: 'application/octet-stream';

    if (!in_array($fileType, $allowedTypes, true)) {
        $error = 'Archivo no permitido. Usa JPG, PNG, WEBP o PDF.';
        return;
    }
    if (($upload['size'] ?? 0) > $maxSize) {
        $error = 'El archivo supera 10MB.';
        return;
    }

    $ext = strtolower((string) pathinfo($upload['name'], PATHINFO_EXTENSION));
    $ext = $ext !== '' ? $ext : 'bin';
    $storedName = uniqid('evi_', true) . '.' . $ext;
    $relativePath = 'uploads/evidence/' . $storedName;
    $absoluteDir = __DIR__ . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'evidence';
    $absolutePath = $absoluteDir . DIRECTORY_SEPARATOR . $storedName;

    if (!is_dir($absoluteDir) && !mkdir($absoluteDir, 0775, true) && !is_dir($absoluteDir)) {
        $error = 'No existe la carpeta de evidencias y no pudo crearse automáticamente.';
        return;
    }
    if (!is_writable($absoluteDir)) {
        $error = 'La carpeta de evidencias no tiene permisos de escritura.';
        return;
    }

    if (!move_uploaded_file($upload['tmp_name'], $absolutePath)) {
        $error = 'No fue posible guardar el archivo adjunto. Verifica permisos de la carpeta uploads/evidence.';
        return;
    }

    $origName = $upload['name'];
    $stmt = $mysqli->prepare('INSERT INTO message_attachments (message_id, original_name, stored_name, file_path, file_type) VALUES (?, ?, ?, ?, ?)');
    $stmt->bind_param('issss', $messageId, $origName, $storedName, $relativePath, $fileType);
    $stmt->execute();
    $stmt->close();
}

function load_messages(mysqli $mysqli, int $ticketId, int $sinceId = 0): array
{
    $sql = 'SELECT m.id, m.message, m.created_at, m.sender_id, u.full_name, u.role,
                   a.id AS attachment_id, a.original_name, a.file_path, a.file_type
            FROM ticket_messages m
            INNER JOIN users u ON u.id = m.sender_id
            LEFT JOIN message_attachments a ON a.message_id = m.id
            WHERE m.ticket_id = ?';

    if ($sinceId > 0) {
        $sql .= ' AND m.id > ?';
    }

    $sql .= ' ORDER BY m.id ASC, a.id ASC';

    if ($sinceId > 0) {
        $stmt = $mysqli->prepare($sql);
        $stmt->bind_param('ii', $ticketId, $sinceId);
    } else {
        $stmt = $mysqli->prepare($sql);
        $stmt->bind_param('i', $ticketId);
    }

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

    return array_values($messages);
}

function typing_users(mysqli $mysqli, int $ticketId, int $currentUserId): array
{
    $stmt = $mysqli->prepare(
        'SELECT u.full_name
         FROM ticket_typing_status ts
         INNER JOIN users u ON u.id = ts.user_id
         WHERE ts.ticket_id = ?
           AND ts.user_id <> ?
           AND ts.is_typing = 1
           AND ts.updated_at >= (NOW() - INTERVAL 8 SECOND)
         ORDER BY ts.updated_at DESC'
    );
    $stmt->bind_param('ii', $ticketId, $currentUserId);
    $stmt->execute();
    $names = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    return array_map(static fn(array $row): string => $row['full_name'], $names);
}

if (isset($_GET['api']) && $_GET['api'] === '1') {
    header('Content-Type: application/json; charset=utf-8');

    $ticketId = (int) ($_GET['ticket_id'] ?? $_POST['ticket_id'] ?? 0);
    $ticket = can_access_ticket($mysqli, $ticketId, $userId, $role);

    if ($ticketId <= 0 || !$ticket) {
        http_response_code(403);
        echo json_encode(['ok' => false, 'error' => 'Acceso no permitido']);
        exit;
    }

    $action = $_POST['action'] ?? ($_GET['action'] ?? 'fetch');

    if ($action === 'typing') {
        $isTyping = (int) ($_POST['is_typing'] ?? 0) === 1 ? 1 : 0;
        $stmt = $mysqli->prepare(
            'INSERT INTO ticket_typing_status (ticket_id, user_id, is_typing, updated_at)
             VALUES (?, ?, ?, NOW())
             ON DUPLICATE KEY UPDATE is_typing = VALUES(is_typing), updated_at = NOW()'
        );
        $stmt->bind_param('iii', $ticketId, $userId, $isTyping);
        $stmt->execute();
        $stmt->close();

        echo json_encode(['ok' => true]);
        exit;
    }

    if ($action === 'send') {
        $message = trim($_POST['message'] ?? '');
        $hasFile = isset($_FILES['evidence']) && ($_FILES['evidence']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;
        if (!$message && !$hasFile) {
            echo json_encode(['ok' => false, 'error' => 'Debes escribir un mensaje o adjuntar evidencia.']);
            exit;
        }

        $stmt = $mysqli->prepare('INSERT INTO ticket_messages (ticket_id, sender_id, message) VALUES (?, ?, ?)');
        $stmt->bind_param('iis', $ticketId, $userId, $message);
        $stmt->execute();
        $messageId = (int) $stmt->insert_id;
        $stmt->close();

        $error = null;
        if ($hasFile) {
            $upload = $_FILES['evidence'];
            if (($upload['error'] ?? UPLOAD_ERR_OK) === UPLOAD_ERR_OK) {
                save_attachment($mysqli, $upload, $messageId, $error);
            } else {
                $error = 'Ocurrió un error al subir la evidencia.';
            }
        }

        if ($error) {
            echo json_encode(['ok' => false, 'error' => $error]);
            exit;
        }

        $messages = load_messages($mysqli, $ticketId, $messageId - 1);
        echo json_encode([
            'ok' => true,
            'messages' => $messages,
            'typing' => typing_users($mysqli, $ticketId, $userId),
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    $sinceId = (int) ($_GET['since_id'] ?? 0);
    echo json_encode([
        'ok' => true,
        'messages' => load_messages($mysqli, $ticketId, $sinceId),
        'typing' => typing_users($mysqli, $ticketId, $userId),
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

$ticketId = (int) ($_GET['ticket_id'] ?? 0);
$ticket = can_access_ticket($mysqli, $ticketId, $userId, $role);
if ($ticketId <= 0 || !$ticket) {
    header('Location: ' . ($role === 'systems' ? 'dashboard_system.php' : 'dashboard_user.php'));
    exit;
}

$messages = load_messages($mysqli, $ticketId);
$lastMessageId = $messages ? (int) end($messages)['id'] : 0;
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

        <section class="card chat-thread">
            <h2>Conversación por caso</h2>
            <div class="typing-indicator muted" id="typingIndicator" style="display:none;"></div>
            <div class="chat-messages" id="chatMessages" data-last-id="<?php echo $lastMessageId; ?>">
                <?php foreach ($messages as $msg): ?>
                    <?php $mine = $msg['sender_id'] === $userId; ?>
                    <article class="chat-bubble <?php echo $mine ? 'mine' : 'other'; ?>" data-message-id="<?php echo (int) $msg['id']; ?>">
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
                                        <?php if ($isImage): ?><img src="<?php echo htmlspecialchars($attachment['file_path']); ?>" alt="Evidencia"><?php endif; ?>
                                        <span><?php echo htmlspecialchars($attachment['original_name']); ?></span>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </article>
                <?php endforeach; ?>
                <?php if (!$messages): ?><p class="muted" id="noMessagesText">No hay mensajes aún. Inicia la conversación del caso.</p><?php endif; ?>
            </div>
        </section>

        <section class="card" id="composer">
            <h2>Enviar mensaje</h2>
            <div class="alert error" id="sendError" style="display:none;"></div>
            <form id="chatForm" enctype="multipart/form-data" class="form-grid">
                <input type="hidden" name="ticket_id" value="<?php echo (int) $ticket['id']; ?>">
                <label>Mensaje
                    <textarea name="message" id="messageInput" rows="4" placeholder="Escribe detalles, avances, dudas o solución aplicada..."></textarea>
                </label>
                <label>Adjuntar evidencia (JPG, PNG, WEBP o PDF · máx 10MB)
                    <input type="file" name="evidence" id="evidenceInput" accept=".jpg,.jpeg,.png,.webp,.pdf">
                </label>
                <button class="btn primary" type="submit">Enviar al chat del caso</button>
            </form>
        </section>
    </main>
</div>

<script>
(() => {
  const ticketId = <?php echo (int) $ticket['id']; ?>;
  const currentUserId = <?php echo $userId; ?>;
  const chatMessages = document.getElementById('chatMessages');
  const form = document.getElementById('chatForm');
  const messageInput = document.getElementById('messageInput');
  const evidenceInput = document.getElementById('evidenceInput');
  const noMessagesText = document.getElementById('noMessagesText');
  const sendError = document.getElementById('sendError');
  const typingIndicator = document.getElementById('typingIndicator');
  let lastMessageId = Number(chatMessages?.dataset.lastId || 0);
  let typingTimeout = null;
  let isTypingSent = false;

  function escapeHtml(value) {
    return value
      .replaceAll('&', '&amp;')
      .replaceAll('<', '&lt;')
      .replaceAll('>', '&gt;')
      .replaceAll('"', '&quot;')
      .replaceAll("'", '&#039;');
  }

  function attachmentHtml(attachment) {
    const isImage = attachment.file_type.startsWith('image/');
    const safePath = escapeHtml(attachment.file_path);
    const safeName = escapeHtml(attachment.original_name);
    return `<a href="${safePath}" target="_blank" class="attachment-item">${isImage ? `<img src="${safePath}" alt="Evidencia">` : ''}<span>${safeName}</span></a>`;
  }

  function messageHtml(msg) {
    const mine = Number(msg.sender_id) === Number(currentUserId);
    const attachments = msg.attachments && msg.attachments.length
      ? `<div class="attachments">${msg.attachments.map(attachmentHtml).join('')}</div>`
      : '';

    const text = msg.message
      ? `<p>${escapeHtml(msg.message).replaceAll('\n', '<br>')}</p>`
      : '';

    return `
      <article class="chat-bubble ${mine ? 'mine' : 'other'}" data-message-id="${msg.id}">
        <header>
          <strong>${escapeHtml(msg.full_name)}</strong>
          <span class="muted"> · ${new Date(msg.created_at.replace(' ', 'T')).toLocaleString('es-ES')}</span>
        </header>
        ${text}
        ${attachments}
      </article>
    `;
  }

  function appendMessages(messages) {
    if (!messages.length) return;
    if (noMessagesText) noMessagesText.remove();

    messages.forEach((msg) => {
      const exists = chatMessages.querySelector(`[data-message-id="${msg.id}"]`);
      if (exists) return;
      chatMessages.insertAdjacentHTML('beforeend', messageHtml(msg));
      lastMessageId = Math.max(lastMessageId, Number(msg.id));
    });

    chatMessages.dataset.lastId = String(lastMessageId);
    chatMessages.scrollTop = chatMessages.scrollHeight;
  }

  function renderTyping(names) {
    if (!names || names.length === 0) {
      typingIndicator.style.display = 'none';
      typingIndicator.textContent = '';
      return;
    }

    const displayName = names[0];
    typingIndicator.style.display = 'block';
    typingIndicator.textContent = `${displayName} está escribiendo... · · ·`;
  }

  async function apiRequest(url, options = {}) {
    const response = await fetch(url, options);
    return response.json();
  }

  async function pollMessages() {
    try {
      const data = await apiRequest(`ticket_chat.php?api=1&action=fetch&ticket_id=${ticketId}&since_id=${lastMessageId}`);
      if (data.ok) {
        appendMessages(data.messages || []);
        renderTyping(data.typing || []);
      }
    } catch (error) {
      // ignore transient polling errors
    }
  }

  async function sendTyping(isTyping) {
    if (isTypingSent === isTyping) return;
    isTypingSent = isTyping;

    const payload = new URLSearchParams();
    payload.append('action', 'typing');
    payload.append('ticket_id', String(ticketId));
    payload.append('is_typing', isTyping ? '1' : '0');

    try {
      await apiRequest('ticket_chat.php?api=1', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: payload.toString(),
      });
    } catch (error) {
      // ignore typing signal errors
    }
  }

  messageInput?.addEventListener('input', () => {
    sendTyping(true);
    clearTimeout(typingTimeout);
    typingTimeout = setTimeout(() => sendTyping(false), 1200);
  });

  form?.addEventListener('submit', async (event) => {
    event.preventDefault();
    sendError.style.display = 'none';

    const formData = new FormData(form);
    formData.append('action', 'send');

    try {
      const data = await apiRequest('ticket_chat.php?api=1', {
        method: 'POST',
        body: formData,
      });

      if (!data.ok) {
        sendError.textContent = data.error || 'No se pudo enviar el mensaje.';
        sendError.style.display = 'block';
        return;
      }

      appendMessages(data.messages || []);
      renderTyping(data.typing || []);
      form.reset();
      messageInput.value = '';
      evidenceInput.value = '';
      sendTyping(false);
    } catch (error) {
      sendError.textContent = 'No se pudo enviar el mensaje por un error de red.';
      sendError.style.display = 'block';
    }
  });

  setInterval(pollMessages, 2000);
  pollMessages();
})();
</script>
</body>
</html>
