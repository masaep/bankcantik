-- db.sql untuk Sistem Keuangan Perusahaan Bank Cantik

CREATE DATABASE IF NOT EXISTS `bankcantik_db`;
USE `bankcantik_db`;

-- Tabel pengguna (karyawan)
CREATE TABLE IF NOT EXISTS `users` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `username` VARCHAR(50) NOT NULL UNIQUE,
    `password` VARCHAR(255) NOT NULL,
    `role` ENUM('user', 'admin') DEFAULT 'user', -- 'user' untuk karyawan biasa, 'admin' untuk admin keuangan
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Tabel untuk mencatat transaksi keuangan (pemasukan/pengeluaran)
CREATE TABLE IF NOT EXISTS `financial_transactions` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL, -- ID karyawan yang melakukan/terkait transaksi
    `description` VARCHAR(255) NOT NULL, -- Deskripsi transaksi (e.g., Gaji, Pembayaran Sewa)
    `amount` DECIMAL(10, 2) NOT NULL,
    `type` ENUM('income', 'expense') NOT NULL, -- 'income' (pemasukan) atau 'expense' (pengeluaran)
    `transaction_date` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`)
);

-- Tabel untuk data karyawan (bisa mencakup semua user atau hanya yang relevan)
-- Ini terpisah dari 'users' jika Anda ingin menyimpan lebih banyak detail HR
CREATE TABLE IF NOT EXISTS `employees` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(100) NOT NULL,
    `position` VARCHAR(100) NOT NULL,
    `salary` DECIMAL(10, 2) NOT NULL,
    `hire_date` DATE NOT NULL
);

-- Contoh Data (Untuk pengujian)
INSERT INTO `users` (`username`, `password`, `role`) VALUES
('mona', '$2y$10$wT.fR9R9S9Q9S9Q9S9Q9e.O.R.O.R.O.R.O.R.O.R.O.R.O.R.O.R.O.R.O', 'user'), -- password: password123
('adminbank', '$2y$10$tU.fR9R9S9Q9S9Q9S9Q9e.O.R.O.R.O.R.O.R.O.R.O.R.O.R.O.R.O.R.O', 'admin'); -- password: adminpass
-- Catatan: Ganti hash password di atas dengan yang benar menggunakan password_hash() di PHP
-- Contoh hash untuk 'password123': $2y$10$wT.fR9R9S9Q9S9Q9S9Q9e.O.R.O.R.O.R.O.R.O.R.O.R.O.R.O.R.O.R.O
-- Contoh hash untuk 'adminpass': $2y$10$tU.fR9R9S9Q9S9Q9S9Q9e.O.R.O.R.O.R.O.R.O.R.O.R.O.R.O.R.O.R.O

INSERT INTO `financial_transactions` (`user_id`, `description`, `amount`, `type`) VALUES
((SELECT id FROM users WHERE username='mona'), 'Gaji Pokok Juni', 7500000.00, 'income'),
((SELECT id FROM users WHERE username='mona'), 'Pembayaran Asuransi Karyawan', 300000.00, 'expense'),
((SELECT id FROM users WHERE username='adminbank'), 'Pemasukan Proyek Bank Sentral', 250000000.00, 'income'),
((SELECT id FROM users WHERE username='adminbank'), 'Pembayaran Server AWS', 15000000.00, 'expense');

INSERT INTO `employees` (`name`, `position`, `salary`, `hire_date`) VALUES
('Mona Lisa', 'Staff Keuangan', 7500000.00, '2023-01-15'),
('Budi Santoso', 'Manajer Akuntansi', 12000000.00, '2020-03-01'),
('Siti Aisyah', 'HRD', 8000000.00, '2022-07-20');