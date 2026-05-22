<?php
session_start();
require_once 'config.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

// Get user data
$user_id = $_SESSION['user_id'];
$stmt = $pdo->prepare("SELECT username FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

// Get total hours worked
$stmt = $pdo->prepare("SELECT SUM(total_hours) as total_hours FROM attendance WHERE user_id = ?");
$stmt->execute([$user_id]);
$result = $stmt->fetch(PDO::FETCH_ASSOC);
$total_hours = $result['total_hours'] ?? 0;

// Calculate income (10k per hour)
$hourly_rate = 10000;
$total_income = $total_hours * $hourly_rate;

// Format income with thousand separator
$formatted_income = number_format($total_income, 0, ',', '.');

// Format hours
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
    <title>Total Pendapatan - Sistem Absensi Karyawan</title>
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
            max-width: 800px;
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
            text-decoration: none;
        }

        .logout-btn:hover {
            border-color: var(--error);
            color: var(--error);
        }

        .back-btn {
            padding: 0.5rem 1rem;
            background: var(--secondary);
            color: var(--text-dim);
            border: 2px solid var(--border);
            border-radius: 8px;
            cursor: pointer;
            font-family: 'Space Mono', monospace;
            font-size: 0.8rem;
            transition: all 0.3s ease;
            text-decoration: none;
            margin-right: 0.5rem;
        }

        .back-btn:hover {
            border-color: var(--accent);
            color: var(--accent);
        }

        .income-card {
            background: var(--surface);
            border-radius: 16px;
            padding: 3rem;
            border: 1px solid var(--border);
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);
            text-align: center;
            animation: fadeInUp 0.6s ease-out;
        }

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

        .income-title {
            font-family: 'Unbounded', sans-serif;
            font-size: 2rem;
            margin-bottom: 2rem;
            color: var(--accent);
        }

        .income-amount {
            font-family: 'Unbounded', sans-serif;
            font-size: 4rem;
            font-weight: 700;
            color: var(--accent);
            margin-bottom: 1rem;
            text-shadow: 0 0 20px rgba(0, 255, 136, 0.3);
        }

        .income-details {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 2rem;
            margin-top: 2rem;
        }

        .detail-item {
            background: rgba(0, 255, 136, 0.05);
            border: 1px solid rgba(0, 255, 136, 0.2);
            border-radius: 12px;
            padding: 1.5rem;
            transition: all 0.3s ease;
        }

        .detail-item:hover {
            transform: translateY(-4px);
            background: rgba(0, 255, 136, 0.1);
            border-color: var(--accent);
        }

        .detail-label {
            font-size: 0.85rem;
            color: var(--text-dim);
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 0.5rem;
        }

        .detail-value {
            font-family: 'Unbounded', sans-serif;
            font-size: 1.8rem;
            font-weight: 700;
            color: var(--accent);
        }

        .rate-info {
            margin-top: 2rem;
            padding: 1rem;
            background: rgba(255, 255, 255, 0.05);
            border-radius: 8px;
            font-size: 0.9rem;
            color: var(--text-dim);
        }

        @media (max-width: 768px) {
            .income-details {
                grid-template-columns: 1fr;
            }

            .header h1 {
                font-size: 2rem;
            }

            .income-amount {
                font-size: 3rem;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div>
                <h1>Total Pendapatan</h1>
                <p>Ringkasan penghasilan dari jam kerja</p>
            </div>
            <div class="user-info">
                <a href="index.php" class="back-btn">← Kembali</a>
                <span>Selamat datang, <?php echo htmlspecialchars($user['username']); ?>!</span>
                <form method="POST" action="logout.php" style="display: inline;">
                    <button type="submit" class="logout-btn">Logout</button>
                </form>
            </div>
        </div>

        <div class="income-card">
            <h2 class="income-title">Total Pendapatan Anda</h2>
            <div class="income-amount">Rp <?php echo $formatted_income; ?></div>

            <div class="income-details">
                <div class="detail-item">
                    <div class="detail-label">Total Jam Kerja</div>
                    <div class="detail-value"><?php echo formatHours($total_hours); ?></div>
                </div>
                <div class="detail-item">
                    <div class="detail-label">Tarif per Jam</div>
                    <div class="detail-value">Rp 10.000</div>
                </div>
            </div>

            <div class="rate-info">
                💡 Perhitungan: <?php echo formatHours($total_hours); ?> × Rp 10.000 = Rp <?php echo $formatted_income; ?>
            </div>
        </div>
    </div>
</body>
</html>
