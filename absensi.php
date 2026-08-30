<?php

session_start();
include 'config.php';

// Proses absensi jika ada input RFID
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['nomor_kartu'])) {
    $nomor_kartu = trim($_POST['nomor_kartu']);
    $nomor_kartu = preg_replace('/\s+/', '', $nomor_kartu);
    error_log("Nomor Kartu: '$nomor_kartu'");

    // Validasi nomor kartu
    if (empty($nomor_kartu)) {
        $error = "Nomor kartu tidak boleh kosong!";
    } else {
        // Cek apakah kartu terdaftar dengan prepared statement
        $stmt = $conn->prepare("SELECT id, nama_lengkap, jabatan, foto_profile FROM kartu_rfid WHERE nomor_kartu = ?");
        $stmt->bind_param("s", $nomor_kartu);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows == 0) {
            $error = "Kartu tidak terdaftar!";
        } else {
            $kartu = $result->fetch_assoc();
            $id_kartu = $kartu['id'];

            // Cek absensi hari ini
            $today = date('Y-m-d');
            $stmt = $conn->prepare("SELECT id FROM absensi WHERE id_kartu = ? AND DATE(waktu_masuk) = ? AND waktu_keluar IS NULL");
            $stmt->bind_param("is", $id_kartu, $today);
            $stmt->execute();
            $absensi_result = $stmt->get_result();

            // Mulai transaksi untuk atomic operation
            $conn->begin_transaction();

            try {
                if ($absensi_result->num_rows > 0) {
                    // Update absensi keluar
                    $absensi = $absensi_result->fetch_assoc();
                    $update = $conn->prepare("UPDATE absensi SET waktu_keluar = NOW() WHERE id = ?");
                    $update->bind_param("i", $absensi['id']);
                    $update->execute();
                    $success = "Absensi keluar berhasil dicatat untuk ".$kartu['nama_lengkap'];
                } else {
                    // Insert absensi masuk
                    $insert = $conn->prepare("INSERT INTO absensi (id_kartu) VALUES (?)");
                    $insert->bind_param("i", $id_kartu);
                    $insert->execute();
                    $success = "Absensi masuk berhasil dicatat untuk ".$kartu['nama_lengkap'];
                }

                $conn->commit();
            } catch (Exception $e) {
                $conn->rollback();
                $error = "Terjadi kesalahan sistem: ".$e->getMessage();
            }
        }
    }

    // Setelah proses, unset POST untuk prevent resubmission
    unset($_POST['nomor_kartu']);
}
?>


<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Absensi RFID</title>
    <style>
        :root {
            --primary: #4361ee;
            --primary-dark: #3a56d4;
            --light: #f8f9fa;
            --dark: #212529;
            --white: #ffffff;
            --danger: #dc3545;
            --success: #28a745;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        body {
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            padding: 20px;
        }

        .absensi-container {
            background: var(--white);
            width: 100%;
            max-width: 500px;
            padding: 2.5rem;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            text-align: center;
        }

        .absensi-header {
            margin-bottom: 2rem;
        }

        .absensi-header h2 {
            color: var(--primary);
            margin-bottom: 0.5rem;
        }

        .scan-area {
            border: 2px dashed var(--primary);
            padding: 2rem;
            margin-bottom: 1.5rem;
            border-radius: 10px;
            background-color: rgba(67, 97, 238, 0.05);
            cursor: pointer;
            transition: all 0.3s;
        }

        .scan-area:hover {
            background-color: rgba(67, 97, 238, 0.1);
            transform: translateY(-3px);
        }

        .scan-icon {
            font-size: 3rem;
            color: var(--primary);
            margin-bottom: 1rem;
        }

        .error {
            color: var(--danger);
            margin-bottom: 1rem;
            padding: 0.75rem;
            background-color: rgba(220, 53, 69, 0.1);
            border-radius: 5px;
        }

        .success {
            color: var(--success);
            margin-bottom: 1rem;
            padding: 0.75rem;
            background-color: rgba(40, 167, 69, 0.1);
            border-radius: 5px;
        }

        .user-info {
            margin-top: 1.5rem;
            padding: 1rem;
            background-color: rgba(248, 249, 250, 0.8);
            border-radius: 8px;
            display:
                <?php echo isset($kartu) ? 'block' : 'none'; ?>
            ;
        }

        .user-photo {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            object-fit: cover;
            margin-bottom: 1rem;
            border: 3px solid var(--primary);
        }

        .login-link {
            margin-top: 1.5rem;
            color: #6c757d;
        }

        .login-link a {
            color: var(--primary);
            text-decoration: none;
            font-weight: 500;
        }

        .login-link a:hover {
            text-decoration: underline;
        }

        /* ... (tambahkan style ini) ... */
        .rfid-status {
            padding: 15px;
            margin: 15px 0;
            border-radius: 8px;
            text-align: center;
            font-weight: bold;
        }

        .rfid-status.error {
            background-color: rgba(220, 53, 69, 0.1);
            color: var(--danger);
            border: 1px solid var(--danger);
        }

        .rfid-status.success {
            background-color: rgba(40, 167, 69, 0.1);
            color: var(--success);
            border: 1px solid var(--success);
        }
    </style>
