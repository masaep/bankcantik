<?php
// index.php (Login) - VERSI RENTAN SQL INJECTION
require_once 'config.php';

if (isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true) {
    header('Location: dashboard.php');
    exit;
}

$username = $password = '';
$username_err = $password_err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (empty(trim($_POST['username']))) {
        $username_err = 'Masukkan username.';
    } else {
        $username = $_POST['username']; // TIDAK ADA trim(), TIDAK ADA escape()
    }

    if (empty(trim($_POST['password']))) {
        $password_err = 'Masukkan password.';
    } else {
        $password = $_POST['password']; // TIDAK ADA trim(), TIDAK ADA escape()
    }

    if (empty($username_err) && empty($password_err)) {
        // Kerentanan SQL Injection: Input pengguna langsung disisipkan ke dalam query SQL.
        $sql = "SELECT id, username, password, role FROM users WHERE username = '$username'";

        if ($result = $conn->query($sql)) {
            if ($result->num_rows === 1) {
                $row = $result->fetch_assoc();
                $id = $row['id'];
                $db_username = $row['username'];
                $hashed_password_from_db = $row['password'];
                $role = $row['role'];
                
                $_SESSION['loggedin'] = true;
                $_SESSION['id'] = $id;
                $_SESSION['username'] = $db_username;
                $_SESSION['role'] = $role;
                header('Location: dashboard.php');
                exit;

            } else {
                $username_err = 'Kombinasi username/password salah atau tidak ada akun ditemukan.';
            }
        } else {
            // Kerentanan: Error Disclosure
            echo "<div class='alert alert-error'>Ada yang salah dengan query database: " . htmlspecialchars($conn->error) . "</div>";
        }
    }
    if ($conn->ping()) {
        $conn->close();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Bank Cantik</title>
    <link rel="stylesheet" href="css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;600;700&display=swap" rel="stylesheet">
</head>
<body style="background-color: var(--light-bg);">
    <div class="app-header">
        <div class="header-container">
            <a href="index.php" class="logo">Bank Cantik</a>
            <div class="navbar">
                <a href="register.php">Register</a>
            </div>
        </div>
    </div>

    <div class="main-content">
        <div class="card" style="max-width: 400px; margin: 2rem auto;">
            <div class="card-header">Login ke Akun Anda</div>
            <?php if (!empty($username_err) || !empty($password_err)): ?>
                <div class="alert alert-error">
                    <?php echo $username_err; ?> <?php echo $password_err; ?>
                </div>
            <?php endif; ?>
            <form action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" method="post">
                <div class="form-group">
                    <label for="username">Username</label>
                    <input type="text" id="username" name="username" value="<?php echo htmlspecialchars($username); ?>" required>
                </div>
                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" required>
                </div>
                <button type="submit" class="btn btn-primary">Login</button>
            </form>
            <p class="text-center my-4">Belum punya akun? <a href="register.php" class="btn-link">Daftar sekarang</a>.</p>
        </div>
    </div>
</body>
</html>