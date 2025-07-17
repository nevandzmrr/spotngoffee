<?php
// Mulai sesi PHP
session_start();

// Memuat file konfigurasi database
// Pastikan path ke db.php benar relatif terhadap lokasi file ini
require_once __DIR__ . '/config/db.php';

$regionName = null;
$regionShops = [];

// Periksa apakah parameter 'name' ada di URL
if (isset($_GET['name'])) {
    $regionName = urldecode($_GET['name']);

    // Ambil coffee shop berdasarkan region
    // Menggunakan JOIN dengan tabel editions untuk mendapatkan nama edisi
    try {
        $stmt = $pdo->prepare("SELECT cs.*, e.name AS edition_name FROM coffee_shops cs LEFT JOIN editions e ON cs.edition_id = e.id WHERE cs.region = ? ORDER BY RAND()");
        $stmt->execute([$regionName]);
        $regionShops = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Decode images and menu items for each shop
        foreach ($regionShops as $key => $shop) {
            // Pastikan kunci 'images' ada sebelum di-decode
            if (isset($shop['images']) && !empty($shop['images'])) {
                $regionShops[$key]['images'] = json_decode($shop['images'], true);
                // Jika decode gagal atau bukan array, set ke array kosong
                if (!is_array($regionShops[$key]['images'])) {
                    $regionShops[$key]['images'] = [];
                }
            } else {
                $regionShops[$key]['images'] = [];
            }

            // Pastikan kunci 'menu_items' ada sebelum di-decode
            // Hapus bagian ini jika menu_items tidak relevan di halaman ini untuk performa
            if (isset($shop['menu_items']) && !empty($shop['menu_items'])) {
                $regionShops[$key]['menu_items'] = json_decode($shop['menu_items'], true);
                // Jika decode gagal atau bukan array, set ke array kosong
                if (!is_array($regionShops[$key]['menu_items'])) {
                    $regionShops[$key]['menu_items'] = [];
                }
            } else {
                $regionShops[$key]['menu_items'] = [];
            }
        }
    } catch (PDOException $e) {
        // Tangani error database jika terjadi
        error_log("Database error: " . $e->getMessage());
        $regionShops = []; // Pastikan array kosong jika ada error
    }
} else {
    // Redirect jika nama wilayah tidak ditemukan di URL
    header("Location: index.php?page=region");
    exit();
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Coffee Shops di <?php echo htmlspecialchars($regionName ?? 'Wilayah'); ?> - Spot Ngoffee</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700&family=Lora:wght@400;700&display=swap" rel="stylesheet">
    <style>
        /*
        -------------------------------------------------------------
        CSS UMUM & TEMA (Mengikuti versi original untuk header/nav, disesuaikan sedikit)
        -------------------------------------------------------------
        */
        :root {
            --color-coffee-dark: #4A2C2A; /* Coklat Kopi Gelap */
            --color-coffee-medium: #8B4513; /* Coklat Kopi Sedang */
            --color-coffee-light: #D2B48C; /* Coklat Kopi Muda */
            --color-cream: #F5E8C7; /* Krem */
            --color-text-dark: #333;
            --color-text-light: #f8f9fa;
            --color-accent-blue: #007bff; /* Biru Aksen */
            --color-accent-green: #28a745;
            --color-shadow: rgba(0, 0, 0, 0.1); /* Bayangan standar */

            /* Palet baru untuk card lebih unik */
            --card-border-outer: #dcdcdc; /* Abu-abu terang */
            --card-border-inner: #f0f0f0; /* Abu-abu sangat terang */
            --card-accent-color: #A0522D; /* Coklat oranye aksen, dari sebelumnya */
        }

        *, *::before, *::after {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Poppins', sans-serif; /* Font untuk body */
            line-height: 1.6;
            color: var(--color-text-dark);
            background-color: var(--color-cream);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            overflow-x: hidden;
        }

        a {
            text-decoration: none;
            color: var(--color-accent-blue);
            transition: color 0.3s ease;
        }

        a:hover {
            color: var(--color-coffee-medium);
        }

        .container {
            max-width: 1300px;
            margin: 0 auto;
            padding: 1.5rem 1rem;
            width: 100%;
            flex-grow: 1;
            display: flex; /* Tambahkan ini untuk flexbox */
            flex-direction: column; /* Mengatur arah flex */
            align-items: center; /* Memusatkan item secara horizontal */
        }

        header {
            background-color: var(--color-coffee-dark);
            padding: 1rem 1.5rem;
            box-shadow: 0 4px 10px var(--color-shadow);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 1rem;
        }

        .logo {
            font-family: 'Lora', serif; /* Font untuk logo */
            font-size: 2.2rem;
            font-weight: bold;
            color: var(--color-coffee-light);
            text-decoration: none;
            flex-shrink: 0;
        }

        .nav-buttons {
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
        }

        .nav-button {
            background-color: var(--color-coffee-medium);
            color: var(--color-text-light);
            border: none;
            padding: 0.75rem 1.2rem;
            border-radius: 25px;
            cursor: pointer;
            font-size: 0.95rem;
            transition: background-color 0.3s ease, transform 0.2s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .nav-button:hover {
            background-color: var(--color-coffee-dark);
            transform: translateY(-2px);
        }
        .nav-button.active {
            background-color: var(--color-coffee-dark);
        }
        .admin-login-button {
            background-color: var(--color-accent-blue);
            padding: 0.75rem 1.2rem;
            border-radius: 25px;
            color: white;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            text-decoration: none;
            transition: background-color 0.3s ease, transform 0.2s ease;
            position: relative;
        }
        .admin-login-button:hover {
            background-color: #0056b3;
            transform: translateY(-2px);
        }
        .admin-login-button i {
            font-size: 1.1rem;
        }

        /* Tombol Kembali ke Beranda */
        .back-to-home-button {
            display: inline-flex;
            align-items: center;
            gap: 0.6rem;
            background-color: var(--color-coffee-medium);
            color: var(--color-text-light);
            padding: 0.8rem 1.5rem;
            border-radius: 30px;
            text-decoration: none;
            font-weight: 600;
            font-size: 1rem;
            margin-top: 1.5rem; /* Memberi jarak dari header region */
            margin-bottom: 2.5rem; /* Memberi jarak ke grid */
            transition: background-color 0.3s ease, transform 0.2s ease, box-shadow 0.3s ease;
            box-shadow: 0 2px 5px var(--color-shadow);
        }

        .back-to-home-button:hover {
            background-color: var(--color-coffee-dark);
            transform: translateY(-3px);
            box-shadow: 0 5px 10px rgba(0, 0, 0, 0.2);
        }


        /*
        -------------------------------------------------------------
        GAYA SPESIFIK UNTUK HALAMAN DETAIL REGION (Diperbarui untuk ukuran LEBIH BESAR & MEMANJANG KE BAWAH)
        -------------------------------------------------------------
        */
        .region-header {
            text-align: center;
            margin-bottom: 2.5rem;
            background-color: #fff;
            padding: 2.5rem;
            border-radius: 15px;
            box-shadow: 0 8px 20px var(--color-shadow);
            border: 1px solid var(--color-coffee-light);
            width: 100%; /* Pastikan header mengambil lebar penuh container */
        }
        .region-header h1 {
            font-family: 'Lora', serif; /* Font untuk judul region */
            font-size: 3.5rem;
            color: var(--color-coffee-dark);
            margin-bottom: 0.5rem;
            line-height: 1.2;
        }
        .region-header p {
            font-size: 1.2rem;
            color: #666;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
        }

        /* Daftar Kartu Tempat (Coffee Shops) - Lebih Besar dan Lebih Lebar & Lebih Tinggi */
        .place-card-grid {
            display: grid;
            gap: 3rem; /* Tambah gap lebih besar lagi untuk kartu yang lebih besar */
            grid-template-columns: repeat(auto-fit, minmax(400px, 1fr)); /* LEBAR MINIMUM KARTU DITINGKATKAN LEBIH DARI SEBELUMNYA */
            padding-bottom: 2.5rem; /* Tambah padding bawah */
            width: 100%; /* Pastikan grid mengambil lebar penuh container */
        }

        .place-card {
            background-color: #fff;
            border-radius: 20px; /* Sedikit lebih membulat lagi */
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.15), /* Bayangan utama lebih kuat */
                        0 20px 40px rgba(0, 0, 0, 0.1); /* Bayangan tambahan untuk kedalaman */
            overflow: hidden;
            display: flex;
            flex-direction: column;
            cursor: pointer;
            transition: transform 0.4s ease, box-shadow 0.4s ease;
            position: relative;
            border: 2px solid var(--card-border-outer);
        }

        .place-card::before {
            content: '';
            position: absolute;
            top: 6px; /* Menyesuaikan dengan border-radius baru */
            left: 6px;
            right: 6px;
            bottom: 6px;
            border: 2px solid var(--card-border-inner);
            border-radius: 17px; /* Menyesuaikan dengan border-radius place-card */
            pointer-events: none;
            z-index: 1;
        }

        .place-card:hover {
            transform: translateY(-15px) scale(1.03); /* Angkat lebih tinggi dan sedikit membesar */
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.25),
                        0 30px 60px rgba(0, 0, 0, 0.2); /* Bayangan lebih kuat saat hover */
        }

        .place-image-wrapper {
            position: relative;
            width: 100%;
            padding-bottom: 70%; /* MENINGKATKAN KETINGGIAN GAMBAR UNTUK KESAN MEMANJANG KE BAWAH */
            overflow: hidden;
            background-color: #f0f0f0;
            border-bottom: 1px solid #eee;
            z-index: 2;
        }

        .place-image {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
            transition: transform 0.5s ease;
        }
        .place-card:hover .place-image {
            transform: scale(1.08);
        }

        .place-info {
            padding: 2.2rem; /* LEBIH BANYAK PADDING DI DALAM INFO UNTUK KESAN LEBIH BESAR */
            flex-grow: 1;
            display: flex;
            flex-direction: column;
            position: relative;
            z-index: 2;
        }

        .place-name {
            font-size: 2.1rem; /* Nama lebih besar lagi */
            margin-bottom: 0.8rem;
            color: var(--color-coffee-dark);
            font-weight: 700;
            font-family: 'Lora', serif;
            line-height: 1.2;
        }

        .place-location {
            font-size: 1.1rem; /* Lokasi sedikit lebih besar lagi */
            color: #777;
            margin-bottom: 1.5rem; /* Margin lebih besar */
            display: flex;
            align-items: center;
            gap: 0.6rem;
        }

        .place-details {
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 1rem; /* Ukuran teks tag sedikit lebih besar lagi */
            color: #555;
            margin-top: auto;
            margin-bottom: 1.8rem; /* Margin lebih besar sebelum tombol */
            flex-wrap: wrap;
            gap: 1rem; /* Gap antara tag lebih besar */
        }

        .place-edition, .place-region {
            padding: 0.7rem 1.4rem; /* Padding tag lebih besar lagi */
            border-radius: 28px; /* Radius tag sedikit lebih besar */
            font-size: 1rem; /* Ukuran font tag lebih besar */
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1); /* Bayangan sedikit lebih jelas */
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }
        .place-edition:hover, .place-region:hover {
            transform: translateY(-3px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
        }
        /* Warna spesifik untuk tag Edisi */
        .place-edition {
            background-color: #ffe0b2;
            border: 1px solid #ffcc80;
            color: #e65100;
        }
        /* Warna spesifik untuk tag Region */
        .place-region {
            background-color: #c8e6c9;
            border: 1px solid #a5d6a7;
            color: #2e7d32;
        }

        .btn-maps {
            display: block;
            width: 100%;
            padding: 1.1rem; /* Padding tombol maps lebih besar lagi */
            background-color: var(--color-accent-blue);
            color: #fff;
            text-align: center;
            text-decoration: none;
            border-radius: 15px; /* Radius tombol maps sedikit lebih besar */
            transition: background-color 0.3s ease, transform 0.2s ease, box-shadow 0.3s ease;
            font-weight: 600;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.8rem;
            font-size: 1.05rem; /* Ukuran font tombol maps sedikit lebih besar */
            box-shadow: 0 3px 8px rgba(0, 0, 0, 0.2);
        }

        .btn-maps:hover {
            background-color: #0056b3;
            transform: translateY(-4px);
            box-shadow: 0 6px 12px rgba(0, 0, 0, 0.3);
        }

        /* Pesan "Tidak Ada Coffee Shop" (Tidak ada perubahan signifikan pada ukuran ini) */
        .no-shops-message {
            text-align: center;
            font-style: italic;
            color: var(--color-coffee-dark);
            margin-top: 5rem;
            margin-bottom: 5rem;
            font-size: 1.8rem;
            padding: 3rem;
            background-color: #fff;
            border-radius: 15px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
            border: 2px solid var(--color-coffee-light);
            max-width: 700px;
            width: 90%;
            align-self: center;
            line-height: 1.4;
            font-weight: 600;
        }


        /* Footer (Sama dengan index.php) */
        footer {
            background-color: var(--color-coffee-dark);
            color: var(--color-text-light);
            text-align: center;
            padding: 2rem 1rem;
            margin-top: auto;
            border-top-left-radius: 15px;
            border-top-right-radius: 15px;
            box-shadow: 0 -4px 10px rgba(0,0,0,0.2);
            position: relative;
        }
        footer p {
            font-size: 0.95rem;
            margin-bottom: 0.5rem;
        }


        /*
        -------------------------------------------------------------
        MEDIA QUERIES (RESPONSIF) UNTUK HALAMAN REGION DETAIL (Diperbarui untuk ukuran LEBIH BESAR & MEMANJANG KE BAWAH)
        -------------------------------------------------------------
        */
        @media (min-width: 768px) {
            .region-header h1 {
                font-size: 4.5rem;
            }
            .place-card-grid {
                grid-template-columns: repeat(auto-fit, minmax(400px, 1fr)); /* Pertahankan lebar min card lebih besar */
            }
            .no-shops-message {
                font-size: 2.2rem;
                padding: 4rem;
            }
        }

        @media (min-width: 1024px) {
            .region-header h1 {
                font-size: 5.5rem;
            }
            .place-card-grid {
                grid-template-columns: repeat(2, 1fr); /* Tetap 2 kolom di desktop standar untuk memaksimalkan lebar & tinggi per kartu */
                /* Dengan minmax 400px, 2 kolom akan memberi ruang lebih besar per kartu */
            }
            .no-shops-message {
                font-size: 2.5rem;
                padding: 5rem;
            }
        }

        @media (min-width: 1200px) { /* New breakpoint for even larger screens */
            .place-card-grid {
                grid-template-columns: repeat(2, 1fr); /* Pertahankan 2 kolom untuk kesan sangat besar per kartu */
                /* Jika Anda memiliki banyak sekali kartu dan ingin lebih padat, bisa diubah ke 3, tapi kesan "lebih besar" akan berkurang */
            }
        }

        @media (min-width: 1400px) { /* New breakpoint for extra large screens */
            .place-card-grid {
                grid-template-columns: repeat(3, 1fr); /* Di layar yang sangat lebar, bisa 3 kolom */
            }
        }


        /* Untuk mobile, pastikan pesan tetap terlihat besar */
        @media (max-width: 767px) {
            .no-shops-message {
                font-size: 1.3rem;
                padding: 2rem;
                margin-top: 3rem;
                margin-bottom: 3rem;
            }
            .container {
                padding: 1rem;
            }
            .place-card-grid {
                grid-template-columns: 1fr; /* Satu kolom di mobile */
                gap: 2rem; /* Sesuaikan gap di mobile untuk kartu yang lebih besar */
            }
            .place-image-wrapper {
                padding-bottom: 70%; /* Sedikit lebih tinggi di mobile untuk kesan memanjang */
            }
            .place-info {
                padding: 1.5rem; /* Kurangi padding info di mobile */
            }
            .place-name {
                font-size: 1.7rem; /* Nama lebih kecil di mobile, tapi tetap menonjol */
            }
            .place-location {
                font-size: 1rem;
            }
            .place-details {
                flex-direction: column;
                align-items: flex-start;
                gap: 0.6rem;
            }
            .place-edition, .place-region {
                width: 100%;
                text-align: center;
                justify-content: center;
                padding: 0.6rem 1.2rem;
                font-size: 0.9rem;
            }
            .btn-maps {
                padding: 0.9rem;
                font-size: 1rem;
            }
        }
    </style>
</head>
<body>
    <header>
        <a href="index.php" class="logo">Spot Ngoffee</a>
        <div class="nav-buttons">
            <a href="index.php" class="nav-button active"><i class="fas fa-home"></i> Beranda</a>
            <a href="index.php?page=search" class="nav-button"><i class="fas fa-search"></i> Pencarian</a>
            <a href="index.php?page=region" class="nav-button"><i class="fas fa-map-marked-alt"></i> Wilayah</a>
            <a href="index.php?page=about" class="nav-button"><i class="fas fa-info-circle"></i> Tentang Kami</a>
            <a href="admin/login.php" target="_blank" class="admin-login-button">
                <i class="fas fa-key"></i> Admin
            </a>
        </div>
    </header>

    <main class="container">
        <section id="region-detail-page">
            <div class="region-header">
                <h1>Coffee Shops di <?php echo htmlspecialchars($regionName); ?></h1>
                <p><i class="fas fa-map-pin"></i> Temukan spot ngopi terbaik di wilayah <?php echo htmlspecialchars($regionName); ?>!</p>
            </div>

            <a href="index.php" class="back-to-home-button">
                <i class="fas fa-arrow-left"></i> Kembali ke Beranda
            </a>

            <?php if (!empty($regionShops)): ?>
                <div class="place-card-grid">
                    <?php foreach ($regionShops as $shop): ?>
                        <?php
                        // Setel URL gambar default jika tidak ada gambar atau ada masalah decoding
                        $imageUrl = 'https://raw.githubusercontent.com/Anandax24/SpotNgoffee/main/images/coffee-shop-placeholder.jpg';
                        if (isset($shop['images']) && is_array($shop['images']) && count($shop['images']) > 0) {
                            $imageUrl = htmlspecialchars($shop['images'][0]);
                        }
                        ?>
                        <div class="place-card" data-shop-id="<?php echo htmlspecialchars($shop['id'] ?? ''); ?>">
                            <div class="place-image-wrapper">
                                <img src="<?php echo $imageUrl; ?>" alt="<?php echo htmlspecialchars($shop['name'] ?? 'Coffee Shop'); ?>" class="place-image">
                            </div>
                            <div class="place-info">
                                <h3 class="place-name"><?php echo htmlspecialchars($shop['name'] ?? 'Nama Coffee Shop Tidak Diketahui'); ?></h3>
                                <p class="place-location"><i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($shop['location'] ?? 'Lokasi Tidak Diketahui'); ?></p>
                                <div class="place-details">
                                    <?php if (!empty($shop['edition_name'])): ?>
                                        <span class="place-edition"><i class="fas fa-book"></i> Edisi: <?php echo htmlspecialchars($shop['edition_name']); ?></span>
                                    <?php else: ?>
                                        <span class="place-edition"><i class="fas fa-book"></i> Edisi: Umum</span>
                                    <?php endif; ?>

                                    <?php if (!empty($shop['region'])): ?>
                                        <span class="place-region"><i class="fas fa-map-pin"></i> Region: <?php echo htmlspecialchars($shop['region']); ?></span>
                                    <?php else: ?>
                                        <span class="place-region"><i class="fas fa-map-pin"></i> Region: N/A</span>
                                    <?php endif; ?>
                                </div>
                                <a href="<?php echo htmlspecialchars($shop['maps_url'] ?? '#'); ?>" target="_blank" class="btn-maps" onclick="event.stopPropagation();"><i class="fas fa-map"></i> Buka Maps</a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <p class="no-shops-message">Maaf, belum ada coffee shop yang terdaftar di wilayah <?php echo htmlspecialchars($regionName); ?>.</p>
            <?php endif; ?>
        </section>
    </main>

    <footer>
        <p>&copy; <?php echo date("Y"); ?> Spot Ngoffee — Created with <i class="fas fa-heart"></i> by Muhammad Nevan Dzamir.</p>
    </footer>

    <script>
        // Event listener untuk setiap place-card agar mengarahkan ke halaman detail
        document.querySelectorAll('.place-card').forEach(card => {
            card.addEventListener('click', (event) => {
                // Mencegah navigasi jika yang diklik adalah tombol maps
                if (event.target.closest('.btn-maps')) {
                    return;
                }
                const shopId = card.dataset.shopId;
                if (shopId) {
                    window.location.href = `detail_coffee_shop.php?id=${shopId}`;
                }
            });
        });
    </script>
</body>
</html>