<?php
session_start();
// Pastikan hanya admin yang bisa mengakses halaman ini
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: admin_login.php');
    exit();
}
// Sesuaikan path jika db.php tidak di direktori yang sama
// require_once __DIR__ . '/../config/db.php';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Admin - Spot Ngoffee</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        /* Global Admin Styles (idealnya dipindahkan ke admin_styles.css) */
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            margin: 0;
            background-color: #FDF7E4; /* Krem Muda */
            color: #333;
            display: flex; /* Untuk layout sidebar dan content */
            min-height: 100vh;
        }

        .main-content {
            flex-grow: 1; /* Mengambil sisa ruang */
            padding: 20px;
            background-color: #fff; /* Warna latar belakang kontainer utama */
            border-radius: 8px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.05);
            margin: 20px; /* Margin di sekitar main content */
        }

        h1 {
            color: #5C4033;
            margin-bottom: 20px;
        }

        .dashboard-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 20px;
            margin-top: 30px;
        }

        .dashboard-card {
            background-color: #fff;
            padding: 25px;
            border-radius: 8px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
            text-align: center;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            align-items: center;
            border: 1px solid #ddd;
        }

        .dashboard-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 6px 12px rgba(0, 0, 0, 0.15);
        }

        .dashboard-card i {
            font-size: 3.5em;
            color: #A0522D; /* Cokelat Bata */
            margin-bottom: 15px;
        }

        .dashboard-card h3 {
            color: #5C4033;
            margin-bottom: 10px;
            font-size: 1.4em;
        }

        .dashboard-card p {
            color: #666;
            font-size: 0.95em;
            line-height: 1.5;
            flex-grow: 1; /* Agar teks mengisi ruang */
            margin-bottom: 20px;
        }

        .btn-manage {
            display: inline-block;
            background-color: #7B3F00; /* Cokelat Kopi Lebih Terang */
            color: white;
            padding: 10px 20px;
            border-radius: 5px;
            text-decoration: none;
            font-weight: bold;
            transition: background-color 0.3s ease;
            margin-top: 15px; /* Jarak dari paragraf */
        }

        .btn-manage:hover {
            background-color: #8B4513; /* Kopi Lebih Gelap saat Hover */
        }
    </style>
</head>
<body>
    <?php include 'admin_sidebar.php'; // Include the sidebar ?>

    <div class="main-content">
        <h1>Selamat Datang, <?php echo htmlspecialchars($_SESSION['username']); ?>!</h1>
        <div class="dashboard-grid">
            <div class="dashboard-card">
                <i class="fas fa-store"></i>
                <h3>Kedai Kopi</h3>
                <p>Tambah, edit, atau hapus informasi tentang kedai kopi.</p>
                <a href="manage_coffee_shops.php" class="btn-manage">Kelola Kedai</a>
            </div>
            <div class="dashboard-card">
                <i class="fas fa-mug-hot"></i>
                <h3>Item Menu</h3>
                <p>Kelola menu makanan dan minuman untuk setiap kedai kopi.</p>
                <a href="manage_menu_items.php" class="btn-manage">Kelola Menu</a>
            </div>
            <div class="dashboard-card">
                <i class="fas fa-star"></i>
                <h3>Rekomendasi</h3>
                <p>Atur rekomendasi khusus untuk berbagai edisi.</p>
                <a href="manage_recommendations.php" class="btn-manage">Kelola Rekomendasi</a>
            </div>
            <div class="dashboard-card">
                <i class="fas fa-users-cog"></i>
                <h3>Akun Admin</h3>
                <p>Kelola akun admin.</p>
                <a href="manage_users.php" class="btn-manage">Kelola Admin</a>
            </div>
        </div>
    </div>
</body>
</html>