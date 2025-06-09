<?php
// reports.php - Modul Laporan Keuangan Sensitif (MEMILIKI KERENTANAN)
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

$report_data = [];
$message = '';
$print_format = $_GET['print_format'] ?? 'html'; // Parameter untuk fitur cetak, default html

// Ambil semua transaksi (untuk laporan keuangan sensitif)
// --- PERBAIKAN QUERY DI SINI ---
$sql = "SELECT u.username, ft.description, ft.amount, ft.type, ft.transaction_date
        FROM financial_transactions ft
        JOIN accounts a ON ft.account_id = a.id  -- Join ke tabel accounts
        JOIN users u ON a.user_id = u.id        -- Lalu join dari accounts ke users
        ORDER BY ft.transaction_date DESC LIMIT 50";

if ($result = $conn->query($sql)) {
    while ($row = $result->fetch_assoc()) {
        $report_data[] = $row;
    }
    $result->free();
} else {
    // Kerentanan 1: Error Disclosure
    $message .= '<div class="alert alert-error">Gagal mengambil data laporan: ' . htmlspecialchars($conn->error) . '</div>';
}


// --- Data untuk Grafik ---
$monthly_summary = [];
foreach ($report_data as $transaction) {
    $month_year = date('Y-m', strtotime($transaction['transaction_date']));
    if (!isset($monthly_summary[$month_year])) {
        $monthly_summary[$month_year] = ['income' => 0, 'expense' => 0];
    }
    if ($transaction['type'] == 'credit') { // 'credit' = pemasukan
        $monthly_summary[$month_year]['income'] += $transaction['amount'];
    } else { // 'debit' = pengeluaran
        $monthly_summary[$month_year]['expense'] += $transaction['amount'];
    }
}
ksort($monthly_summary); // Urutkan berdasarkan bulan

$chart_labels = json_encode(array_keys($monthly_summary));
$chart_income_data = json_encode(array_column($monthly_summary, 'income'));
$chart_expense_data = json_encode(array_column($monthly_summary, 'expense'));


// --- Logika Kerentanan Local File Inclusion (LFI) / Path Traversal ---
if (isset($_GET['action']) && $_GET['action'] === 'print') {
    // Kerentanan 2: Local File Inclusion (LFI) / Path Traversal
    $template_file = 'templates/report_template_' . $print_format . '.php';

    if (file_exists($template_file)) {
        ob_start();
        include $template_file;
        $print_content = ob_get_clean();
        
        // Simulasikan cetak (ditampilkan sebagai pesan untuk demonstrasi)
        $message .= '<div class="alert alert-success">Laporan Siap Cetak (Output dari ' . htmlspecialchars($template_file) . '):<br><pre>' . $print_content . '</pre></div>';

    } else {
        $message .= '<div class="alert alert-error">Format cetak atau template tidak ditemukan: ' . htmlspecialchars($template_file) . '</div>';
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laporan Keuangan Sensitif - Bank Cantik</title>
    <link rel="stylesheet" href="css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
            <div class="card-header">Laporan Keuangan Bank Cantik</div>
            <p class="text-center alert alert-info">Anda masuk sebagai Administrator. Anda dapat melihat semua transaksi.</p>

            <h2 style="margin-top: 30px;">Grafik Pemasukan dan Pengeluaran Bulanan</h2>
            <canvas id="financialChart" style="max-height: 400px;"></canvas>
            <script>
                const ctx = document.getElementById('financialChart');
                new Chart(ctx, {
                    type: 'bar',
                    data: {
                        labels: <?php echo $chart_labels; ?>,
                        datasets: [{
                            label: 'Pemasukan',
                            data: <?php echo $chart_income_data; ?>,
                            backgroundColor: 'rgba(75, 192, 192, 0.6)',
                            borderColor: 'rgba(75, 192, 192, 1)',
                            borderWidth: 1
                        }, {
                            label: 'Pengeluaran',
                            data: <?php echo $chart_expense_data; ?>,
                            backgroundColor: 'rgba(255, 99, 132, 0.6)',
                            borderColor: 'rgba(255, 99, 132, 1)',
                            borderWidth: 1
                        }]
                    },
                    options: {
                        responsive: true,
                        scales: {
                            y: {
                                beginAtZero: true
                            }
                        }
                    }
                });
            </script>

            <h2 style="margin-top: 30px;">Detail Transaksi Terbaru</h2>
            <?php if (empty($report_data)): ?>
                <p class="text-center">Tidak ada data laporan yang tersedia.</p>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>Pengguna</th>
                            <th>Deskripsi</th>
                            <th>Jumlah</th>
                            <th>Tipe</th>
                            <th>Tanggal</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($report_data as $row): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($row['username']); ?></td>
                                <td><?php echo $row['description']; ?></td>
                                <td>Rp <?php echo number_format($row['amount'], 2, ',', '.'); ?></td>
                                <td><?php echo htmlspecialchars($row['type'] == 'income' ? 'Pemasukan' : 'Pengeluaran'); ?></td>
                                <td><?php htmlspecialchars($row['transaction_date']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>

            <h2 style="margin-top: 30px;">Cetak Laporan</h2>
            <form action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" method="get">
                <input type="hidden" name="action" value="print">
                <div class="form-group">
                    <label for="print_format">Pilih Format Cetak:</label>
                    <select id="print_format" name="print_format">
                        <option value="html">HTML</option>
                        <option value="txt">Teks Biasa</option>
                    </select>
                </div>
                <button type="submit" class="btn btn-primary">Cetak Laporan</button>
            </form>
        </div>
    </div>
</body>
</html>