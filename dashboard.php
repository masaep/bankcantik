<?php
// dashboard.php - Dashboard Keuangan Pengguna (MEMILIKI KERENTANAN)
require_once 'config.php';

if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header('Location: index.php');
    exit;
}

$user_id = $_SESSION['id'];
$username = $_SESSION['username'];
$accounts = [];
$transactions = [];
$loans = [];
$message = '';
$current_tab = $_GET['tab'] ?? 'accounts'; // Default tab

// --- Fetch Accounts ---
$sql_accounts = "SELECT id, account_number, balance, account_type FROM accounts WHERE user_id = ?";
if ($stmt_accounts = $conn->prepare($sql_accounts)) {
    $stmt_accounts->bind_param('i', $user_id);
    if ($stmt_accounts->execute()) {
        $result_accounts = $stmt_accounts->get_result();
        while ($row = $result_accounts->fetch_assoc()) {
            $accounts[] = $row;
        }
    } else {
        $message .= "<div class='alert alert-error'>Gagal mengambil rekening: " . htmlspecialchars($stmt_accounts->error) . "</div>";
    }
    $stmt_accounts->close();
} else {
    $message .= "<div class='alert alert-error'>Gagal mempersiapkan rekening: " . htmlspecialchars($conn->error) . "</div>";
}

// --- Fetch Loans ---
$sql_loans = "SELECT id, loan_amount, remaining_amount, interest_rate, monthly_payment_amount, status, next_payment_date FROM loans WHERE user_id = ?";
if ($stmt_loans = $conn->prepare($sql_loans)) {
    $stmt_loans->bind_param('i', $user_id);
    if ($stmt_loans->execute()) {
        $result_loans = $stmt_loans->get_result();
        while ($row = $result_loans->fetch_assoc()) {
            $loans[] = $row;
        }
    } else {
        $message .= "<div class='alert alert-error'>Gagal mengambil pinjaman: " . htmlspecialchars($stmt_loans->error) . "</div>";
    }
    $stmt_loans->close();
} else {
    $message .= "<div class='alert alert-error'>Gagal mempersiapkan pinjaman: " . htmlspecialchars($conn->error) . "</div>";
}

// --- Fetch Transactions (Mutasi Rekening) ---
$selected_account_id = $_GET['account_id'] ?? ($accounts[0]['id'] ?? 0); // Default ke rekening pertama
$search_query = $_GET['search_query'] ?? '';

// Kerentanan: SQL Injection pada search_query
$sql_transactions = "SELECT description, amount, type, transaction_date FROM financial_transactions WHERE account_id = '$selected_account_id'"; // IDOR: selected_account_id tidak divalidasi kepemilikannya

if (!empty($search_query)) {
    // Kerentanan SQL Injection di sini!
    $sql_transactions .= " AND description LIKE '%$search_query%'";
}
$sql_transactions .= " ORDER BY transaction_date DESC";

if ($result_transactions = $conn->query($sql_transactions)) {
    while ($row = $result_transactions->fetch_assoc()) {
        $transactions[] = $row;
    }
} else {
    $message .= "<div class='alert alert-error'>Gagal mengambil mutasi: " . htmlspecialchars($conn->error) . "</div>";
}

