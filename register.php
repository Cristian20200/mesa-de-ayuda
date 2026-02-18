<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';

if (is_logged_in()) {
    $target = $_SESSION['user']['role'] === 'systems' ? 'dashboard_system.php' : 'dashboard_user.php';
    header('Location: ' . $target);
    exit;
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fullName = trim($_POST['full_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $area = trim($_POST['area'] ?? '');
    $password = $_POST['password'] ?? '';

    if (!$fullName || !$email || !$area || strlen($password) < 8) {
        $error = 'Completa todos los campos y usa una contraseña de al menos 8 caracteres.';
    } else {
        $check = $mysqli->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
        $check->bind_param('s', $email);
        $check->execute();
        $exists = $check->get_result()->fetch_assoc();
        $check->close();

        if ($exists) {
            $error = 'Ya existe una cuenta con ese correo.';
        } else {
            $hash = password_hash($password, PASSWORD_BCRYPT);
            $role = 'requester';
            $stmt = $mysqli->prepare('INSERT INTO users (full_name, email, area, password_hash, role) VALUES (?, ?, ?, ?, ?)');
            $stmt->bind_param('sssss', $fullName, $email, $area, $hash, $role);
            $stmt->execute();
            $stmt->close();
            $success = 'Registro creado. Ya puedes iniciar sesión.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registro | Mesa de Ayuda</title>
    <link rel="stylesheet" href="assets/css/styles.css">
</head>
<body class="auth-page">
    <main class="auth-card">
        <h1>Crear cuenta</h1>
        <p>Solicitante para cualquier área de la empresa.</p>

        <?php if ($error): ?>
            <div class="alert error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="alert success"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>

        <form method="POST" class="form-grid">
            <label>Nombre completo<input type="text" name="full_name" required></label>
            <label>Correo<input type="email" name="email" required></label>
            <label>Área<input type="text" name="area" required></label>
            <label>Contraseña<input type="password" name="password" minlength="8" required></label>
            <button type="submit" class="btn primary">Registrar</button>
        </form>

        <p class="muted"><a href="index.php">Volver al acceso</a></p>
    </main>
</body>
</html>
