<?php
// templates/report_template_html.php
// Ini akan di-include oleh reports.php

if (!isset($report_data) || !is_array($report_data)) {
    echo "<p>Tidak ada data laporan untuk dicetak.</p>";
    return;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laporan Keuangan - Cetak HTML</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        h1 { color: #333; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background-color: #f2f2f2; }
    </style>
</head>
<body>
    <h1>Laporan Keuangan Bank Cantik</h1>
    <p>Tanggal Cetak: <?php echo date("Y-m-d H:i:s"); ?></p>

    <?php if (empty($report_data)): ?>
        <p>Tidak ada transaksi dalam laporan ini.</p>
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
                        <td><?php echo htmlspecialchars($row['transaction_date']); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>

</body>
</html>