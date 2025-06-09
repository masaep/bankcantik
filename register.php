<?php
// register.php - Register (Rentan Stored XSS)
require_once 'config.php';

$username = $password = $confirm_password = '';
$username_err = $password_err = $confirm_password_err = '';
$registration_success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Username
    if (empty(trim($_POST['username']))) {
        $username_err = 'Masukkan username.';
    } else {
        $sql = "SELECT id FROM users WHERE username = ?";
        if ($stmt = $conn->prepare($sql)) {
            $stmt->bind_param('s', $param_username);
            $param_username = trim($_POST['username']);
            if ($stmt->execute()) {
                $stmt->store_result();
                if ($stmt->num_rows === 1) {
                    $username_err = 'Username ini sudah digunakan.';
                } else {
                    $username = trim($_POST['username']);
                }
            } else {
                echo "<div class='alert alert-error'>Ada yang salah. Silakan coba lagi nanti: " . htmlspecialchars($stmt->error) . "</div>";
            }
            $stmt->close();
        }
    }

    // Password
    if (empty(trim($_POST['password']))) {
        $password_err = 'Masukkan password.';
    } elseif (strlen(trim($_POST['password'])) < 6) {
        $password_err = 'Password harus memiliki setidaknya 6 karakter.';
    } else {
        $password = trim($_POST['password']);
    }

    // Konfirmasi Password
    if (empty(trim($_POST['confirm_password']))) {
        $confirm_password_err = 'Konfirmasi password.';
    } else {
        $confirm_password = trim($_POST['confirm_password']);
        if (empty($password_err) && ($password != $confirm_password)) {
            $confirm_password_err = 'Password tidak cocok.';
        }
    }

    // Jika tidak ada error, coba insert user
    if (empty($username_err) && empty($password_err) && empty($confirm_password_err)) {
        // Kerentanan Stored XSS pada username (jika username ditampilkan di Dashboard tanpa htmlspecialchars)
        $sql = "INSERT INTO users (username, password, role) VALUES (?, ?, 'user')";
        if ($stmt = $conn->prepare($sql)) {
            $stmt->bind_param('ss', $param_username, $param_password);
            $param_username = $username; // Input username langsung ke DB tanpa htmlspecialchars
            $param_password = password_hash($password, PASSWORD_DEFAULT);
            
            if ($stmt->execute()) {
                $registration_success = '<div class="alert alert-success">Registrasi berhasil. Silakan <a href="index.php" class="btn-link">login</a>.</div>';
            } else {
                echo "<div class='alert alert-error'>Ada yang salah saat registrasi. Silakan coba lagi nanti: " . htmlspecialchars($stmt->error) . "</div>";
            }
            $stmt->close();
        }
    }
    $conn->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registrasi - Bank Cantik</title>
    <link rel="stylesheet" href="css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;600;700&display=swap" rel="stylesheet">
</head>
<body style="background-color: var(--light-bg);">
    <div class="app-header">
        <div class="header-container">
            <a href="index.php" class="logo">Bank Cantik</a>
            <div class="navbar">
                <a href="index.php">Login</a>
            </div>
        </div>
    </div>

    <div class="main-content">
        <div class="card" style="max-width: 400px; margin: 2rem auto;">
            <div class="card-header">Daftar Akun Baru</div>
            <?php if (!empty($username_err) || !empty($password_err) || !empty($confirm_password_err)): ?>
                <div class="alert alert-error">
                    <?php echo $username_err; ?> <?php echo $password_err; ?> <?php echo $confirm_password_err; ?>
                </div>
            <?php endif; ?>
            <?php echo $registration_success; ?>
            <form action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" method="post">
                <div class="form-group">
                    <label for="username">Username</label>
                    <input type="text" id="username" name="username" value="<?php echo htmlspecialchars($username); ?>" required>
                </div>
                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" required>
                </div>
                <div class="form-group">
                    <label for="confirm_password">Konfirmasi Password</label>
                    <input type="password" id="confirm_password" name="confirm_password" required>
                </div>
                <button type="submit" class="btn btn-primary">Daftar</button>
            </form>
            <p class="text-center my-4">Sudah punya akun? <a href="index.php" class="btn-link">Login di sini</a>.</p>
        </div>
    </div>
</body>
</html>