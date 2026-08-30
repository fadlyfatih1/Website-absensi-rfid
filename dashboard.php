<?php
session_start();
include 'config.php';

// Jika belum login atau bukan admin, redirect ke halaman login
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: login.php");
    exit();
}

// Proses CRUD
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Tambah kartu baru
    if (isset($_POST['tambah_kartu'])) {
        $nomor_kartu = $_POST['nomor_kartu'];
        $nama_lengkap = $_POST['nama_lengkap'];
        $nim = $_POST['nim'];
        $jabatan = $_POST['jabatan'];

        // Upload foto
        $foto_profile = null;
        if (isset($_FILES['foto_profile']) && $_FILES['foto_profile']['error'] == UPLOAD_ERR_OK) {
            $target_dir = "uploads/";
            if (!file_exists($target_dir)) {
                mkdir($target_dir, 0777, true);
            }
            $file_ext = pathinfo($_FILES['foto_profile']['name'], PATHINFO_EXTENSION);
            $foto_profile = $target_dir . uniqid() . '.' . $file_ext;
            move_uploaded_file($_FILES['foto_profile']['tmp_name'], $foto_profile);
        }

        $stmt = $conn->prepare("INSERT INTO kartu_rfid (nomor_kartu, nama_lengkap, nim, jabatan, foto_profile) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("sssss", $nomor_kartu, $nama_lengkap, $nim, $jabatan, $foto_profile);

        if ($stmt->execute()) {
            $success = "Kartu berhasil ditambahkan!";
        } else {
            $error = "Gagal menambahkan kartu: " . $conn->error;
        }
    }

    // Hapus kartu
    if (isset($_POST['hapus_kartu'])) {
        $id = $_POST['id'];

        // Hapus foto jika ada
        $stmt = $conn->prepare("SELECT foto_profile FROM kartu_rfid WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();
        $kartu = $result->fetch_assoc();

        if ($kartu['foto_profile'] && file_exists($kartu['foto_profile'])) {
            unlink($kartu['foto_profile']);
        }

        // Hapus dari database
        $stmt = $conn->prepare("DELETE FROM kartu_rfid WHERE id = ?");
        $stmt->bind_param("i", $id);

        if ($stmt->execute()) {
            $success = "Kartu berhasil dihapus!";
        } else {
            $error = "Gagal menghapus kartu: " . $conn->error;
        }
    }

    // Clear history absensi
    if (isset($_POST['clear_history'])) {
        $range = $_POST['range'];
        $today = date('Y-m-d');

        if ($range == 'hari_ini') {
            $sql = "DELETE FROM absensi WHERE DATE(waktu_masuk) = CURDATE()";
            $message = "History absensi hari ini berhasil dihapus";
        } elseif ($range == 'semua') {
            $sql = "TRUNCATE TABLE absensi";
            $message = "Seluruh history absensi berhasil dihapus";
        } elseif ($range == 'custom' && !empty($_POST['custom_date'])) {
            $custom_date = $_POST['custom_date'];
            $sql = "DELETE FROM absensi WHERE DATE(waktu_masuk) = '$custom_date'";
            $message = "History absensi tanggal $custom_date berhasil dihapus";
        }

        if (isset($sql)) {
            if ($conn->query($sql)) {
                $success = $message;
            } else {
                $error = "Gagal menghapus history: " . $conn->error;
            }
        }
    }
}

// Ambil data kartu
$kartu = $conn->query("SELECT * FROM kartu_rfid ORDER BY nama_lengkap");

// Ambil data absensi
$absensi = $conn->query("
    SELECT a.*, k.nama_lengkap, k.jabatan, k.foto_profile, k.nomor_kartu 
    FROM absensi a 
    JOIN kartu_rfid k ON a.id_kartu = k.id 
    ORDER BY a.waktu_masuk DESC
    LIMIT 10
");

// Hitung statistik kehadiran
$statistik = $conn->query("
    SELECT 
        (SELECT COUNT(*) FROM kartu_rfid) AS total_kartu,
        (SELECT COUNT(DISTINCT id_kartu) FROM absensi WHERE DATE(waktu_masuk) = CURDATE()) AS sudah_absen,
        (SELECT COUNT(*) FROM absensi WHERE DATE(waktu_masuk) = CURDATE() AND waktu_keluar IS NULL) AS belum_keluar
")->fetch_assoc();

// Hitung persentase kehadiran
$persentase_hadir = $statistik['total_kartu'] > 0 ? round(($statistik['sudah_absen'] / $statistik['total_kartu']) * 100) : 0;
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Admin - Sistem Absensi RFID</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        :root {
            --primary: #4361ee;
            --primary-light: #e6e9ff;
            --primary-dark: #3a56d4;
            --secondary: #3f37c9;
            --success: #28a745;
            --warning: #ffc107;
            --danger: #dc3545;
            --light: #f8f9fa;
            --dark: #212529;
            --gray: #6c757d;
            --white: #ffffff;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        body {
            background-color: #f5f7fb;
            color: var(--dark);
        }

        .sidebar {
            width: 250px;
            background: var(--white);
            box-shadow: 0 0 20px rgba(0, 0, 0, 0.1);
            position: fixed;
            height: 100vh;
            padding: 20px 0;
            transition: all 0.3s;
        }

        .sidebar-header {
            padding: 0 20px 20px;
            border-bottom: 1px solid rgba(0, 0, 0, 0.1);
        }

        .sidebar-header h3 {
            color: var(--primary);
            font-size: 1.3rem;
        }

        .sidebar-menu {
            padding: 20px 0;
        }

        .sidebar-menu a {
            display: flex;
            align-items: center;
            padding: 12px 20px;
            color: var(--gray);
            text-decoration: none;
            transition: all 0.3s;
        }

        .sidebar-menu a:hover,
        .sidebar-menu a.active {
            color: var(--primary);
            background-color: var(--primary-light);
        }

        .sidebar-menu a i {
            margin-right: 10px;
            width: 20px;
            text-align: center;
        }

        .main-content {
            margin-left: 250px;
            padding: 20px;
            transition: all 0.3s;
        }

        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px 20px;
            background: var(--white);
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            border-radius: 8px;
            margin-bottom: 20px;
        }

        .user-info {
            display: flex;
            align-items: center;
        }

        .user-info img {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            margin-right: 10px;
        }

        .logout-btn {
            color: var(--danger);
            text-decoration: none;
            font-weight: 500;
        }

        .logout-btn:hover {
            text-decoration: underline;
        }

        .cards {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
            margin-bottom: 30px;
        }

        .card {
            background: var(--white);
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
        }

        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }

        .card-title {
            font-size: 1rem;
            color: var(--gray);
        }

        .card-value {
            font-size: 1.8rem;
            font-weight: 600;
            color: var(--dark);
        }

        .card-icon {
            width: 50px;
            height: 50px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
        }

        .card-icon.primary {
            background-color: rgba(67, 97, 238, 0.1);
            color: var(--primary);
        }

        .card-icon.success {
            background-color: rgba(40, 167, 69, 0.1);
            color: var(--success);
        }

        .card-icon.warning {
            background-color: rgba(255, 193, 7, 0.1);
            color: var(--warning);
        }

        .progress-container {
            margin-top: 15px;
        }

        .progress-text {
            display: flex;
            justify-content: space-between;
            margin-bottom: 5px;
            font-size: 0.85rem;
        }

        .progress-bar {
            height: 8px;
            background-color: #e9ecef;
            border-radius: 4px;
            overflow: hidden;
        }

        .progress-fill {
            height: 100%;
            background-color: var(--success);
            border-radius: 4px;
            width:
                <?php echo $persentase_hadir; ?>
                %;
            transition: width 0.5s ease;
        }

        .tabs {
            display: flex;
            border-bottom: 1px solid #dee2e6;
            margin-bottom: 20px;
        }

        .tab {
            padding: 10px 20px;
            cursor: pointer;
            border-bottom: 2px solid transparent;
            font-weight: 500;
            color: var(--gray);
            transition: all 0.3s;
        }

        .tab.active {
            color: var(--primary);
            border-bottom-color: var(--primary);
        }

        .tab-content {
            display: none;
        }

        .tab-content.active {
            display: block;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            background: var(--white);
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
        }

        th,
        td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #dee2e6;
        }

        th {
            background-color: #f8f9fa;
            font-weight: 600;
            color: var(--dark);
        }

        tr:hover {
            background-color: #f8f9fa;
        }

        .status {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 500;
        }

        .status.masuk {
            background-color: rgba(40, 167, 69, 0.1);
            color: var(--success);
        }

        .status.keluar {
            background-color: rgba(13, 110, 253, 0.1);
            color: #0d6efd;
        }

        .status.belum {
            background-color: rgba(255, 193, 7, 0.1);
            color: var(--warning);
        }

        .profile-img {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            object-fit: cover;
        }

        .no-photo {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background-color: #e9ecef;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--gray);
        }

        .btn {
            padding: 6px 12px;
            border-radius: 4px;
            border: none;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s;
        }

        .btn-danger {
            background-color: rgba(220, 53, 69, 0.1);
            color: var(--danger);
        }

        .btn-danger:hover {
            background-color: rgba(220, 53, 69, 0.2);
        }

        .form-group {
            margin-bottom: 15px;
        }

        label {
            display: block;
            margin-bottom: 5px;
            font-weight: 500;
            color: var(--dark);
        }

        .form-control {
            width: 100%;
            padding: 10px 15px;
            border: 1px solid #ced4da;
            border-radius: 4px;
            font-size: 1rem;
            transition: border-color 0.3s;
        }

        .form-control:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(67, 97, 238, 0.2);
        }

        .scan-area {
            border: 2px dashed var(--primary);
            padding: 20px;
            text-align: center;
            border-radius: 8px;
            background-color: rgba(67, 97, 238, 0.05);
            cursor: pointer;
            margin-bottom: 15px;
        }

        .scan-area:hover {
            background-color: rgba(67, 97, 238, 0.1);
        }

        .scan-icon {
            font-size: 2rem;
            color: var(--primary);
            margin-bottom: 10px;
        }

        .error {
            color: var(--danger);
            background-color: rgba(220, 53, 69, 0.1);
            padding: 10px 15px;
            border-radius: 4px;
            margin-bottom: 20px;
        }

        .success {
            color: var(--success);
            background-color: rgba(40, 167, 69, 0.1);
            padding: 10px 15px;
            border-radius: 4px;
            margin-bottom: 20px;
        }

        .clear-history-btn {
            background-color: rgba(220, 53, 69, 0.1);
            color: var(--danger);
            border: none;
            padding: 8px 15px;
            border-radius: 4px;
            cursor: pointer;
            font-weight: 500;
            transition: all 0.3s;
            margin-bottom: 15px;
        }

        .clear-history-btn:hover {
            background-color: rgba(220, 53, 69, 0.2);
        }

        .clear-history-form {
            background: var(--white);
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            margin-bottom: 20px;
            display: none;
        }

        .clear-history-form.active {
            display: block;
        }

        .radio-group {
            display: flex;
            gap: 15px;
            margin-bottom: 15px;
        }

        .radio-option {
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .date-input {
            margin-top: 10px;
            display: none;
        }

        .form-actions {
            display: flex;
            gap: 10px;
            margin-top: 15px;
        }

        .btn-secondary {
            background-color: var(--light);
            color: var(--dark);
        }

        @media (max-width: 992px) {
            .sidebar {
                width: 80px;
                overflow: hidden;
            }

            .sidebar-header h3,
            .sidebar-menu a span {
                display: none;
            }

            .sidebar-menu a {
                justify-content: center;
            }

            .sidebar-menu a i {
                margin-right: 0;
                font-size: 1.2rem;
            }

            .main-content {
                margin-left: 80px;
            }

            .cards {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>

<body>
    <div class="sidebar">
        <div class="sidebar-header">
            <h3>Absensi RFID</h3>
        </div>
        <div class="sidebar-menu">
            <a href="dashboard.php" class="active"><i class="fas fa-tachometer-alt"></i> <span>Dashboard</span></a>
            <a href="absensi.php"><i class="fas fa-id-card"></i> <span>Absensi</span></a>
            <a href="logout.php"><i class="fas fa-sign-out-alt"></i> <span>Logout</span></a>
        </div>
    </div>

    <div class="main-content">
        <div class="header">
            <h2>Dashboard Admin</h2>
            <div class="user-info">
                <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($_SESSION['username']); ?>&background=random"
                    alt="User">
                <span><?php echo htmlspecialchars($_SESSION['username']); ?></span>
            </div>
        </div>

        <?php if (isset($error)): ?>
        <div class="error"><?php echo $error; ?></div>
        <?php endif; ?>

        <?php if (isset($success)): ?>
        <div class="success"><?php echo $success; ?></div>
        <?php endif; ?>

        <div class="cards">
            <div class="card">
                <div class="card-header">
                    <div>
                        <div class="card-title">Total Kartu Terdaftar</div>
                        <div class="card-value">
                            <?php echo $statistik['total_kartu']; ?>
                        </div>
                    </div>
                    <div class="card-icon primary">
                        <i class="fas fa-id-card"></i>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <div>
                        <div class="card-title">Sudah Absen Hari Ini</div>
                        <div class="card-value">
                            <?php echo $statistik['sudah_absen']; ?>
                        </div>
                    </div>
                    <div class="card-icon success">
                        <i class="fas fa-user-check"></i>
                    </div>
                </div>
                <div class="progress-container">
                    <div class="progress-text">
                        <span>Persentase Kehadiran</span>
                        <span><?php echo $persentase_hadir; ?>%</span>
                    </div>
                    <div class="progress-bar">
                        <div class="progress-fill"></div>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <div>
                        <div class="card-title">Belum Keluar</div>
                        <div class="card-value">
                            <?php echo $statistik['belum_keluar']; ?>
                        </div>
                    </div>
                    <div class="card-icon warning">
                        <i class="fas fa-user-clock"></i>
                    </div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="tabs">
                <div class="tab active" onclick="switchTab('absensi')">Data Absensi</div>
                <div class="tab" onclick="switchTab('kartu')">Daftar Kartu</div>
                <div class="tab" onclick="switchTab('tambah')">Tambah Kartu</div>
            </div>

            <div id="absensi" class="tab-content active">
                <!-- Tombol Clear History -->
                <button class="clear-history-btn" onclick="toggleClearHistoryForm()">
                    <i class="fas fa-trash-alt"></i> Clear History
                </button>
                <!-- Form Clear History (awalnya tersembunyi) -->
                <div id="clearHistoryForm" class="clear-history-form">
                    <h4>Pilih Range History yang Akan Dihapus:</h4>
                    <form method="POST">
                        <div class="radio-group">
                            <label class="radio-option">
                                <input type="radio" name="range" value="hari_ini" checked onchange="toggleDateInput()">
                                Hari Ini
                            </label>
                            <label class="radio-option">
                                <input type="radio" name="range" value="semua" onchange="toggleDateInput()">
                                Semua Data
                            </label>
                            <label class="radio-option">
                                <input type="radio" name="range" value="custom" onchange="toggleDateInput()">
                                Tanggal Tertentu
                            </label>
                        </div>

                        <div id="customDateInput" class="date-input">
                            <label for="custom_date">Pilih Tanggal:</label>
                            <input type="date" id="custom_date" name="custom_date" class="form-control">
                        </div>

                        <div class="form-actions">
                            <button type="submit" name="clear_history" class="btn btn-danger">
                                <i class="fas fa-trash"></i> Hapus History
                            </button>
                            <button type="button" class="btn btn-secondary" onclick="toggleClearHistoryForm()">
                                <i class="fas fa-times"></i> Batal
                            </button>
                        </div>
                    </form>
                </div>

                <table>
                    <thead>
                        <tr>
                            <th>Foto</th>
                            <th>Nama</th>
                            <th>Jabatan</th>
                            <th>Nomor Kartu</th>
                            <th>Waktu Masuk</th>
                            <th>Waktu Keluar</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($row = $absensi->fetch_assoc()):
                            $status = $row['waktu_keluar'] ? 'keluar' : 'masuk';
                            $status_text = $row['waktu_keluar'] ? 'Sudah Keluar' : 'Masih di Lokasi';
                            ?>
                        <tr>
                            <td>
                                <?php if ($row['foto_profile']): ?>
                                <img src="<?php echo $row['foto_profile']; ?>"
                                    alt="Profile" class="profile-img">
                                <?php else: ?>
                                <div class="no-photo">
                                    <i class="fas fa-user"></i>
                                </div>
                                <?php endif; ?>
                            </td>
                            <td><?php echo htmlspecialchars($row['nama_lengkap']); ?>
                            </td>
                            <td><?php echo htmlspecialchars($row['jabatan']); ?>
                            </td>
                            <td><?php echo htmlspecialchars($row['nomor_kartu']); ?>
                            </td>
                            <td><?php echo date('d M Y H:i', strtotime($row['waktu_masuk'])); ?>
                            </td>
                            <td>
                                <?php if ($row['waktu_keluar']): ?>
                                <?php echo date('d M Y H:i', strtotime($row['waktu_keluar'])); ?>
                                <?php else: ?>
                                -
                                <?php endif; ?>
                            </td>
                            <td>
                                <span
                                    class="status <?php echo $status; ?>"><?php echo $status_text; ?></span>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>

            <div id="kartu" class="tab-content">
                <table>
                    <thead>
                        <tr>
                            <th>Foto</th>
                            <th>Nomor Kartu</th>
                            <th>Nama Lengkap</th>
                            <th>NIM</th>
                            <th>Jabatan</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($row = $kartu->fetch_assoc()): ?>
                        <tr>
                            <td>
                                <?php if ($row['foto_profile']): ?>
                                <img src="<?php echo $row['foto_profile']; ?>"
                                    alt="Profile" class="profile-img">
                                <?php else: ?>
                                <div class="no-photo">
                                    <i class="fas fa-user"></i>
                                </div>
                                <?php endif; ?>
                            </td>
                            <td><?php echo htmlspecialchars($row['nomor_kartu']); ?>
                            </td>
                            <td><?php echo htmlspecialchars($row['nama_lengkap']); ?>
                            </td>
                            <td><?php echo htmlspecialchars($row['nim']); ?>
                            </td>
                            <td><?php echo htmlspecialchars($row['jabatan']); ?>
                            </td>
                            <td>
                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="id"
                                        value="<?php echo $row['id']; ?>">
                                    <button type="submit" name="hapus_kartu" class="btn btn-danger">
                                        <i class="fas fa-trash"></i> Hapus
                                    </button>
                                </form>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>

            <div id="tambah" class="tab-content">
                <form method="POST" enctype="multipart/form-data">
                    <div class="scan-area" onclick="startRFIDScan()">
                        <div class="scan-icon">
                            <i class="fas fa-rss"></i>
                        </div>
                        <h3>Scan Kartu RFID</h3>
                        <p>Tempelkan kartu RFID baru ke reader</p>
                    </div>
                    <input type="hidden" id="nomor_kartu" name="nomor_kartu" required>

                    <div class="form-group">
                        <label for="nama_lengkap">Nama Lengkap</label>
                        <input type="text" id="nama_lengkap" name="nama_lengkap" class="form-control" required>
                    </div>

                    <div class="form-group">
                        <label for="nim">NIM</label>
                        <input type="text" id="nim" name="nim" class="form-control" required>
                    </div>

                    <div class="form-group">
                        <label for="jabatan">Jabatan</label>
                        <input type="text" id="jabatan" name="jabatan" class="form-control" required>
                    </div>

                    <div class="form-group">
                        <label for="foto_profile">Foto Profile</label>
                        <input type="file" id="foto_profile" name="foto_profile" class="form-control" accept="image/*">
                    </div>

                    <button type="submit" name="tambah_kartu" class="btn"
                        style="background-color: var(--primary); color: white; padding: 10px 15px;">
                        <i class="fas fa-save"></i> Simpan Kartu
                    </button>
                </form>
            </div>
        </div>
    </div>

    <script>
        function switchTab(tabId) {
            // Sembunyikan semua tab content
            document.querySelectorAll('.tab-content').forEach(content => {
                content.classList.remove('active');
            });

            // Tampilkan tab content yang dipilih
            document.getElementById(tabId).classList.add('active');

            // Update tab navigasi
            document.querySelectorAll('.tab').forEach(tab => {
                tab.classList.remove('active');
            });

            // Aktifkan tab yang dipilih
            document.querySelector(`.tab[onclick="switchTab('${tabId}')"]`).classList.add('active');
        }

        // Fungsi untuk menangkap input RFID
        function startRFIDScan() {
            alert("Mode scan aktif. Tempelkan kartu RFID baru ke reader.");

            // Simulasi input RFID (pada implementasi nyata, ini akan diganti dengan input dari reader)
            document.addEventListener('keypress', function rfidListener(e) {
                if (e.target === document.body) {
                    document.getElementById('nomor_kartu').value += e.key;

                    // Jika panjang input sudah cukup (misal 10 karakter), tampilkan nomor kartu
                    if (document.getElementById('nomor_kartu').value.length >= 10) {
                        alert("Kartu berhasil discan! Nomor: " + document.getElementById('nomor_kartu').value);
                        document.removeEventListener('keypress', rfidListener);
                    }
                }
            });
        }

        // Untuk menampilkan nomor kartu yang sudah terdaftar (jika ada)
        window.onload = function() {
            const urlParams = new URLSearchParams(window.location.search);
            const scannedCard = urlParams.get('scanned');
            if (scannedCard) {
                document.getElementById('nomor_kartu').value = scannedCard;
            }
        };

        // Fungsi untuk menampilkan/sembunyikan form clear history
        function toggleClearHistoryForm() {
            const form = document.getElementById('clearHistoryForm');
            form.classList.toggle('active');
        }

        // Fungsi untuk menampilkan input tanggal custom
        function toggleDateInput() {
            const customRadio = document.querySelector('input[name="range"][value="custom"]');
            const dateInput = document.getElementById('customDateInput');

            if (customRadio.checked) {
                dateInput.style.display = 'block';
            } else {
                dateInput.style.display = 'none';
            }
        }

        // Set today's date as default for custom date input
        document.addEventListener('DOMContentLoaded', function() {
            document.getElementById('custom_date').valueAsDate = new Date();
        });
    </script>
</body>

</html>