// --- Proses Transfer Dana ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'transfer') {
    $from_account_id = intval($_POST['from_account_id'] ?? 0);
    $to_account_number = $_POST['to_account_number'] ?? '';
    $amount = floatval($_POST['amount'] ?? 0);
    $description = $_POST['description'] ?? '';

    // Kerentanan: IDOR - from_account_id tidak divalidasi kepemilikannya
    // Kerentanan: Race Condition - Tidak ada locking yang kuat
    // Kerentanan: SQL Injection pada description
    
    // Validasi dasar
    if ($from_account_id <= 0 || empty($to_account_number) || $amount <= 0 || empty($description)) {
        $message = '<div class="alert alert-error">Harap lengkapi semua detail transfer.</div>';
    } else {
        $conn->autocommit(FALSE); // Mulai transaksi manual

        try {
            // Ambil saldo rekening pengirim (Race Condition di sini)
            $sql_get_balance = "SELECT balance FROM accounts WHERE id = $from_account_id FOR UPDATE"; // FOR UPDATE untuk mengunci baris (parsial)
            $res_balance = $conn->query($sql_get_balance);
            if (!$res_balance || $res_balance->num_rows === 0) {
                throw new Exception("Rekening pengirim tidak ditemukan atau tidak valid.");
            }
            $sender_balance = $res_balance->fetch_assoc()['balance'];

            if ($sender_balance < $amount) {
                throw new Exception("Saldo tidak mencukupi untuk transfer.");
            }

            // Temukan rekening tujuan
            $sql_get_receiver = "SELECT id FROM accounts WHERE account_number = '$to_account_number'";
            $res_receiver = $conn->query($sql_get_receiver);
            if (!$res_receiver || $res_receiver->num_rows === 0) {
                throw new Exception("Rekening tujuan tidak ditemukan.");
            }
            $to_account_id = $res_receiver->fetch_assoc()['id'];

            // Update saldo pengirim
            $sql_update_sender = "UPDATE accounts SET balance = balance - $amount WHERE id = $from_account_id";
            if (!$conn->query($sql_update_sender)) {
                throw new Exception("Gagal mengurangi saldo pengirim: " . $conn->error);
            }

            // Update saldo penerima
            $sql_update_receiver = "UPDATE accounts SET balance = balance + $amount WHERE id = $to_account_id";
            if (!$conn->query($sql_update_receiver)) {
                throw new Exception("Gagal menambah saldo penerima: " . $conn->error);
            }

            // Catat transaksi pengirim (debit)
            $description_escaped = mysqli_real_escape_string($conn, $description); // Kerentanan XSS/SQLI jika description buruk
            $sql_log_debit = "INSERT INTO financial_transactions (account_id, description, amount, type) VALUES ($from_account_id, '$description_escaped', $amount, 'debit')";
            if (!$conn->query($sql_log_debit)) {
                throw new Exception("Gagal mencatat transaksi debit: " . $conn->error);
            }

            // Catat transaksi penerima (credit)
            $sql_log_credit = "INSERT INTO financial_transactions (account_id, description, amount, type) VALUES ($to_account_id, 'Transfer Masuk dari $username', $amount, 'credit')";
            if (!$conn->query($sql_log_credit)) {
                throw new Exception("Gagal mencatat transaksi kredit: " . $conn->error);
            }

            $conn->commit();
            $message = '<div class="alert alert-success">Transfer dana berhasil!</div>';
            // Refresh halaman untuk update saldo
            header('Location: dashboard.php?tab=transfer');
            exit;

        } catch (Exception $e) {
            $conn->rollback();
            $message = '<div class="alert alert-error">Transfer gagal: ' . htmlspecialchars($e->getMessage()) . '</div>';
        }
        $conn->autocommit(TRUE);
    }
}

// --- Proses Pembayaran Tagihan (Simulasi) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'pay_bill') {
    $from_account_id = intval($_POST['bill_from_account_id'] ?? 0);
    $bill_amount = floatval($_POST['bill_amount'] ?? 0);
    $bill_description = $_POST['bill_description'] ?? '';

    // Kerentanan: IDOR - from_account_id tidak divalidasi kepemilikannya
    // Kerentanan: SQL Injection pada bill_description
    
    // Validasi dasar
    if ($from_account_id <= 0 || $bill_amount <= 0 || empty($bill_description)) {
        $message = '<div class="alert alert-error">Harap lengkapi semua detail pembayaran tagihan.</div>';
    } else {
        $conn->autocommit(FALSE);
        try {
            $sql_get_balance = "SELECT balance FROM accounts WHERE id = $from_account_id FOR UPDATE";
            $res_balance = $conn->query($sql_get_balance);
            if (!$res_balance || $res_balance->num_rows === 0) {
                throw new Exception("Rekening pengirim tagihan tidak ditemukan atau tidak valid.");
            }
            $sender_balance = $res_balance->fetch_assoc()['balance'];

            if ($sender_balance < $bill_amount) {
                throw new Exception("Saldo tidak mencukupi untuk pembayaran tagihan.");
            }

            $sql_update_sender = "UPDATE accounts SET balance = balance - $bill_amount WHERE id = $from_account_id";
            if (!$conn->query($sql_update_sender)) {
                throw new Exception("Gagal mengurangi saldo untuk pembayaran tagihan: " . $conn->error);
            }
            
            $bill_description_escaped = mysqli_real_escape_string($conn, $bill_description); // Kerentanan XSS/SQLI
            $sql_log_debit = "INSERT INTO financial_transactions (account_id, description, amount, type) VALUES ($from_account_id, '$bill_description_escaped', $bill_amount, 'debit')";
            if (!$conn->query($sql_log_debit)) {
                throw new Exception("Gagal mencatat pembayaran tagihan: " . $conn->error);
            }

            $conn->commit();
            $message = '<div class="alert alert-success">Pembayaran tagihan berhasil!</div>';
            header('Location: dashboard.php?tab=bills');
            exit;

        } catch (Exception $e) {
            $conn->rollback();
            $message = '<div class="alert alert-error">Pembayaran tagihan gagal: ' . htmlspecialchars($e->getMessage()) . '</div>';
        }
        $conn->autocommit(TRUE);
    }
}

