<?php
// 1. Jalankan session dengan aman
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 2. PERBAIKAN: Jika session kosong di Vercel, coba pulihkan dari Cookie cadangan
if (!isset($_SESSION['user_id']) && isset($_COOKIE['logged_in_user'])) {
    $_SESSION['user_id'] = $_COOKIE['logged_in_user'];
    $_SESSION['username'] = $_COOKIE['username_user']; // jika dibutuhkan di halaman ini
}

require_once 'config.php';

// 3. Cek kembali, jika di session TETAP kosong (artinya di cookie juga tidak ada)
// Baru kita lempar user ke halaman login.php
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

// Get user data
$user_id = $_SESSION['user_id'];
$stmt = $pdo->prepare("SELECT username FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

// Jika karena suatu hal user tidak ditemukan di database (id palsu dari cookie)
if (!$user) {
    // Hapus cookie dan paksa login ulang
    setcookie('logged_in_user', '', time() - 3600, '/');
    header("Location: login.php");
    exit;
}

// Get attendance data for current user
$stmt = $pdo->prepare("SELECT * FROM attendance WHERE user_id = ? ORDER BY created_at ASC");
$stmt->execute([$user_id]);
$attendance_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate statistics for current month
$current_month = date('m');
$current_year = date('Y');
$stmt = $pdo->prepare("SELECT COUNT(*) as total_days, SUM(total_hours) as total_hours FROM attendance WHERE user_id = ? AND MONTH(date) = ? AND YEAR(date) = ?");
$stmt->execute([$user_id, $current_month, $current_year]);
$stats = $stmt->fetch(PDO::FETCH_ASSOC);
$total_days = $stats['total_days'] ?? 0;
$total_hours = $stats['total_hours'] ?? 0;
$avg_hours = $total_days > 0 ? $total_hours / $total_days : 0;

function formatHours($hours) {
    $hours_int = floor($hours);
    $minutes = round(($hours - $hours_int) * 60);
    return $hours_int . ' jam ' . $minutes . ' menit';
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sistem Absensi Karyawan</title>
    <link href="https://fonts.googleapis.com/css2?family=Unbounded:wght@400;600;700&family=Space+Mono:wght@400;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #0a0e27;
            --secondary: #1a1f3a;
            --accent: #00ff88;
            --accent-dark: #00cc6a;
            --text: #e8eaed;
            --text-dim: #9aa0a6;
            --surface: #252a41;
            --border: #3a4158;
            --error: #ff4757;
            --success: #00ff88;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Space Mono', monospace;
            background: linear-gradient(135deg, var(--primary) 0%, #0f1428 50%, var(--secondary) 100%);
            color: var(--text);
            min-height: 100vh;
            padding: 2rem;
            position: relative;
            overflow-x: hidden;
        }

        body::before {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background:
                repeating-linear-gradient(0deg, transparent, transparent 2px, rgba(0, 255, 136, 0.03) 2px, rgba(0, 255, 136, 0.03) 4px);
            pointer-events: none;
            z-index: 1;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            position: relative;
            z-index: 2;
        }

        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 3rem;
            animation: slideDown 0.6s ease-out;
        }

        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .header h1 {
            font-family: 'Unbounded', sans-serif;
            font-size: 3rem;
            font-weight: 700;
            background: linear-gradient(135deg, var(--accent) 0%, #00ffaa 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: 0.5rem;
            letter-spacing: -2px;
            text-transform: uppercase;
        }

        .header p {
            color: var(--text-dim);
            font-size: 0.9rem;
            letter-spacing: 2px;
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .user-info span {
            color: var(--accent);
        }

        .income-btn {
            padding: 0.5rem 1rem;
            background: var(--accent);
            color: var(--primary);
            border: 2px solid var(--accent);
            border-radius: 8px;
            cursor: pointer;
            font-family: 'Space Mono', monospace;
            font-size: 0.8rem;
            font-weight: 700;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
        }

        .income-btn:hover {
            background: transparent;
            color: var(--accent);
        }

        .logout-btn {
            padding: 0.5rem 1rem;
            background: var(--secondary);
            color: var(--text-dim);
            border: 2px solid var(--border);
            border-radius: 8px;
            cursor: pointer;
            font-family: 'Space Mono', monospace;
            font-size: 0.8rem;
            transition: all 0.3s ease;
        }

        .logout-btn:hover {
            border-color: var(--error);
            color: var(--error);
        }

        .admin-btn {
            padding: 0.5rem 1rem;
            background: var(--error);
            color: white;
            border: 2px solid var(--error);
            border-radius: 8px;
            cursor: pointer;
            font-family: 'Space Mono', monospace;
            font-size: 0.8rem;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
            margin-left: 0.5rem;
        }

        .admin-btn:hover {
            background: transparent;
            color: var(--error);
        }

        .main-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 2rem;
            margin-bottom: 2rem;
        }

        .card {
            background: var(--surface);
            border-radius: 16px;
            padding: 2rem;
            border: 1px solid var(--border);
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);
            animation: fadeInUp 0.6s ease-out backwards;
        }

        .card:nth-child(1) { animation-delay: 0.1s; }
        .card:nth-child(2) { animation-delay: 0.2s; }

        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .card-title {
            font-family: 'Unbounded', sans-serif;
            font-size: 1.3rem;
            margin-bottom: 1.5rem;
            color: var(--accent);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .card-title::before {
            content: '▸';
            font-size: 1.5rem;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            color: var(--text-dim);
            font-size: 0.85rem;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .form-group input,
        .form-group textarea {
            width: 100%;
            padding: 0.875rem 1rem;
            background: var(--secondary);
            border: 2px solid var(--border);
            border-radius: 8px;
            color: var(--text);
            font-family: 'Space Mono', monospace;
            font-size: 1rem;
            transition: all 0.3s ease;
        }

        .form-group input:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: var(--accent);
            box-shadow: 0 0 0 3px rgba(0, 255, 136, 0.1);
        }

        .form-group input:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }

        .time-inputs {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
        }

        .signature-pad {
            width: 100%;
            height: 200px;
            border: 2px dashed var(--border);
            border-radius: 8px;
            background: var(--secondary);
            cursor: crosshair;
            transition: border-color 0.3s ease;
        }

        .signature-pad:hover {
            border-color: var(--accent);
        }

        .signature-actions {
            display: flex;
            gap: 0.5rem;
            margin-top: 0.5rem;
        }

        .btn {
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 8px;
            font-family: 'Space Mono', monospace;
            font-size: 0.9rem;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s ease;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--accent) 0%, var(--accent-dark) 100%);
            color: var(--primary);
            flex: 1;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 24px rgba(0, 255, 136, 0.3);
        }

        .btn-secondary {
            background: var(--secondary);
            color: var(--text-dim);
            border: 2px solid var(--border);
        }

        .btn-secondary:hover {
            border-color: var(--accent);
            color: var(--accent);
        }

        .stats-card {
            grid-column: 1 / -1;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
        }

        .stat-item {
            text-align: center;
            padding: 1.5rem;
            background: rgba(0, 255, 136, 0.05);
            border: 1px solid rgba(0, 255, 136, 0.2);
            border-radius: 12px;
            transition: all 0.3s ease;
        }

        .stat-item:hover {
            transform: translateY(-4px);
            background: rgba(0, 255, 136, 0.1);
            border-color: var(--accent);
        }

        .stat-label {
            font-size: 0.75rem;
            color: var(--text-dim);
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 0.5rem;
        }

        .stat-value {
            font-family: 'Unbounded', sans-serif;
            font-size: 2rem;
            font-weight: 700;
            color: var(--accent);
        }

        .attendance-list {
            grid-column: 1 / -1;
        }

        .attendance-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 1rem;
        }

        .attendance-table th,
        .attendance-table td {
            padding: 1rem;
            text-align: left;
            border-bottom: 1px solid var(--border);
        }

        .attendance-table th {
            color: var(--accent);
            font-size: 0.85rem;
            text-transform: uppercase;
            letter-spacing: 1px;
            font-weight: 700;
        }

        .attendance-table tr {
            transition: background 0.2s ease;
        }

        .attendance-table tbody tr:hover {
            background: rgba(0, 255, 136, 0.05);
        }

        .signature-preview {
            width: 60px;
            height: 40px;
            border-radius: 4px;
            border: 1px solid var(--border);
        }

        .delete-btn {
            background: transparent;
            border: 1px solid var(--error);
            color: var(--error);
            padding: 0.5rem 1rem;
            border-radius: 6px;
            cursor: pointer;
            font-family: 'Space Mono', monospace;
            font-size: 0.75rem;
            transition: all 0.3s ease;
        }

        .delete-btn:hover {
            background: var(--error);
            color: var(--primary);
        }

        .export-btn {
            background: linear-gradient(135deg, var(--accent) 0%, var(--accent-dark) 100%);
            color: var(--primary);
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-family: 'Space Mono', monospace;
            font-size: 0.9rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 1rem;
            transition: all 0.3s ease;
        }

        .export-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 24px rgba(0, 255, 136, 0.3);
        }

        @media (max-width: 768px) {
            .main-grid {
                grid-template-columns: 1fr;
            }

            .header h1 {
                font-size: 2rem;
            }

            .time-inputs {
                grid-template-columns: 1fr;
            }

            .stats-grid {
                grid-template-columns: 1fr;
            }

            .attendance-table {
                font-size: 0.85rem;
            }

            .attendance-table th,
            .attendance-table td {
                padding: 0.75rem 0.5rem;
            }
        }

        .empty-state {
            text-align: center;
            padding: 3rem 1rem;
            color: var(--text-dim);
        }

        .empty-state svg {
            width: 80px;
            height: 80px;
            margin-bottom: 1rem;
            opacity: 0.3;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div>
                <h1>Absensi</h1>
                <p>Sistem Pencatatan Kehadiran Digital</p>
            </div>
            <div class="user-info">
                <a href="income.php" class="income-btn">💰 Pendapatan</a>
                <span>Selamat datang, <?php echo htmlspecialchars($user['username']); ?>!</span>
                <form method="POST" action="logout.php" style="display: inline;">
                    <button type="submit" class="logout-btn">Logout</button>
                </form>
            </div>
        </div>

        <div class="main-grid">
            <!-- Form Input Absensi -->
            <div class="card">
                <h2 class="card-title">Input Kehadiran</h2>

                <form id="attendanceForm">
                    <div class="form-group">
                        <label for="day">Hari</label>
                        <input type="text" id="day" disabled>
                    </div>

                    <div class="form-group">
                        <label for="date">Tanggal</label>
                        <input type="date" id="date" required>
                    </div>

                    <div class="form-group">
                        <label>Jam Kerja</label>
                        <div class="time-inputs">
                            <div>
                                <input type="time" id="startTime" placeholder="Mulai" required>
                            </div>
                            <div>
                                <input type="time" id="endTime" placeholder="Selesai" required>
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="totalHours">Total Jam (Otomatis)</label>
                        <input type="text" id="totalHours" disabled>
                    </div>

                    <button type="submit" class="btn btn-primary">Simpan Absensi</button>
                </form>
            </div>

            <!-- Signature Pad -->
            <div class="card">
                <h2 class="card-title">Tanda Tangan Digital</h2>

                <div class="form-group">
                    <label>Silakan Tanda Tangan di Area Bawah</label>
                    <canvas id="signaturePad" class="signature-pad"></canvas>
                    <div class="signature-actions">
                        <button type="button" class="btn btn-secondary" id="clearSignature">Hapus</button>
                    </div>
                </div>
            </div>

            <!-- Statistics -->
            <div class="card stats-card">
                <h2 class="card-title">Statistik Bulan Ini</h2>

                <div class="stats-grid">
                    <div class="stat-item">
                        <div class="stat-label">Total Hari Kerja</div>
                        <div class="stat-value" id="totalDays"><?php echo $total_days; ?></div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-label">Total Jam Kerja</div>
                        <div class="stat-value" id="totalMonthHours"><?php echo formatHours($total_hours); ?></div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-label">Rata-rata Jam/Hari</div>
                        <div class="stat-value" id="avgHours"><?php echo formatHours($avg_hours); ?></div>
                    </div>
                </div>
            </div>

            <!-- Attendance List -->
            <div class="card attendance-list">
                <h2 class="card-title">Riwayat Absensi</h2>

                <a href="export.php" class="export-btn">Export ke Excel</a>

                <table class="attendance-table">
                    <thead>
                        <tr>
                            <th>Hari</th>
                            <th>Tanggal</th>
                            <th>Jam Masuk</th>
                            <th>Jam Keluar</th>
                            <th>Total Jam</th>
                            <th>Paraf</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody id="attendanceTableBody">
                        <?php if (empty($attendance_data)): ?>
                            <tr>
                                <td colspan="7" class="empty-state">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                    </svg>
                                    <p>Belum ada data absensi. Mulai catat kehadiran Anda!</p>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($attendance_data as $item): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($item['day']); ?></td>
                                    <td><?php echo date('d/m/Y', strtotime($item['date'])); ?></td>
                                    <td><?php echo htmlspecialchars($item['start_time']); ?></td>
                                    <td><?php echo htmlspecialchars($item['end_time']); ?></td>
                                    <td><?php echo formatHours($item['total_hours']); ?></td>
                                    <td><img src="signatures/<?php echo htmlspecialchars($item['signature']); ?>" class="signature-preview" alt="Paraf"></td>
                                    <td>
                                        <button class="delete-btn" onclick="deleteAttendance(<?php echo $item['id']; ?>)">Hapus</button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script>
        // Signature Pad Setup
        const canvas = document.getElementById('signaturePad');
        const ctx = canvas.getContext('2d');
        let isDrawing = false;
        let hasSignature = false;

        // Set canvas size
        function resizeCanvas() {
            const rect = canvas.getBoundingClientRect();
            canvas.width = rect.width;
            canvas.height = rect.height;
        }
        resizeCanvas();
        window.addEventListener('resize', resizeCanvas);

        // Drawing functions
        function startDrawing(e) {
            isDrawing = true;
            hasSignature = true;
            const rect = canvas.getBoundingClientRect();
            const x = (e.clientX || e.touches[0].clientX) - rect.left;
            const y = (e.clientY || e.touches[0].clientY) - rect.top;
            ctx.beginPath();
            ctx.moveTo(x, y);
        }

        function draw(e) {
            if (!isDrawing) return;
            e.preventDefault();
            const rect = canvas.getBoundingClientRect();
            const x = (e.clientX || e.touches[0].clientX) - rect.left;
            const y = (e.clientY || e.touches[0].clientY) - rect.top;
            ctx.lineTo(x, y);
            ctx.strokeStyle = '#000000';
            ctx.lineWidth = 2;
            ctx.lineCap = 'round';
            ctx.stroke();
        }

        function stopDrawing() {
            isDrawing = false;
        }

        // Mouse events
        canvas.addEventListener('mousedown', startDrawing);
        canvas.addEventListener('mousemove', draw);
        canvas.addEventListener('mouseup', stopDrawing);
        canvas.addEventListener('mouseout', stopDrawing);

        // Touch events
        canvas.addEventListener('touchstart', startDrawing);
        canvas.addEventListener('touchmove', draw);
        canvas.addEventListener('touchend', stopDrawing);

        // Clear signature
        document.getElementById('clearSignature').addEventListener('click', () => {
            ctx.clearRect(0, 0, canvas.width, canvas.height);
            hasSignature = false;
        });

        // Set current day
        function updateDay() {
            const days = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
            const today = new Date();
            document.getElementById('day').value = days[today.getDay()];

            // Set default date to today
            const dateInput = document.getElementById('date');
            if (!dateInput.value) {
                dateInput.value = today.toISOString().split('T')[0];
            }
        }
        updateDay();

        // Update day when date changes
        document.getElementById('date').addEventListener('change', function() {
            const days = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
            const selectedDate = new Date(this.value + 'T00:00:00');
            document.getElementById('day').value = days[selectedDate.getDay()];
        });

        // Format hours function
        function formatHours(hours) {
            const hoursInt = Math.floor(hours);
            const minutes = Math.round((hours - hoursInt) * 60);
            return hoursInt + ' jam ' + minutes + ' menit';
        }

        // Calculate total hours
        function calculateHours() {
            const startTime = document.getElementById('startTime').value;
            const endTime = document.getElementById('endTime').value;

            if (startTime && endTime) {
                const start = new Date('2000-01-01 ' + startTime);
                const end = new Date('2000-01-01 ' + endTime);
                let diff = (end - start) / (1000 * 60 * 60);

                if (diff < 0) diff += 24;

                document.getElementById('totalHours').value = formatHours(diff);
                return diff;
            }
            return 0;
        }

        document.getElementById('startTime').addEventListener('change', calculateHours);
        document.getElementById('endTime').addEventListener('change', calculateHours);

        // Form submission
        document.getElementById('attendanceForm').addEventListener('submit', function(e) {
            e.preventDefault();

            if (!hasSignature) {
                alert('Silakan tanda tangan terlebih dahulu!');
                return;
            }

            const formData = new FormData();
            formData.append('day', document.getElementById('day').value);
            formData.append('date', document.getElementById('date').value);
            formData.append('startTime', document.getElementById('startTime').value);
            formData.append('endTime', document.getElementById('endTime').value);
            formData.append('totalHours', calculateHours());
            formData.append('signature', canvas.toDataURL());

            fetch('submit.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Reset form
                    document.getElementById('attendanceForm').reset();
                    ctx.clearRect(0, 0, canvas.width, canvas.height);
                    hasSignature = false;
                    updateDay();
                    document.getElementById('totalHours').value = '';

                    // Reload page to show new data
                    location.reload();
                } else {
                    alert('Error: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Terjadi kesalahan saat menyimpan data');
            });
        });

        // Delete attendance
        function deleteAttendance(id) {
            if (confirm('Yakin ingin menghapus data absensi ini?')) {
                fetch('delete.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: 'id=' + id
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        location.reload();
                    } else {
                        alert('Error: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Terjadi kesalahan saat menghapus data');
                });
            }
        }
    </script>
</body>
</html>
