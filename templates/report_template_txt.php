<?php
// templates/report_template_txt.php
// Ini akan di-include oleh reports.php

header("Content-Type: text/plain");

if (!isset($report_data) || !is_array($report_data)) {
    echo "Tidak ada data laporan untuk dicetak.\n";
    return;
}

echo "LAPORAN KEUANGAN BANK CANTIK\n";
echo "===========================\n";
echo "Tanggal Cetak: " . date("Y-m-d H:i:s") . "\n\n";

if (empty($report_data)) {
    echo "Tidak ada transaksi dalam laporan ini.\n";
} else {
    $col_user_width = 15;
    $col_desc_width = 35;
    $col_amount_width = 12;
    $col_type_width = 10;
    $col_date_width = 20;

    function truncate_string($str, $length) {
        if (mb_strlen($str) > $length) {
            return mb_substr($str, 0, $length - 3) . '...';
        }
        return $str;
    }

    $header_line = '+-' . str_repeat('-', $col_user_width) . '-+-' . str_repeat('-', $col_desc_width) . '-+-' . str_repeat('-', $col_amount_width) . '-+-' . str_repeat('-', $col_type_width) . '-+-' . str_repeat('-', $col_date_width) . '-+';
    echo $header_line . "\n";
    printf("| %-" . $col_user_width . "s | %-" . $col_desc_width . "s | %-" . $col_amount_width . "s | %-" . $col_type_width . "s | %-" . $col_date_width . "s |\n",
           "Pengguna", "Deskripsi", "Jumlah", "Tipe", "Tanggal");
    echo $header_line . "\n";

    foreach ($report_data as $row) {
        $username = truncate_string($row['username'], $col_user_width);
        $description = truncate_string($row['description'], $col_desc_width);
        $amount = truncate_string(number_format($row['amount'], 2, ',', '.'), $col_amount_width);
        $type = truncate_string(($row['type'] == 'income' ? 'Pemasukan' : 'Pengeluaran'), $col_type_width);
        $date = truncate_string($row['transaction_date'], $col_date_width);

        printf("| %-" . $col_user_width . "s | %-" . $col_desc_width . "s | %-" . $col_amount_width . "s | %-" . $col_type_width . "s | %-" . $col_date_width . "s |\n",
               $username, $description, $amount, $type, $date);
    }
    echo $header_line . "\n";
}
?>