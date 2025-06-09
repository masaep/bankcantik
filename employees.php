<?php
// employees.php - Manajemen Karyawan Admin (MEMILIKI KERENTANAN)
require_once 'config.php';

if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header('Location: index.php');
    exit;
}

// Kontrol Akses: Hanya admin yang bisa mengakses halaman ini
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    echo '<div class="alert alert-error">Anda tidak memiliki izin untuk mengakses halaman ini.</div>';
    echo '<div class="text-center"><p><a href="dashboard.php" class="btn-link">Kembali ke Dashboard Keuangan</a></p></div>';
    exit;
}

$employees = [];
$search_employee_query = $_GET['search_employee_query'] ?? '';
$message = '';

// Ambil data pegawai
// Kerentanan 1: SQL Injection pada search_employee_query
$sql = "SELECT id, name, position, salary, hire_date FROM employees";
if (!empty($search_employee_query)) {
    $sql .= " WHERE name LIKE '%$search_employee_query%' OR position LIKE '%$search_employee_query%'";
}
$sql .= " ORDER BY name ASC";

if ($result = $conn->query($sql)) {
    while ($row = $result->fetch_assoc()) {
        $employees[] = $row;
    }
    $result->free();
} else {
    // Kerentanan 2: Error Disclosure
    $message = '<div class="alert alert-error">Gagal mengambil data pegawai: ' . htmlspecialchars($conn->error) . '</div>';
}

// Tambah Pegawai Baru
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_employee'])) {
    $name = $_POST['name'] ?? '';
    $position = $_POST['position'] ?? '';
    $salary = floatval($_POST['salary'] ?? 0);
    $hire_date = $_POST['hire_date'] ?? '';

    if (empty($name) || empty($position) || $salary <= 0 || empty($hire_date)) {
        $message = '<div class="alert alert-error">Harap lengkapi semua bidang dengan benar dan pastikan gaji lebih dari 0.</div>';
    } else {
        // Kerentanan 3: Stored XSS pada name dan position
        $sql_insert = "INSERT INTO employees (name, position, salary, hire_date) VALUES ('$name', '$position', '$salary', '$hire_date')";

        if ($conn->query($sql_insert)) {
            $message = '<div class="alert alert-success">Pegawai baru "' . htmlspecialchars($name) . '" berhasil ditambahkan.</div>';
            header('Location: employees.php');
            exit;
        } else {
            echo "<div class='alert alert-error'>Gagal menambahkan pegawai: " . htmlspecialchars($conn->error) . "</div>";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manajemen Pegawai - Bank Cantik</title>
    <link rel="stylesheet" href="css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;600;700&display=swap" rel="stylesheet">
</head>
<body>
    <div class="app-header">
        <div class="header-container">
            <a href="dashboard.php" class="logo">Bank Cantik</a>
            <div class="header-nav">
                <span class="welcome-text">Selamat Datang, <?php echo htmlspecialchars($_SESSION['username']); ?>!</span>
                <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
                    <a href="employees.php" class="btn btn-secondary">Manajemen Pegawai</a>
                    <a href="reports.php" class="btn btn-secondary">Laporan Keuangan</a>
                <?php endif; ?>
                <a href="logout.php" class="btn btn-logout">Logout</a>
            </div>
        </div>
    </div>

    <div class="main-content">
        <?php if (!empty($message)): ?>
            <?php echo $message; ?>
        <?php endif; ?>

        <div class="card">
            <div class="card-header">Tambah Pegawai Baru</div>
            <form action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" method="post">
                <input type="hidden" name="add_employee" value="1">
                <div class="form-group">
                    <label for="name">Nama Pegawai</label>
                    <input type="text" id="name" name="name" required>
                </div>
                <div class="form-group">
                    <label for="position">Posisi</label>
                    <input type="text" id="position" name="position" required>
                </div>
                <div class="form-group">
                    <label for="salary">Gaji</label>
                    <input type="number" id="salary" step="0.01" name="salary" required>
                </div>
                <div class="form-group">
                    <label for="hire_date">Tanggal Rekrut</label>
                    <input type="date" id="hire_date" name="hire_date" required>
                </div>
                <button type="submit" class="btn btn-primary">Tambah Pegawai</button>
            </form>
        </div>

        <div class="card">
            <div class="card-header">Cari Pegawai</div>
            <form action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" method="get">
                <div class="form-group">
                    <label for="search_employee_query">Cari Nama/Posisi Pegawai:</label>
                    <input type="text" id="search_employee_query" name="search_employee_query" value="<?php echo htmlspecialchars($search_employee_query); ?>" placeholder="Contoh: John Doe">
                </div>
                <button type="submit" class="btn btn-secondary">Cari</button>
            </form>
        </div>

        <div class="card">
            <div class="card-header">Daftar Pegawai</div>
            <?php if (empty($employees)): ?>
                <p class="text-center">Tidak ada data pegawai ditemukan.</p>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Nama</th>
                            <th>Posisi</th>
                            <th>Gaji</th>
                            <th>Tanggal Rekrut</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($employees as $employee): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($employee['id']); ?></td>
                                <td><?php echo $employee['name']; ?></td>
                                <td><?php echo $employee['position']; ?></td>
                                <td>Rp <?php echo number_format($employee['salary'], 2, ',', '.'); ?></td>
                                <td><?php echo htmlspecialchars($employee['hire_date']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>