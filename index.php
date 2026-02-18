<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';

if (is_logged_in()) {
    $target = $_SESSION['user']['role'] === 'systems' ? 'dashboard_system.php' : 'dashboard_user.php';
    header('Location: ' . $target);
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    $stmt = $mysqli->prepare('SELECT id, full_name, email, area, password_hash, role FROM users WHERE email = ? LIMIT 1');
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    $stmt->close();

    if ($user && password_verify($password, $user['password_hash'])) {
        $_SESSION['user'] = [
            'id' => (int) $user['id'],
            'full_name' => $user['full_name'],
            'email' => $user['email'],
            'area' => $user['area'],
            'role' => $user['role'],
        ];

        $target = $user['role'] === 'systems' ? 'dashboard_system.php' : 'dashboard_user.php';
        header('Location: ' . $target);
        exit;
    }

    $error = 'Credenciales inválidas. Intenta nuevamente.';
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mesa de Ayuda | Acceso</title>
    <link rel="stylesheet" href="assets/css/styles.css">
</head>
<body class="auth-page">
    <main class="auth-card">
        <h1>Mesa de Ayuda TI</h1>
        <p>Gestiona casos entre todas las áreas y Sistemas.</p>

        <?php if ($error): ?>
            <div class="alert error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <form method="POST" class="form-grid">
            <label>
                Correo
                <input type="email" name="email" required>
            </label>
            <label>
                Contraseña
                <input type="password" name="password" required>
            </label>
            <button type="submit" class="btn primary">Ingresar</button>
        </form>

        <p class="muted">¿No tienes cuenta? <a href="register.php">Regístrate</a></p>
        <p class="muted small">Usuario de Sistemas demo: <strong>sistemas@empresa.com</strong> / <strong>Sistemas123!</strong></p>
    </main>
</body>
</html>