</head>

<body>
    <div class="absensi-container">
        <div class="absensi-header">
            <h2>Absensi RFID</h2>
            <p>Tempelkan kartu Anda pada reader untuk melakukan absensi</p>
        </div>

        <?php if (isset($error)): ?>
        <div class="error"><?php echo $error; ?></div>
        <?php endif; ?>

        <?php if (isset($success)): ?>
        <div class="success"><?php echo $success; ?></div>
        <?php endif; ?>

        <div class="scan-area" onclick="document.getElementById('rfidForm').submit();">
            <div class="scan-icon">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="var(--primary)" width="48"
                    height="48">
                    <path d="M4 5h16v2H4zm0 4h16v2H4zm0 4h16v2H4zm0 4h16v2H4z" />
                </svg>
            </div>
            <h3>Klik untuk memindai kartu</h3>
            <p>Atau tempelkan kartu RFID Anda ke reader</p>
        </div>

        <?php if (isset($kartu)): ?>
        <div class="user-info">
            <?php if ($kartu['foto_profile']): ?>
            <img src="<?php echo $kartu['foto_profile']; ?>"
                alt="User Photo" class="user-photo">
            <?php else: ?>
            <div class="user-photo"
                style="background-color: #eee; display: flex; align-items: center; justify-content: center;">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="#999" width="40" height="40">
                    <path
                        d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm0 3c1.66 0 3 1.34 3 3s-1.34 3-3 3-3-1.34-3-3 1.34-3 3-3zm0 14.2c-2.5 0-4.71-1.28-6-3.22.03-1.99 4-3.08 6-3.08 1.99 0 5.97 1.09 6 3.08-1.29 1.94-3.5 3.22-6 3.22z" />
                </svg>
            </div>
            <?php endif; ?>
            <h3><?php echo htmlspecialchars($kartu['nama_lengkap']); ?>
            </h3>
            <p><?php echo htmlspecialchars($kartu['jabatan']); ?>
            </p>
            <p><?php date_default_timezone_set('Asia/Jakarta');
echo date('d F Y H:i:s');
?></p>
        </div>
        <?php endif; ?>

        <form id="rfidForm" method="POST" action="absensi.php" autocomplete="off">
            <input type="text" name="nomor_kartu" id="nomor_kartu"
            oninput="checkRFID()" style="width:1px;height:1px;border:0;position:absolute;left:-9999px;">
        </form>

        <div id="rfidStatusDisplay"></div>

        <div class="login-link">
            <p>Admin? <a href="login.php">Login disini</a></p>
        </div>
    </div>

    <script>
        // Simulasi input RFID (pada implementasi nyata, ini akan diganti dengan input dari reader)
        document.addEventListener('keypress', function(e) {
            if (e.target === document.body) {
                document.getElementById('nomor_kartu').value += e.key;

                // Jika panjang input sudah cukup (misal 10 karakter), submit form
                if (document.getElementById('nomor_kartu').value.length >= 10) {
                    document.getElementById('rfidForm').submit();
                }
            }
        });

        // Variabel untuk tracking status
        let isProcessing = false;

        // Fungsi untuk menampilkan status
        function showStatus(message, isSuccess) {
            const statusDiv = document.getElementById('rfidStatusDisplay');
            statusDiv.className = 'rfid-status ' + (isSuccess ? 'success' : 'error');
            statusDiv.textContent = message;

            // Auto hide setelah 3 detik
            setTimeout(() => {
                statusDiv.textContent = '';
                statusDiv.className = 'rfid-status';
            }, 3000);
        }

        function checkRFID() {
        const input = document.getElementById("nomor_kartu");
        // Kartu RFID biasanya panjangnya 10 karakter
        if (input.value.length >= 10) {
            document.getElementById("rfidForm").submit();
            }
        }

        setInterval(() => {
        const input = document.getElementById("nomor_kartu");
        if (document.activeElement !== input) {
            input.focus();
            }
        }, 1000);

        // Event listener untuk RFID input
        document.addEventListener('keypress', function(e) {
            if (e.target === document.body && !isProcessing) {
                const input = document.getElementById('nomor_kartu');

                // Hanya terima digit
                if (/^\d$/.test(e.key)) {
                    input.value += e.key;

                    // Jika panjang input sudah cukup (misal 10 digit)
                    if (input.value.length >= 10) {
                        isProcessing = true;

                        // Submit form
                        document.getElementById('rfidForm').submit();

                        // Tampilkan status loading
                        showStatus("Memproses kartu...", true);

                        // Reset setelah 1 detik
                        setTimeout(() => {
                            input.value = '';
                            isProcessing = false;
                        }, 1000);
                    }
                }
            }
        });
    </script>
</body>

</html>