// --- Proses Pembayaran Cicilan Pinjaman ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'pay_loan') {
    $loan_id = intval($_POST['loan_id'] ?? 0);
    $pay_amount = floatval($_POST['pay_amount'] ?? 0);
    $from_account_id = intval($_POST['loan_from_account_id'] ?? 0);

    // Kerentanan: IDOR - loan_id dan from_account_id tidak divalidasi kepemilikannya
    // Kerentanan: Race Condition - Tidak ada locking kuat
    
    // Validasi dasar
    if ($loan_id <= 0 || $pay_amount <= 0 || $from_account_id <= 0) {
        $message = '<div class="alert alert-error">Harap lengkapi semua detail pembayaran pinjaman.</div>';
    } else {
        $conn->autocommit(FALSE);
        try {
            // Ambil detail pinjaman
            $sql_get_loan = "SELECT remaining_amount, monthly_payment_amount, status FROM loans WHERE id = $loan_id FOR UPDATE";
            $res_loan = $conn->query($sql_get_loan);
            if (!$res_loan || $res_loan->num_rows === 0) {
                throw new Exception("Pinjaman tidak ditemukan atau tidak valid.");
            }
            $loan_details = $res_loan->fetch_assoc();

            if ($loan_details['status'] !== 'active') {
                throw new Exception("Pinjaman tidak aktif.");
            }
            if ($pay_amount > $loan_details['remaining_amount']) {
                throw new Exception("Jumlah pembayaran melebihi sisa pinjaman.");
            }

            // Ambil saldo rekening pembayar
            $sql_get_balance = "SELECT balance FROM accounts WHERE id = $from_account_id FOR UPDATE";
            $res_balance = $conn->query($sql_get_balance);
            if (!$res_balance || $res_balance->num_rows === 0) {
                throw new Exception("Rekening pembayar tidak ditemukan atau tidak valid.");
            }
            $payer_balance = $res_balance->fetch_assoc()['balance'];

            if ($payer_balance < $pay_amount) {
                throw new Exception("Saldo tidak mencukupi untuk pembayaran pinjaman.");
            }

            // Update sisa pinjaman
            $new_remaining = $loan_details['remaining_amount'] - $pay_amount;
            $sql_update_loan = "UPDATE loans SET remaining_amount = $new_remaining, status = IF($new_remaining <= 0, 'paid', 'active') WHERE id = $loan_id";
            if (!$conn->query($sql_update_loan)) {
                throw new Exception("Gagal memperbarui pinjaman: " . $conn->error);
            }

            // Update saldo rekening
            $sql_update_account = "UPDATE accounts SET balance = balance - $pay_amount WHERE id = $from_account_id";
            if (!$conn->query($sql_update_account)) {
                throw new Exception("Gagal mengurangi saldo rekening: " . $conn->error);
            }

            // Catat pembayaran pinjaman
            $sql_log_payment = "INSERT INTO loan_payments (loan_id, amount_paid) VALUES ($loan_id, $pay_amount)";
            if (!$conn->query($sql_log_payment)) {
                throw new Exception("Gagal mencatat pembayaran pinjaman: " . $conn->error);
            }

            // Catat sebagai transaksi keuangan (debit)
            $description_log = "Pembayaran Cicilan Pinjaman #" . $loan_id;
            $sql_log_debit = "INSERT INTO financial_transactions (account_id, description, amount, type) VALUES ($from_account_id, '$description_log', $pay_amount, 'debit')";
            if (!$conn->query($sql_log_debit)) {
                throw new Exception("Gagal mencatat transaksi pembayaran cicilan: " . $conn->error);
            }

            $conn->commit();
            $message = '<div class="alert alert-success">Pembayaran pinjaman berhasil! Sisa: Rp ' . number_format($new_remaining, 2, ',', '.') . '</div>';
            header('Location: dashboard.php?tab=loans');
            exit;

        } catch (Exception $e) {
            $conn->rollback();
            $message = '<div class="alert alert-error">Pembayaran pinjaman gagal: ' . htmlspecialchars($e->getMessage()) . '</div>';
        }
        $conn->autocommit(TRUE);
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Bank Cantik</title>
    <link rel="stylesheet" href="css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;600;700&display=swap" rel="stylesheet">
</head>
<body>
    <div class="app-header">
        <div class="header-container">
            <a href="dashboard.php" class="logo">Bank Cantik</a>
            <div class="header-nav">
                <span class="welcome-text">Selamat Datang, <?php echo htmlspecialchars($username); ?>!</span>
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

        <div class="tabs">
            <button class="tab-button <?php echo ($current_tab === 'accounts') ? 'active' : ''; ?>" onclick="openTab(event, 'accounts')">Rekening Saya</button>
            <button class="tab-button <?php echo ($current_tab === 'transactions') ? 'active' : ''; ?>" onclick="openTab(event, 'transactions')">Mutasi Rekening</button>
            <button class="tab-button <?php echo ($current_tab === 'transfer') ? 'active' : ''; ?>" onclick="openTab(event, 'transfer')">Transfer Dana</button>
            <button class="tab-button <?php echo ($current_tab === 'bills') ? 'active' : ''; ?>" onclick="openTab(event, 'bills')">Bayar Tagihan</button>
            <button class="tab-button <?php echo ($current_tab === 'loans') ? 'active' : ''; ?>" onclick="openTab(event, 'loans')">Pinjaman Saya</button>
        </div>

        <div id="accounts" class="tab-content <?php echo ($current_tab === 'accounts') ? 'active' : ''; ?>">
            <div class="card">
                <div class="card-header">Rekening Bank Anda</div>
                <?php if (empty($accounts)): ?>
                    <p class="text-center">Anda belum memiliki rekening.</p>
                <?php else: ?>
                    <div class="section-grid">
                        <?php foreach ($accounts as $account): ?>
                            <div class="card account-item">
                                <h3><?php echo htmlspecialchars($account['account_type']); ?></h3>
                                <p>No. Rekening: <strong><?php echo htmlspecialchars($account['account_number']); ?></strong></p>
                                <p>Saldo: <span class="balance">Rp <?php echo number_format($account['balance'], 2, ',', '.'); ?></span></p>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div id="transactions" class="tab-content <?php echo ($current_tab === 'transactions') ? 'active' : ''; ?>">
            <div class="card">
                <div class="card-header">Mutasi Rekening</div>
                <form action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" method="get">
                    <input type="hidden" name="tab" value="transactions">
                    <div class="form-group">
                        <label for="select_account_id">Pilih Rekening:</label>
                        <select id="select_account_id" name="account_id" onchange="this.form.submit()">
                            <?php foreach ($accounts as $account): ?>
                                <option value="<?php echo htmlspecialchars($account['id']); ?>" <?php echo ($selected_account_id == $account['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($account['account_number']); ?> (<?php echo htmlspecialchars($account['account_type']); ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </form>
                <form action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" method="get" class="my-4">
                    <input type="hidden" name="tab" value="transactions">
                    <input type="hidden" name="account_id" value="<?php echo htmlspecialchars($selected_account_id); ?>">
                    <div class="form-group">
                        <label for="search_query_transactions">Cari Deskripsi Transaksi:</label>
                        <input type="text" id="search_query_transactions" name="search_query" value="<?php echo htmlspecialchars($search_query); ?>" placeholder="Contoh: Transfer">
                    </div>
                    <button type="submit" class="btn btn-secondary">Cari Mutasi</button>
                </form>
                <?php if (empty($transactions)): ?>
                    <p class="text-center">Tidak ada mutasi ditemukan.</p>
                <?php else: ?>
                    <table>
                        <thead>
                            <tr>
                                <th>Deskripsi</th>
                                <th>Jumlah</th>
                                <th>Tipe</th>
                                <th>Tanggal</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($transactions as $transaction): ?>
                                <tr>
                                    <td><?php echo $transaction['description']; ?></td>
                                    <td>Rp <?php echo number_format($transaction['amount'], 2, ',', '.'); ?></td>
                                    <td><?php echo htmlspecialchars($transaction['type'] == 'debit' ? 'Debet' : 'Kredit'); ?></td>
                                    <td><?php echo htmlspecialchars($transaction['transaction_date']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>

        <div id="transfer" class="tab-content <?php echo ($current_tab === 'transfer') ? 'active' : ''; ?>">
            <div class="card">
                <div class="card-header">Transfer Dana</div>
                <form action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>?tab=transfer" method="post">
                    <input type="hidden" name="action" value="transfer">
                    <div class="form-group">
                        <label for="from_account_id">Dari Rekening:</label>
                        <select id="from_account_id" name="from_account_id" required>
                            <?php foreach ($accounts as $account): ?>
                                <option value="<?php echo htmlspecialchars($account['id']); ?>">
                                    <?php echo htmlspecialchars($account['account_number']); ?> (Saldo: Rp <?php echo number_format($account['balance'], 2, ',', '.'); ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="to_account_number">Nomor Rekening Tujuan:</label>
                        <input type="text" id="to_account_number" name="to_account_number" placeholder="BC123456789" required>
                    </div>
                    <div class="form-group">
                        <label for="amount">Jumlah Transfer:</label>
                        <input type="number" id="amount" name="amount" step="0.01" min="0.01" required>
                    </div>
                    <div class="form-group">
                        <label for="description">Deskripsi Transfer:</label>
                        <input type="text" id="description" name="description" required>
                    </div>
                    <button type="submit" class="btn btn-primary">Lakukan Transfer</button>
                </form>
            </div>
        </div>

        <div id="bills" class="tab-content <?php echo ($current_tab === 'bills') ? 'active' : ''; ?>">
            <div class="card">
                <div class="card-header">Bayar Tagihan</div>
                <form action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>?tab=bills" method="post">
                    <input type="hidden" name="action" value="pay_bill">
                    <div class="form-group">
                        <label for="bill_from_account_id">Bayar Dari Rekening:</label>
                        <select id="bill_from_account_id" name="bill_from_account_id" required>
                            <?php foreach ($accounts as $account): ?>
                                <option value="<?php echo htmlspecialchars($account['id']); ?>">
                                    <?php echo htmlspecialchars($account['account_number']); ?> (Saldo: Rp <?php echo number_format($account['balance'], 2, ',', '.'); ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="bill_amount">Jumlah Tagihan:</label>
                        <input type="number" id="bill_amount" name="bill_amount" step="0.01" min="0.01" required>
                    </div>
                    <div class="form-group">
                        <label for="bill_description">Deskripsi Tagihan (misal: Tagihan Listrik PLN, Internet IndiHome):</label>
                        <input type="text" id="bill_description" name="bill_description" required>
                    </div>
                    <button type="submit" class="btn btn-primary">Bayar Tagihan</button>
                </form>
            </div>
        </div>

        <div id="loans" class="tab-content <?php echo ($current_tab === 'loans') ? 'active' : ''; ?>">
            <div class="card">
                <div class="card-header">Pinjaman Anda</div>
                <?php if (empty($loans)): ?>
                    <p class="text-center">Anda tidak memiliki pinjaman aktif.</p>
                <?php else: ?>
                    <div class="section-grid">
                        <?php foreach ($loans as $loan): ?>
                            <div class="card loan-item">
                                <h3>Pinjaman #<?php echo htmlspecialchars($loan['id']); ?></h3>
                                <p>Jumlah Pinjaman: Rp <?php echo number_format($loan['loan_amount'], 2, ',', '.'); ?></p>
                                <p>Sisa Pinjaman: <span class="balance">Rp <?php echo number_format($loan['remaining_amount'], 2, ',', '.'); ?></span></p>
                                <p>Bunga: <?php echo htmlspecialchars($loan['interest_rate']); ?>%</p>
                                <p>Angsuran Bulanan: Rp <?php echo number_format($loan['monthly_payment_amount'], 2, ',', '.'); ?></p>
                                <p>Status: <span class="status-<?php echo htmlspecialchars($loan['status']); ?>"><?php echo htmlspecialchars(ucfirst($loan['status'])); ?></span></p>
                                <?php if ($loan['status'] === 'active'): ?>
                                    <p>Pembayaran Selanjutnya: <?php echo htmlspecialchars($loan['next_payment_date']); ?></p>
                                    <hr style="border-top: 1px dashed #ccc; margin: 15px 0;">
                                    <h4>Bayar Cicilan</h4>
                                    <form action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>?tab=loans" method="post">
                                        <input type="hidden" name="action" value="pay_loan">
                                        <input type="hidden" name="loan_id" value="<?php echo htmlspecialchars($loan['id']); ?>">
                                        <div class="form-group">
                                            <label for="loan_from_account_id_<?php echo $loan['id']; ?>">Bayar Dari Rekening:</label>
                                            <select id="loan_from_account_id_<?php echo $loan['id']; ?>" name="loan_from_account_id" required>
                                                <?php foreach ($accounts as $account): ?>
                                                    <option value="<?php echo htmlspecialchars($account['id']); ?>">
                                                        <?php echo htmlspecialchars($account['account_number']); ?> (Saldo: Rp <?php echo number_format($account['balance'], 2, ',', '.'); ?>)
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div class="form-group">
                                            <label for="pay_amount_<?php echo $loan['id']; ?>">Jumlah Pembayaran (Min: Rp <?php echo number_format($loan['monthly_payment_amount'], 2, ',', '.'); ?>):</label>
                                            <input type="number" id="pay_amount_<?php echo $loan['id']; ?>" name="pay_amount" step="0.01" min="<?php echo htmlspecialchars($loan['monthly_payment_amount']); ?>" max="<?php echo htmlspecialchars($loan['remaining_amount']); ?>" value="<?php echo htmlspecialchars($loan['monthly_payment_amount']); ?>" required>
                                        </div>
                                        <button type="submit" class="btn btn-primary">Bayar Sekarang</button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        // JavaScript untuk Tab Fungsionalitas
        function openTab(evt, tabName) {
            var i, tabcontent, tabbuttons;
            tabcontent = document.getElementsByClassName("tab-content");
            for (i = 0; i < tabcontent.length; i++) {
                tabcontent[i].style.display = "none";
            }
            tabbuttons = document.getElementsByClassName("tab-button");
            for (i = 0; i < tabbuttons.length; i++) {
                tabbuttons[i].className = tabbuttons[i].className.replace(" active", "");
            }
            document.getElementById(tabName).style.display = "block";
            evt.currentTarget.className += " active";

            // Update URL hash without reloading
            history.pushState(null, '', `?tab=${tabName}`);
        }

        // Activate tab based on URL hash on page load
        document.addEventListener('DOMContentLoaded', function() {
            const urlParams = new URLSearchParams(window.location.search);
            const initialTab = urlParams.get('tab') || 'accounts';
            const defaultButton = document.querySelector(`.tab-button[onclick$="'${initialTab}')"]`);
            if (defaultButton) {
                defaultButton.click();
            } else {
                // Fallback to default tab if URL param is invalid
                document.querySelector('.tab-button').click();
            }
        });
    </script>
</body>
</html>