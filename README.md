# Website Absensi RFID

Sistem absensi berbasis RFID yang mencatat kehadiran pengguna dengan tap kartu melalui RFID scanner dan menampilkan informasi absensi melalui dashboard berbasis web.

## 📌 Tentang Project

Project ini dibuat untuk mengimplementasikan sistem absensi menggunakan kartu RFID yang terhubung dengan aplikasi berbasis web.

Data hasil scanning RFID diproses dan disimpan ke database, kemudian ditampilkan melalui dashboard untuk memudahkan pemantauan data kehadiran.

## ✨ Fitur

- Login pengguna
- Role Admin dan User
- Pencatatan absensi menggunakan RFID
- Dashboard absensi
- Data pengguna
- Riwayat absensi (waktu masuk dan waktu keluar)
- Penyimpanan data menggunakan MySQL

## 🛠️ Teknologi

- PHP
- MySQL
- HTML
- CSS
- RFID scanner + RFID tag/card
- XAMPP

## ⚙️ Instalasi

### 1. Clone repository

```bash
git clone https://github.com/fadlyfatih1/Website-absensi-rfid.git
```

### 2. Masukkan project ke XAMPP

Letakkan folder project di:

C:\xampp\htdocs\

### 3. Buat database

Buat database MySQL melalui phpMyAdmin dan import file database yang tersedia di project "database.sql".

### 4. Konfigurasi database

Sesuaikan konfigurasi database pada file:
config.php

### 5. Jalankan project

Aktifkan Apache dan MySQL melalui XAMPP, kemudian buka:
http://localhost/website-absensi-rfid/

### 👤 Default Login

Admin

Username: admin
Password: admin

### 📷 Preview

<img width="1920" height="829" alt="Screenshot 2026-08-30 133248" src="https://github.com/user-attachments/assets/e87f410f-9828-42be-bb1f-ff40ad5a711e" />
