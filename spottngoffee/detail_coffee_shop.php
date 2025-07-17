<?php
session_start();
require_once __DIR__ . '/config/db.php';

$shop = null;
if (isset($_GET['id'])) {
    $shopId = $_GET['id'];
    // Ambil semua data coffee_shop, termasuk edition_id
    $stmt = $pdo->prepare("SELECT cs.*, e.name AS edition_name FROM coffee_shops cs LEFT JOIN editions e ON cs.edition_id = e.id WHERE cs.id = ?");
    $stmt->execute([$shopId]);
    $shop = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($shop) {
        $shop['images'] = json_decode($shop['images'] ?? '[]', true);

        // Ensure at least one placeholder image if no images are uploaded
        if (empty($shop['images'])) {
            $shop['images'] = ['images/placeholder/coffee_shop_placeholder.jpg'];
        }

        // --- PERBAIKAN DI SINI: Mengambil nama edisi langsung ---
        // Jika edition_id ada dan nama edisi berhasil diambil dari tabel editions
        if (!empty($shop['edition_id']) && !empty($shop['edition_name'])) {
            $shop['editions_display'] = [$shop['edition_name']]; // Simpan dalam array untuk konsistensi dengan tampilan
        } else {
            $shop['editions_display'] = []; // Kosong jika tidak ada edisi
        }
        // Hapus $shop['editions'] dari sebelumnya jika tidak dipakai, atau ganti namanya
        unset($shop['edition_name']); // Hapus kolom yang tidak lagi dibutuhkan setelah diproses

        // Mengambil menu items
        $stmt_menu = $pdo->prepare("SELECT * FROM menu_items WHERE coffee_shop_id = ? ORDER BY category, name ASC");
        $stmt_menu->execute([$shopId]);
        $shop['menu_items'] = $stmt_menu->fetchAll(PDO::FETCH_ASSOC);
    }
}

