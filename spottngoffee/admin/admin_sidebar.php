<div class="sidebar">
    <h2><i class="fas fa-coffee"></i> Form</h2>
    <ul>
        <li><a href="admin_dashboard.php" class="<?php echo (basename($_SERVER['PHP_SELF']) == 'admin_dashboard.php') ? 'active' : ''; ?>"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
        <li><a href="manage_coffee_shops.php" class="<?php echo (basename($_SERVER['PHP_SELF']) == 'manage_coffee_shops.php') ? 'active' : ''; ?>"><i class="fas fa-store"></i> Kelola Kedai Kopi</a></li>
        <li><a href="manage_menu_items.php" class="<?php echo (basename($_SERVER['PHP_SELF']) == 'manage_menu_items.php') ? 'active' : ''; ?>"><i class="fas fa-mug-hot"></i> Kelola Item Menu</a></li>
        <li><a href="manage_recommendations.php" class="<?php echo (basename($_SERVER['PHP_SELF']) == 'manage_recommendations.php') ? 'active' : ''; ?>"><i class="fas fa-star"></i> Kelola Rekomendasi</a></li>
        <li><a href="manage_users.php" class="<?php echo (basename($_SERVER['PHP_SELF']) == 'manage_users.php') ? 'active' : ''; ?>"><i class="fas fa-users-cog"></i> Kelola Admin</a></li>
    </ul>

    <div class="coffee-cup-clock-container">
        <div id="digitalClockCup" class="digital-clock-cup">
            <div id="timeDisplay" class="time"></div>
        </div>
        <div id="dateDisplay" class="date"></div>
    </div>

    <a href="admin_logout.php" class="logout-button"><i class="fas fa-sign-out-alt"></i> Logout</a>
</div>

<style>
    /* CSS untuk Sidebar (tidak banyak berubah) */
    body {
        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        margin: 0;
        background-color: #FDF7E4; /* Krem Muda */
        color: #333;
        display: flex;
        min-height: 100vh;
    }
    .sidebar {
        width: 250px;
        background-color: #5C4033; /* Cokelat Kopi Gelap */
        color: white;
        padding: 20px;
        box-shadow: 2px 0 10px rgba(0,0,0,0.1);
        display: flex;
        flex-direction: column;
        justify-content: space-between;
    }

    .sidebar h2 {
        text-align: center;
        color: #FDF7E4;
        margin-bottom: 30px;
    }

    .sidebar ul {
        list-style: none;
        padding: 0;
        flex-grow: 1;
    }

    .sidebar ul li {
        margin-bottom: 10px;
    }

    .sidebar ul li a {
        display: block;
        color: white;
        text-decoration: none;
        padding: 10px 15px;
        border-radius: 5px;
        transition: background-color 0.3s ease;
    }

    .sidebar ul li a:hover,
    .sidebar ul li a.active {
        background-color: #7B3F00;
    }

    .sidebar ul li a i {
        margin-right: 10px;
    }

    .logout-button {
        display: block;
        background-color: #B05C35;
        color: white;
        text-align: center;
        padding: 10px 15px;
        border-radius: 5px;
        text-decoration: none;
        margin-top: 20px;
        transition: background-color 0.3s ease;
    }

    .logout-button:hover {
        background-color: #8B4513;
    }

    .logout-button i {
        margin-right: 8px;
    }

    /* CSS Baru untuk Jam Digital Bentuk Cangkir Kopi */
    .coffee-cup-clock-container {
        margin-top: 30px;
        text-align: center;
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 15px; /* Jarak antara jam dan tanggal */
    }

    .digital-clock-cup {
        width: 180px; /* Lebar lingkaran jam */
        height: 180px; /* Tinggi lingkaran jam */
        background: radial-gradient(circle at 50% 50%, #4A3026 0%, #4A3026 80%, #2A1A14 100%); /* Gradien gelap untuk dasar kopi */
        border: 10px solid #B05C35; /* Warna cangkir (bingkai tebal) */
        border-radius: 50%; /* Membuatnya bulat sempurna */
        box-shadow: 0 8px 20px rgba(0,0,0,0.4), inset 0 0 15px rgba(255,255,255,0.1); /* Bayangan untuk kedalaman */
        display: flex; /* Untuk memusatkan teks jam */
        justify-content: center;
        align-items: center;
        position: relative;
        overflow: hidden; /* Penting untuk efek uap */
    }

    .digital-clock-cup::before { /* Efek busa kopi */
        content: '';
        position: absolute;
        top: 15%; /* Posisikan busa di bagian atas */
        left: 50%;
        transform: translateX(-50%);
        width: 80%;
        height: 30%;
        background-color: rgba(240, 230, 210, 0.8); /* Warna busa krem */
        border-radius: 50%;
        filter: blur(5px); /* Efek blur untuk busa */
        z-index: 1; /* Pastikan di bawah teks jam */
    }

    .digital-clock-cup .time {
        position: relative; /* Penting agar z-index bekerja di atas pseudo-element */
        z-index: 2; /* Pastikan teks jam di atas busa */
        font-size: 2.5em; /* Ukuran font jam */
        font-weight: bold;
        color: #FDF7E4; /* Warna teks jam */
        font-family: 'Consolas', 'Monaco', monospace;
        letter-spacing: 2px;
        text-shadow: 0 0 10px rgba(255,255,255,0.8), 0 0 20px rgba(255,255,255,0.6); /* Efek neon/glow */
    }

    /* Styling untuk Tanggal (diperbesar dan diletakkan di bawah cangkir) */
    .coffee-cup-clock-container .date {
        font-size: 1.5em; /* Ukuran font tanggal diperbesar lebih lagi */
        color: #FDF7E4; /* Warna teks tanggal */
        background-color: #4A3026; /* Latar belakang untuk tanggal */
        padding: 10px 20px;
        border-radius: 8px;
        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        font-weight: 600;
        text-shadow: 0 0 5px rgba(0,0,0,0.5);
    }
</style>

<script>
    // Pastikan DOM sudah dimuat sebelum menjalankan script
    document.addEventListener('DOMContentLoaded', function() {
        const dateDisplay = document.getElementById('dateDisplay');
        const timeDisplay = document.getElementById('timeDisplay');

        function updateDateTime() {
            const now = new Date();

            // Format Waktu (HH:MM:SS)
            const hours = String(now.getHours()).padStart(2, '0');
            const minutes = String(now.getMinutes()).padStart(2, '0');
            const seconds = String(now.getSeconds()).padStart(2, '0');
            timeDisplay.textContent = `${hours}:${minutes}:${seconds}`;

            // Format Tanggal (Nama Hari, DD Bulan YYYY)
            const days = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
            const months = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
                            'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];

            const dayName = days[now.getDay()];
            const day = now.getDate();
            const monthName = months[now.getMonth()];
            const year = now.getFullYear();

            dateDisplay.textContent = `${dayName}, ${day} ${monthName} ${year}`;
        }

        // Panggil fungsi segera saat halaman dimuat
        updateDateTime();

        // Perbarui setiap detik
        setInterval(updateDateTime, 1000);
    });
</script>