if (!$shop) {
    header("Location: index.php?error=shop_not_found");
    exit();
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profil <?php echo htmlspecialchars($shop['name']); ?> - Spot Ngoffee</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700&family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-color: #5C4033; /* Dark Coffee Brown */
            --secondary-color: #D4A373; /* Golden Brown */
            --accent-color: #FDF7E4; /* Light Cream / Off-White */
            --light-brown-bg: #EEDDCC; /* Softer light brown background */
            --text-dark: #333;
            --text-light: #666;
            --border-light: #eee;
            --shadow-light: rgba(0, 0, 0, 0.08);
            --shadow-medium: rgba(0, 0, 0, 0.15);
            --shadow-strong: rgba(0, 0, 0, 0.25);
        }

        body {
            font-family: 'Poppins', sans-serif;
            margin: 0;
            padding: 0;
            background-color: var(--light-brown-bg);
            color: var(--text-dark);
            line-height: 1.6;
            overflow-x: hidden;
            display: flex;
            justify-content: center;
            align-items: flex-start;
            min-height: 100vh;
        }

        .container {
            max-width: 1000px;
            width: 95%;
            margin: 30px auto;
            padding: 30px;
            background-color: #fff;
            border-radius: 20px; /* Slightly more rounded */
            box-shadow: 0 10px 40px var(--shadow-medium); /* Stronger initial shadow */
            position: relative;
            box-sizing: border-box;
        }

        /* --- Back Button at Top --- */
        .back-button-top-container {
            text-align: left;
            margin-bottom: 25px; /* Increased margin */
        }

        .back-button {
            display: inline-flex;
            align-items: center;
            padding: 12px 25px; /* Slightly larger padding */
            background-color: var(--primary-color);
            color: white;
            text-decoration: none;
            border-radius: 30px; /* Pill shape */
            font-weight: 500;
            font-size: 1rem; /* Slightly larger font */
            transition: all 0.3s ease; /* Smooth transition for all properties */
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.15); /* Improved shadow */
        }

        .back-button i {
            margin-right: 10px; /* Increased margin */
            font-size: 1.1rem; /* Slightly larger icon */
        }

        .back-button:hover {
            background-color: #4A3329;
            transform: translateY(-3px); /* More pronounced lift */
            box-shadow: 0 8px 20px var(--shadow-medium); /* Stronger hover shadow */
        }

        /* --- Hero Section --- */
        .hero-section {
            text-align: center;
            margin-bottom: 30px; /* Increased margin */
        }

        .hero-section h1 {
            font-family: 'Playfair Display', serif;
            color: var(--primary-color);
            font-size: 4rem; /* Larger title */
            margin-bottom: 8px; /* Adjusted margin */
            line-height: 1.1;
            text-shadow: 1px 1px 2px rgba(0, 0, 0, 0.05); /* Subtle text shadow */
        }

        .hero-section .location {
            color: var(--text-light);
            font-size: 1.3rem; /* Larger font */
            margin-top: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 400;
        }

        .hero-section .location i {
            margin-right: 10px; /* Increased margin */
            color: var(--secondary-color);
            font-size: 1.4rem; /* Larger icon */
        }

        /* --- External Links Container --- */
        .external-links-container {
            display: flex;
            flex-wrap: wrap;
            justify-content: center;
            gap: 20px; /* Increased gap */
            margin: 25px 0 50px; /* Adjusted margins */
        }

        .external-link-item {
            display: inline-flex;
            align-items: center;
            padding: 15px 30px; /* Larger padding */
            border-radius: 35px; /* More pill-like */
            text-decoration: none;
            font-weight: 600;
            font-size: 1.05rem; /* Slightly larger font */
            transition: all 0.3s ease;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.12); /* Softer, wider shadow */
        }

        .external-link-item i {
            margin-right: 12px; /* Increased margin */
            font-size: 1.4rem; /* Larger icon */
        }

        .external-link-item.google-maps {
            background-color: #DB4437; /* Google Maps Red */
            color: white;
        }

        .external-link-item.google-maps:hover {
            background-color: #C23321;
            transform: translateY(-4px); /* More pronounced lift */
            box-shadow: 0 8px 18px rgba(0, 0, 0, 0.25); /* Stronger hover shadow */
        }

        .external-link-item.instagram {
            background: linear-gradient(45deg, #f09433 0%, #e6683c 25%, #dc2743 50%, #cc2366 75%, #bc1888 100%); /* Instagram gradient */
            color: white;
        }

        .external-link-item.instagram:hover {
            transform: translateY(-4px); /* More pronounced lift */
            box-shadow: 0 8px 18px rgba(0, 0, 0, 0.25); /* Stronger hover shadow */
            filter: brightness(1.05); /* Subtle brightness change on hover */
        }

        .no-external-links-message {
            color: var(--text-light);
            font-style: italic;
            text-align: center;
            margin: 25px 0;
            padding: 15px;
            border: 2px dashed var(--border-light); /* Thicker dashed border */
            border-radius: 10px;
            background-color: var(--accent-color); /* Light background for message */
            font-size: 0.95rem;
        }

        /* --- Image Gallery (Horizontal Scroll) --- */
        .image-gallery-wrapper {
            position: relative;
            width: 100%;
            height: 650px; /* Adjusted height for a more balanced look */
            margin-top: 40px; /* Increased margin */
            border-radius: 15px; /* Slightly more rounded */
            box-shadow: 0 6px 25px var(--shadow-strong); /* Stronger shadow */
            background-color: var(--border-light);
            overflow: hidden;
        }

        .image-gallery {
            display: flex;
            height: 100%;
            overflow-x: scroll;
            scroll-snap-type: x mandatory;
            -webkit-overflow-scrolling: touch;
            scrollbar-width: none;
            -ms-overflow-style: none;
        }

        .image-gallery::-webkit-scrollbar {
            display: none;
        }

        .image-gallery img {
            flex-shrink: 0;
            width: 100%;
            height: 100%;
            object-fit: cover;
            scroll-snap-align: center;
            transition: transform 0.4s ease-in-out; /* Slower, smoother transition */
            border-radius: 15px; /* Match wrapper border-radius */
        }

        .slider-navigation {
            position: absolute;
            top: 50%;
            width: 100%;
            display: flex;
            justify-content: space-between;
            transform: translateY(-50%);
            padding: 0 20px; /* Increased padding */
            box-sizing: border-box;
            z-index: 10;
        }

        .slider-navigation button {
            background-color: rgba(92, 64, 51, 0.8); /* Slightly more opaque */
            color: white;
            border: none;
            border-radius: 50%;
            width: 55px; /* Larger buttons */
            height: 55px; /* Larger buttons */
            font-size: 1.8rem; /* Larger icon */
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: background-color 0.3s ease, transform 0.2s ease, box-shadow 0.3s ease;
            box-shadow: 0 3px 8px var(--shadow-medium); /* Improved shadow */
        }

        .slider-navigation button:hover {
            background-color: var(--primary-color);
            transform: scale(1.1); /* More pronounced scale */
            box-shadow: 0 5px 12px var(--shadow-strong);
        }

        .image-gallery-placeholder {
            height: 100%;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            color: var(--text-light);
            font-size: 1.3rem; /* Larger font */
            text-align: center;
            background-color: var(--accent-color); /* Add a background */
            border-radius: 15px;
        }
        .image-gallery-placeholder i {
            margin-bottom: 20px; /* Increased margin */
            font-size: 4rem; /* Larger icon */
            color: var(--secondary-color);
        }


        /* --- Details Sections --- */
        .details-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); /* Adjusted minmax for better layout */
            gap: 30px; /* Increased gap */
            margin-top: 50px; /* Increased margin */
        }

        .info-card, .menu-section {
            background-color: var(--accent-color);
            padding: 35px; /* Increased padding */
            border-radius: 15px; /* More rounded */
            box-shadow: 0 4px 18px var(--shadow-light); /* Softer, wider shadow */
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }

        .info-card:hover {
            transform: translateY(-8px); /* More pronounced lift */
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.15); /* Stronger hover shadow */
        }

        .info-card h2, .menu-section h2 {
            font-family: 'Playfair Display', serif;
            color: var(--primary-color);
            font-size: 2.5rem; /* Larger headings */
            margin-top: 0;
            margin-bottom: 25px; /* Increased margin */
            padding-bottom: 12px; /* Adjusted padding */
            border-bottom: 3px solid var(--secondary-color); /* Thicker border */
            display: flex;
            align-items: center;
            letter-spacing: 0.5px; /* Subtle letter spacing */
        }

        .info-card h2 i, .menu-section h2 i {
            margin-right: 15px; /* Increased margin */
            color: var(--secondary-color);
            font-size: 2rem; /* Larger icon */
        }

        .info-card p {
            margin-bottom: 18px; /* Increased margin */
            color: var(--text-light);
            font-size: 1.05rem; /* Slightly larger text */
        }

        .info-card p strong {
            color: var(--primary-color);
            font-weight: 600; /* Bolder strong text */
        }

        .icon-text {
            display: flex;
            align-items: flex-start;
            margin-bottom: 15px; /* Increased margin */
            font-size: 1.1rem; /* Slightly larger font */
            color: var(--text-dark); /* Darker text for clarity */
        }

        .icon-text i {
            margin-right: 15px; /* Increased margin */
            color: var(--secondary-color);
            font-size: 1.4rem; /* Larger icon */
            padding-top: 3px;
        }

        .info-card ul {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .info-card ul li {
            margin-bottom: 10px; /* Increased margin */
            color: var(--text-dark); /* Darker text */
            display: flex;
            align-items: center;
            font-size: 1.05rem;
        }

        .info-card ul li i {
            margin-right: 12px; /* Increased margin */
            color: var(--secondary-color);
            font-size: 1.2rem;
        }

        .no-links-message {
            color: var(--text-light);
            font-style: italic;
            text-align: center;
            margin-top: 15px;
            font-size: 0.95rem;
        }


        /* --- Menu Section --- */
        .menu-section {
            grid-column: 1 / -1;
            margin-top: 50px;
            text-align: center;
            padding-top: 40px;
            padding-bottom: 40px;
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.1); /* Distinct shadow */
        }
        .menu-section h2 {
            justify-content: center;
        }

        .menu-categories {
            margin-top: 40px; /* Increased margin */
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 30px; /* Increased gap */
        }

        .menu-category-card {
            background-color: #fff;
            padding: 30px; /* Increased padding */
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.08); /* Softer shadow */
            border: 1px solid var(--border-light);
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }

        .menu-category-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.12);
        }

        .menu-category-card h3 {
            font-family: 'Poppins', sans-serif;
            color: var(--primary-color);
            font-size: 1.8rem; /* Larger heading */
            margin-top: 0;
            margin-bottom: 25px; /* Increased margin */
            border-bottom: 2px dashed var(--secondary-color); /* Thicker dashed border */
            padding-bottom: 12px;
            text-align: left;
            font-weight: 700; /* Bolder font */
        }

        .menu-item {
            display: flex;
            flex-direction: column;
            margin-bottom: 20px; /* Increased margin */
            padding-bottom: 15px; /* Increased padding */
            border-bottom: 1px dotted var(--border-light);
        }

        .menu-item:last-child {
            border-bottom: none;
            margin-bottom: 0;
            padding-bottom: 0;
        }

        .menu-item-header {
            display: flex;
            justify-content: space-between;
            align-items: baseline;
            margin-bottom: 8px; /* Adjusted margin */
        }

        .menu-item-name {
            font-weight: 600;
            color: var(--text-dark);
            font-size: 1.15rem; /* Slightly larger */
            flex-grow: 1;
            margin-right: 20px; /* Increased margin */
        }

        .menu-item-price {
            font-weight: 700;
            color: var(--secondary-color);
            font-size: 1.15rem; /* Slightly larger */
            white-space: nowrap;
        }

        .menu-item-description {
            font-size: 0.95rem; /* Slightly larger */
            color: var(--text-light);
            text-align: left;
            margin-top: 5px;
        }

        /* --- Responsive Design --- */
        @media (max-width: 992px) {
            .container {
                margin: 20px auto;
                padding: 25px;
            }
            .hero-section h1 {
                font-size: 3.5rem;
            }
            .image-gallery-wrapper {
                height: 550px;
            }
            .external-links-container {
                gap: 15px;
            }
            .external-link-item {
                padding: 12px 25px;
                font-size: 1rem;
            }
            .external-link-item i {
                font-size: 1.3rem;
            }
            .info-card h2, .menu-section h2 {
                font-size: 2.2rem;
            }
        }

        @media (max-width: 768px) {
            .container {
                margin: 15px auto;
                padding: 20px;
            }
            .hero-section h1 {
                font-size: 2.8rem;
            }
            .hero-section .location {
                font-size: 1.1rem;
            }
            .image-gallery-wrapper {
                height: 450px;
            }
            .slider-navigation button {
                width: 48px;
                height: 48px;
                font-size: 1.6rem;
            }
            .details-grid {
                grid-template-columns: 1fr;
                gap: 25px;
            }
            .info-card h2, .menu-section h2 {
                font-size: 2rem;
            }
            .info-card h2 i, .menu-section h2 i {
                font-size: 1.8rem;
            }
            .info-card p, .info-card ul li, .icon-text {
                font-size: 1rem;
            }
            .menu-category-card {
                padding: 25px;
            }
            .menu-category-card h3 {
                font-size: 1.6rem;
            }
            .menu-item-name, .menu-item-price {
                font-size: 1.05rem;
            }
            .menu-item-description {
                font-size: 0.9rem;
            }
            .back-button {
                padding: 10px 20px;
                font-size: 0.95rem;
            }
        }

        @media (max-width: 480px) {
            .container {
                padding: 15px;
                margin: 10px auto;
            }
            .hero-section h1 {
                font-size: 2.2rem;
            }
            .hero-section .location {
                font-size: 1rem;
            }
            .image-gallery-wrapper {
                height: 350px;
            }
            .slider-navigation button {
                width: 40px;
                height: 40px;
                font-size: 1.3rem;
                padding: 0 10px;
            }
            .info-card h2, .menu-section h2 {
                font-size: 1.7rem;
            }
            .info-card h2 i, .menu-section h2 i {
                font-size: 1.5rem;
            }
            .external-link-item {
                width: 95%; /* Make them almost full width */
                justify-content: center;
                padding: 12px 15px;
                font-size: 0.95rem;
            }
            .menu-category-card {
                padding: 20px;
            }
            .menu-category-card h3 {
                font-size: 1.4rem;
            }
            .menu-item-name, .menu-item-price {
                font-size: 1rem;
            }
            .menu-item-description {
                font-size: 0.85rem;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="back-button-top-container">
            <a href="index.php" class="back-button">
                <i class="fas fa-arrow-left"></i> Kembali ke Halaman Utama
            </a>
        </div>

        <div class="hero-section">
            <h1><?php echo htmlspecialchars($shop['name']); ?></h1>
            <p class="location"><i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($shop['location']); ?>, <?php echo htmlspecialchars($shop['region'] ?? 'Bekasi'); ?></p>
        </div>

        <div class="external-links-container">
            <?php if (!empty($shop['maps_url'])): ?>
                <a href="<?php echo htmlspecialchars($shop['maps_url']); ?>" target="_blank" title="Buka di Google Maps" class="external-link-item google-maps">
                    <i class="fas fa-map-marked-alt"></i>
                    Akses Google Maps
                </a>
            <?php endif; ?>
            <?php if (!empty($shop['instagram'])): ?>
                <a href="<?php echo htmlspecialchars($shop['instagram']); ?>" target="_blank" title="Kunjungi Instagram" class="external-link-item instagram">
                    <i class="fab fa-instagram"></i>
                    Kunjungi Instagram
                </a>
            <?php endif; ?>
            <?php if (empty($shop['maps_url']) && empty($shop['instagram'])): ?>
                <p class="no-external-links-message">Tidak ada tautan Google Maps atau Instagram yang tersedia.</p>
            <?php endif; ?>
        </div>

        <div class="image-gallery-wrapper">
            <div class="image-gallery" id="shopImageSlider">
                <?php if (!empty($shop['images'])): ?>
                    <?php foreach ($shop['images'] as $index => $image_url): ?>
                        <img src="./<?php echo htmlspecialchars($image_url); ?>" alt="<?php echo htmlspecialchars($shop['name']) . ' Image ' . ($index + 1); ?>">
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="image-gallery-placeholder">
                        <i class="fas fa-image"></i>
                        Gambar tidak tersedia
                    </div>
                <?php endif; ?>
            </div>
            <?php if (count($shop['images']) > 1): ?>
                <div class="slider-navigation">
                    <button id="prevSlide"><i class="fas fa-chevron-left"></i></button>
                    <button id="nextSlide"><i class="fas fa-chevron-right"></i></button>
                </div>
            <?php endif; ?>
        </div>

        <div class="details-grid">
            <div class="info-card">
                <h2><i class="fas fa-info-circle"></i> Tentang Kami</h2>
                <p><?php echo nl2br(htmlspecialchars($shop['description'] ?? 'Tidak ada deskripsi yang tersedia untuk kedai kopi ini.')); ?></p>
            </div>

            <div class="info-card">
                <h2><i class="fas fa-clock"></i> Jam & Harga</h2>
                <div class="icon-text">
                    <i class="fas fa-hourglass-start"></i>
                    <p><strong>Buka:</strong> <?php echo htmlspecialchars(substr($shop['open_hour'] ?? '00:00:00', 0, 5)); ?></p>
                </div>
                <div class="icon-text">
                    <i class="fas fa-hourglass-end"></i>
                    <p><strong>Tutup:</strong> <?php echo htmlspecialchars(substr($shop['close_hour'] ?? '00:00:00', 0, 5)); ?></p>
                </div>
                <div class="icon-text">
                    <i class="fas fa-money-bill-wave"></i>
                    <p><strong>Estimasi Harga Per orang:</strong> Rp<?php echo number_format($shop['min_price'] ?? 0, 0, ',', '.'); ?> - Rp<?php echo number_format($shop['max_price'] ?? 0, 0, ',', '.'); ?></p>
                </div>
            </div>

            <div class="info-card">
                <h2><i class="fas fa-tags"></i> Edisi</h2>
                <?php if (!empty($shop['editions_display'])): ?>
                    <ul>
                        <?php foreach ($shop['editions_display'] as $edition_name): ?>
                            <li><i class="fas fa-tag"></i> <?php echo htmlspecialchars($edition_name); ?></li>
                        <?php endforeach; ?>
                    </ul>
                <?php else: ?>
                    <p>Kedai kopi ini belum memiliki edisi khusus.</p>
                <?php endif; ?>
            </div>
        </div>

        <div class="menu-section">
            <h2><i class="fas fa-utensils"></i> Menu Rekomendasi</h2>
            <?php if (!empty($shop['menu_items'])): ?>
                <div class="menu-categories">
                    <?php
                    $menu_by_category = [];
                    foreach ($shop['menu_items'] as $item) {
                        $category = htmlspecialchars($item['category'] ?? 'Lain-lain');
                        if (!isset($menu_by_category[$category])) {
                            $menu_by_category[$category] = [];
                        }
                        $menu_by_category[$category][] = $item;
                    }
                    ?>
                    <?php foreach ($menu_by_category as $category_name => $items_in_category): ?>
                        <div class="menu-category-card">
                            <h3><?php echo $category_name; ?></h3>
                            <?php foreach ($items_in_category as $item): ?>
                                <div class="menu-item">
                                    <div class="menu-item-header">
                                        <span class="menu-item-name"><?php echo htmlspecialchars($item['name']); ?></span>
                                        <span class="menu-item-price">Rp<?php echo number_format($item['price'], 0, ',', '.'); ?></span>
                                    </div>
                                    <?php if (!empty($item['description'])): ?>
                                        <span class="menu-item-description"><?php echo htmlspecialchars($item['description']); ?></span>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <p style="text-align: center; color: var(--text-light); font-style: italic; margin-top: 20px;">Belum ada item menu yang ditambahkan untuk kedai kopi ini.</p>
            <?php endif; ?>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const gallery = document.getElementById('shopImageSlider');
            const prevBtn = document.getElementById('prevSlide');
            const nextBtn = document.getElementById('nextSlide');
            const images = gallery.querySelectorAll('img');

            if (images.length === 0) {
                if (prevBtn) prevBtn.style.display = 'none';
                if (nextBtn) nextBtn.style.display = 'none';
                return;
            }

            let currentIndex = 0;
            const scrollAmount = gallery.clientWidth;

            const scrollToImage = (index) => {
                if (images[index]) {
                    gallery.scrollTo({
                        left: images[index].offsetLeft,
                        behavior: 'smooth'
                    });
                }
            };

            const showNextSlide = () => {
                currentIndex = (currentIndex + 1) % images.length;
                scrollToImage(currentIndex);
            };

            const showPrevSlide = () => {
                currentIndex = (currentIndex - 1 + images.length) % images.length;
                scrollToImage(currentIndex);
            };

            if (prevBtn) {
                prevBtn.addEventListener('click', showPrevSlide);
            }
            if (nextBtn) {
                nextBtn.addEventListener('click', showNextSlide);
            }

            let slideInterval;
            const startAutoSlide = () => {
                if (images.length > 1) {
                    clearInterval(slideInterval);
                    slideInterval = setInterval(showNextSlide, 4000);
                }
            };

            const stopAutoSlide = () => {
                clearInterval(slideInterval);
            };

            startAutoSlide();

            gallery.parentElement.addEventListener('mouseenter', stopAutoSlide);
            gallery.parentElement.addEventListener('mouseleave', startAutoSlide);

            gallery.addEventListener('scroll', () => {
                const scrollLeft = gallery.scrollLeft;
                const imageWidth = gallery.clientWidth;
                currentIndex = Math.round(scrollLeft / imageWidth);
            });
        });
    </script>
</body>
</html>