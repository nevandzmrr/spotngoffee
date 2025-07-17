<?php
// Mulai sesi PHP
session_start();

// Memuat file konfigurasi database
require_once __DIR__ . '/config/db.php';

// --- Ambil Data Global (akan digunakan di berbagai halaman) ---

// Inisialisasi klausa WHERE dan parameter query
$whereClauses = [];
$queryParams = [];

// Tangani filter Region dari URL
if (isset($_GET['region']) && $_GET['region'] !== '') {
    $selectedRegion = $_GET['region'];
    $whereClauses[] = "cs.region = ?";
    $queryParams[] = $selectedRegion;
}

// Tangani filter Edition dari URL
// Penting: Pastikan 'value' dari option di HTML filter edisi adalah ID edisi
if (isset($_GET['edition']) && $_GET['edition'] !== '') {
    $selectedEditionId = $_GET['edition'];
    $whereClauses[] = "cs.edition_id = ?"; // Filter berdasarkan edition_id di tabel coffee_shops
    $queryParams[] = $selectedEditionId;
}

// Bangun query SQL utama untuk coffee_shops
$sql = "SELECT cs.*, e.name AS edition_name FROM coffee_shops cs LEFT JOIN editions e ON cs.edition_id = e.id";

if (!empty($whereClauses)) {
    $sql .= " WHERE " . implode(" AND ", $whereClauses);
}

// Mengubah urutan pengurutan menjadi berdasarkan nama secara abjad
$sql .= " ORDER BY cs.name ASC";

// Eksekusi query dengan prepared statement untuk keamanan
$stmtAllCoffeeShops = $pdo->prepare($sql);
$stmtAllCoffeeShops->execute($queryParams);
$allCoffeeShops = $stmtAllCoffeeShops->fetchAll(PDO::FETCH_ASSOC);

// Mengubah string JSON gambar menjadi array PHP untuk setiap coffee shop
foreach ($allCoffeeShops as $key => $shop) {
    if (isset($shop['images'])) {
        $allCoffeeShops[$key]['images'] = json_decode($shop['images'], true);
    } else {
        $allCoffeeShops[$key]['images'] = [];
    }
    if (isset($shop['menu_items'])) {
        $allCoffeeShops[$key]['menu_items'] = json_decode($shop['menu_items'], true);
    } else {
        $allCoffeeShops[$key]['menu_items'] = [];
    }
}

/// --- Ambil Data Edisi dari Database ---
$editionsStmt = $pdo->query("SELECT id, name FROM editions ORDER BY name ASC");
$editionsFromDb = $editionsStmt->fetchAll(PDO::FETCH_ASSOC);

// Mengubah format $editions menjadi array string nama saja untuk kemudahan di JS awal
$editions = array_column($editionsFromDb, 'name');

// ... (bagian rekomendasi yang sudah diubah sebelumnya) ...
$recommendations = [];
foreach ($editionsFromDb as $edData) {
    $editionName = $edData['name'];
    $editionId = $edData['id'];

    $recList = [];
    $stmt = $pdo->prepare("
        SELECT cs.*
        FROM coffee_shops cs
        JOIN recommendations r ON cs.id = r.coffee_shop_id
        WHERE r.edition_id = :edition_id
        ORDER BY RAND() LIMIT 4
    ");
    $stmt->execute([':edition_id' => $editionId]);
    $recList = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($recList as $idx => $recShop) {
        if (isset($recShop['images'])) {
            $recList[$idx]['images'] = json_decode($recShop['images'], true);
        } else {
            $recList[$idx]['images'] = [];
        }
        if (isset($recShop['menu_items'])) {
            $recList[$idx]['menu_items'] = json_decode($recShop['menu_items'], true);
        } else {
            $recList[$idx]['menu_items'] = [];
        }
    }
    $recommendations[$editionName] = $recList;
}

// ... di dalam loop foreach ($allCoffeeShops as $key => $shop) {
    // Memproses 'menu_items' (tetap asumsi JSON di tabel coffee_shops)
    if (isset($shop['menu_items']) && is_string($shop['menu_items'])) {
        $allCoffeeShops[$key]['menu_items'] = json_decode($shop['menu_items'], true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            // Handle error jika JSON tidak valid
            $allCoffeeShops[$key]['menu_items'] = [];
        }
    } else {
        $allCoffeeShops[$key]['menu_items'] = [];
    }


// Mengambil semua region unik dari database (untuk filter)
$regionsStmt = $pdo->query("SELECT DISTINCT region FROM coffee_shops WHERE region IS NOT NULL AND region != '' ORDER BY region ASC");
$regions = $regionsStmt->fetchAll(PDO::FETCH_COLUMN);
// Pastikan Bekasi sebagai default jika tidak ada di DB (sesuaikan dengan wilayah yang relevan)
if (!in_array('Bekasi Barat', $regions)) array_push($regions, 'Bekasi Barat');
if (!in_array('Bekasi Timur', $regions)) array_push($regions, 'Bekasi Timur');
if (!in_array('Bekasi Selatan', $regions)) array_push($regions, 'Bekasi Selatan');
if (!in_array('Bekasi Utara', $regions)) array_push($regions, 'Bekasi Utara');
sort($regions); // Urutkan lagi setelah penambahan manual

// Mengambil semua edisi unik dari database (untuk filter)
// Bagian ini menghasilkan $names yang sepertinya untuk JS filter saja, tapi tidak dipakai di PHP filter
$namesStmt = $pdo->query("SELECT DISTINCT name FROM editions WHERE name IS NOT NULL AND name != '' ORDER BY name ASC");
$names = $namesStmt->fetchAll(PDO::FETCH_COLUMN);
if (!in_array('Nongkrong', $names)) array_push($names, 'Nongkrong');
if (!in_array('Nugas', $names)) array_push($names, 'Nugas');
if (!in_array('Kantong Tipis', $names)) array_push($names, 'Kantong Tipis');
sort($names);

// Data PHP yang akan dilewatkan ke JavaScript
$jsAllCoffeeShops = json_encode($allCoffeeShops);
$jsRecommendations = json_encode($recommendations);
$jsRegions = json_encode($regions);
$jsEditions = json_encode($editions); // Kirim daftar nama edisi saja untuk JS filter
$jsEditionsWithId = json_encode($editionsFromDb); // Kirim edisi dengan ID jika diperlukan di JS

?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Spot Ngoffee - Rekomendasi Spot Bekasi</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;600;700&family=Playfair+Display:wght@400;700&display=swap" rel="stylesheet">
  <style>
        /*
        -------------------------------------------------------------
        CSS KESELURUHAN & TEMA
        -------------------------------------------------------------
        */

        /* Palet Warna Kopi (Coklat Memanjakan Mata) */
        :root {
            --color-coffee-dark: #4A2C2A; /* Coklat Tua, mirip kopi hitam */
            --color-coffee-medium: #8B4513; /* Coklat Sedang, mirip biji kopi */
            --color-coffee-light: #D2B48C; /* Coklat Muda, mirip susu di kopi */
            --color-cream: #F5E8C7; /* Warna krem lembut */
            --color-text-dark: #333;
            --color-text-light: #f8f9fa;
            --color-accent-blue: #007bff; /* Biru sebagai aksen */
            --color-accent-green: #28a745; /* Hijau untuk aksi positif */
            --color-shadow: rgba(0, 0, 0, 0.1);
        }

        /* Reset & Gaya Dasar */
        *, *::before, *::after {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Montserrat', sans-serif; /* Font utama */
            line-height: 1.6;
            color: var(--color-text-dark);
            background-color: var(--color-cream); /* Latar belakang krem */
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            overflow-x: hidden; /* Hindari scroll horizontal yang tidak diinginkan */
        }

        a {
            text-decoration: none;
            color: var(--color-accent-blue);
            transition: color 0.3s ease;
        }

        a:hover {
            color: var(--color-coffee-medium);
        }

        /* Container Utama */
        .container {
            max-width: 1300px; /* Lebar maksimum lebih besar */
            margin: 0 auto;
            padding: 1.5rem 1rem; /* Padding atas-bawah dan samping */
            width: 100%;
            flex-grow: 1;
        }

        /* Header / Navigasi */
        header {
            background-color: var(--color-coffee-dark); /* Header warna kopi tua */
            padding: 1rem 1.5rem;
            box-shadow: 0 4px 10px var(--color-shadow);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 1rem;
        }

        .logo {
            font-family: 'Playfair Display', serif; /* Font elegan untuk logo */
            font-size: 2.2rem;
            font-weight: bold;
            color: var(--color-coffee-light); /* Warna kontras untuk logo */
            text-decoration: none;
            flex-shrink: 0;
        }

        .nav-buttons {
            display: flex;
            gap: 1rem;
            flex-wrap: wrap; /* Izinkan tombol membungkus */
        }

        .nav-button {
            background-color: var(--color-coffee-medium);
            color: var(--color-text-light);
            border: none;
            padding: 0.75rem 1.2rem;
            border-radius: 25px; /* Tombol bulat */
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
            box-shadow: inset 0 2px 4px rgba(0, 0, 0, 0.3);
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
            position: relative; /* Untuk posisi ikon kunci */
        }
        .admin-login-button:hover {
            background-color: #0056b3;
            transform: translateY(-2px);
        }
        .admin-login-button i {
            font-size: 1.1rem;
        }

        /* Judul Seksi Umum */
        h2.section-title {
            text-align: center;
            margin: 2.5rem 0 2rem;
            font-size: 2.4rem;
            color: var(--color-coffee-dark);
            position: relative;
            padding-bottom: 0.8rem;
            font-family: 'Playfair Display', serif;
        }

        h2.section-title::after {
            content: '';
            position: absolute;
            left: 50%;
            bottom: 0;
            transform: translateX(-50%);
            width: 100px;
            height: 5px;
            background-color: var(--color-coffee-medium);
            border-radius: 3px;
        }

        /*
        -------------------------------------------------------------
        GAYA ELEMEN UMUM (Search Bar, Filter, Cards)
        -------------------------------------------------------------
        */
        .search-filter-section {
            background-color: #fff;
            padding: 1.5rem;
            border-radius: 12px;
            box-shadow: 0 4px 10px var(--color-shadow);
            margin-bottom: 1.5rem;
            border: 1px solid var(--color-coffee-light);
        }

        .search-bar-group {
            display: flex;
            gap: 0.75rem;
            margin-bottom: 1rem;
            flex-wrap: wrap;
            position: relative; /* Untuk autosuggest */
        }

        .search-bar-group input[type="text"] {
            flex-grow: 1;
            padding: 0.8rem;
            border: 1px solid var(--color-coffee-light);
            border-radius: 8px;
            font-size: 1rem;
            min-width: 180px;
            background-color: var(--color-cream);
        }

        .search-bar-group button {
            padding: 0.8rem 1.5rem;
            background-color: var(--color-accent-green);
            color: white;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 1rem;
            transition: background-color 0.3s ease;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .search-bar-group button:hover {
            background-color: #1e7e34;
        }

        .filter-group {
            display: flex;
            gap: 0.75rem;
            flex-wrap: wrap;
            justify-content: center;
        }

        .filter-group select {
            padding: 0.8rem;
            border: 1px solid var(--color-coffee-light);
            border-radius: 8px;
            font-size: 1rem;
            background-color: var(--color-cream);
            flex-grow: 1;
            min-width: 140px;
        }

        .filter-group button {
            padding: 0.8rem 1.5rem;
            background-color: var(--color-coffee-medium);
            color: white;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 1rem;
            transition: background-color 0.3s ease;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .filter-group button:hover {
            background-color: var(--color-coffee-dark);
        }

        /* Autocomplete / Auto-suggest */
        .autocomplete-suggestions {
            position: absolute;
            top: 100%; /* Di bawah input */
            left: 0;
            right: 0;
            background-color: white;
            border: 1px solid var(--color-coffee-light);
            border-top: none;
            max-height: 200px;
            overflow-y: auto;
            z-index: 10;
            box-shadow: 0 4px 8px var(--color-shadow);
            border-bottom-left-radius: 8px;
            border-bottom-right-radius: 8px;
        }

        .autocomplete-suggestion-item {
            padding: 0.8rem 1rem;
            cursor: pointer;
            border-bottom: 1px solid #eee;
            color: var(--color-text-dark);
        }

        .autocomplete-suggestion-item:last-child {
            border-bottom: none;
        }

        .autocomplete-suggestion-item:hover, .autocomplete-suggestion-item.active {
            background-color: var(--color-coffee-light);
            color: var(--color-coffee-dark);
        }


        /* Daftar Kartu Tempat (Coffee Shops) */
        .place-card-grid {
            display: grid;
            gap: 2rem; /* Jarak antar kartu lebih besar */
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); /* Mobile 1 kolom, PC banyak kolom */
            padding-bottom: 2rem;
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
            transform: translateY(-8px);
            box-shadow: 0 12px 25px rgba(0, 0, 0, 0.2);
        }

        .place-image-wrapper {
            position: relative;
            width: 100%;
            padding-bottom: 60%; /* Aspect ratio 5:3 (untuk gambar) */
            overflow: hidden;
            background-color: #f0f0f0;
        }

        .place-image {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
            border-bottom: 1px solid #eee;
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

         .place-price, .place-region {
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
        .place-price:hover, .place-region:hover {
            transform: translateY(-3px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
        }
        /* Warna spesifik untuk tag Edisi */
        .place-price {
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

        /* Page Section (Hidden by default) */
        .page-section {
            display: none; /* Default hidden */
            animation: fadeIn 0.5s ease-out; /* Animasi saat muncul */
        }

        .page-section.active {
            display: block; /* Active page */
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /*
        -------------------------------------------------------------
        GAYA SPESIFIK UNTUK MASING-MASING HALAMAN
        -------------------------------------------------------------
        */

        /* Home Page */
        .intro-section {
            background: linear-gradient(135deg, var(--color-coffee-dark), var(--color-coffee-medium));
            color: var(--color-text-light);
            padding: 3rem 2rem;
            border-radius: 15px;
            text-align: center;
            margin-bottom: 3rem;
            position: relative;
            overflow: hidden;
            box-shadow: 0 8px 20px rgba(0,0,0,0.3);
            border: 2px solid var(--color-coffee-light);
        }
        .intro-section::before {
            content: url('https://raw.githubusercontent.com/Anandax24/SpotNgoffee/main/images/coffee-cup-icon.png'); /* Ganti dengan path ikon Anda */
            position: absolute;
            top: 20px;
            left: 20px;
            opacity: 0.1;
            font-size: 8rem;
            line-height: 0;
            transform: rotate(-15deg);
        }
        .intro-section::after {
            content: url('https://raw.githubusercontent.com/Anandax24/SpotNgoffee/main/images/coffee-bean-icon.png'); /* Ganti dengan path ikon Anda */
            position: absolute;
            bottom: 20px;
            right: 20px;
            opacity: 0.1;
            font-size: 8rem;
            line-height: 0;
            transform: rotate(15deg);
        }
        .intro-section h1 {
            font-family: 'Playfair Display', serif;
            font-size: 3.5rem;
            margin-bottom: 1rem;
            text-shadow: 2px 2px 5px rgba(0,0,0,0.3);
        }
        .intro-section p {
            font-size: 1.3rem;
            max-width: 800px;
            margin: 0 auto 1.5rem auto;
            line-height: 1.8;
        }

        /* Slider Rekomendasi Edisi (Home Page) */
        .edition-slider-container {
            position: relative;
            overflow: hidden;
            margin-bottom: 3rem;
            background-color: #fff;
            padding: 1.5rem;
            border-radius: 15px;
            box-shadow: 0 4px 15px var(--color-shadow);
            border: 1px solid var(--color-coffee-light);
        }
        .edition-tabs {
            display: flex;
            justify-content: center;
            gap: 1rem;
            margin-bottom: 1.5rem;
            flex-wrap: wrap;
        }
        .edition-tab-button {
            background-color: var(--color-cream);
            color: var(--color-coffee-dark);
            border: 1px solid var(--color-coffee-light);
            padding: 0.8rem 1.5rem;
            border-radius: 25px;
            cursor: pointer;
            font-size: 1.1rem;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        .edition-tab-button:hover {
            background-color: var(--color-coffee-light);
        }
        .edition-tab-button.active {
            background-color: var(--color-coffee-medium);
            color: white;
            border-color: var(--color-coffee-dark);
        }
        .edition-slides {
            display: flex;
            transition: transform 0.5s ease-in-out;
            gap: 1.5rem; /* Jarak antar slide (card) */
            padding-bottom: 1rem; /* Ruang untuk scrollbar */
            overflow-x: auto; /* Memungkinkan scroll samping */
            -webkit-overflow-scrolling: touch; /* Untuk iOS smooth scrolling */
            scroll-snap-type: x mandatory; /* Snap ke card saat scroll */
            scrollbar-width: thin; /* Firefox */
            scrollbar-color: var(--color-coffee-medium) var(--color-cream); /* Firefox */
        }
        .edition-slides::-webkit-scrollbar {
            height: 8px;
        }
        .edition-slides::-webkit-scrollbar-track {
            background: var(--color-cream);
            border-radius: 10px;
        }
        .edition-slides::-webkit-scrollbar-thumb {
            background: var(--color-coffee-light);
            border-radius: 10px;
        }
        .edition-slides::-webkit-scrollbar-thumb:hover {
            background: var(--color-coffee-medium);
        }

        .edition-slide {
            flex: 0 0 auto; /* Jangan shrink, jangan grow, tetap ukuran asli */
            width: 300px; /* Lebar setiap slide/card dalam slider */
            scroll-snap-align: start; /* Snap ke awal elemen */
        }
        /* Override gaya place-card di slider */
        .edition-slide.place-card {
            width: 300px;
        }

       /* Region Page (Landing page untuk Region) */
.region-card-grid {
    display: grid;
    gap: 1.8rem; /* Keep a decent gap between cards */
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); /* Adjusted min-width for potentially more columns on smaller screens */
    padding-bottom: 2rem;
    justify-content: center; /* Center the grid items if there's extra space */
    align-items: stretch; /* Ensure all cards have the same height */
}

/* Enhanced Region Card Styling */
.region-card {
    background-color: #fff;
    border-radius: 60px; /* Softer rounded corners */
    box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1); /* Lighter, more subtle shadow */
    padding: 2.5rem 1.5rem; /* Generous padding */
    text-align: center;
    color: var(--color-coffee-dark);
    text-decoration: none;
    transition: all 0.3s ease; /* Smooth transition for hover effects */
    display: flex; /* Use flexbox for vertical alignment of content */
    flex-direction: column;
    align-items: center;
    justify-content: center;
    min-height: 250px; /* Ensure a minimum height for uniformity */
    position: relative; /* For the subtle background pattern */
    overflow: hidden; /* Hide overflow for background pattern */
    border: 1px solid var(--color-coffee-light); /* Subtle border */
}

/* Subtle background pattern for region cards */
.region-card::before {
    content: '';
    position: absolute;
    top: -50%;
    left: -50%;
    width: 200%;
    height: 200%;
    background: radial-gradient(circle at center, var(--color-cream) 0%, rgba(255,255,255,0) 70%);
    opacity: 0;
    transition: opacity 0.4s ease;
    z-index: 0;
}


.region-card:hover {
    transform: translateY(-8px); /* Lift up on hover */
    box-shadow: 0 12px 25px rgba(0, 0, 0, 0.2); /* Stronger shadow on hover */
    background-color: var(--color-coffee-light); /* Light coffee background on hover */
    color: var(--color-coffee-dark); /* Darker text on hover */
}

.region-card:hover::before {
    opacity: 0.8; /* Make background pattern visible on hover */
}

.region-card i {
    font-size: 4rem; /* Larger icon */
    color: var(--color-coffee-medium); /* Coffee medium color for icon */
    margin-bottom: 1rem; /* Space between icon and title */
    transition: color 0.3s ease;
    position: relative;
    z-index: 1; /* Ensure icon is above pseudo-element */
}

.region-card h3 {
    font-family: 'Playfair Display', serif;
    font-size: 1.8rem; /* Clearer title */
    font-weight: 700;
    margin-bottom: 0;
    position: relative;
    z-index: 1; /* Ensure title is above pseudo-element */
}

/* Media Queries for Region Card Grid to optimize spacing */
@media (min-width: 480px) { /* On very small tablets/large phones */
    .region-card-grid {
        grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
    }
}

@media (min-width: 768px) {
    .region-card-grid {
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); /* 2-3 columns on tablets */
        gap: 2rem; /* Slightly larger gap on tablets */
    }
}

@media (min-width: 1024px) {
    .region-card-grid {
        grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); /* 3-4 columns on desktops */
        gap: 2.5rem; /* Larger gap on desktops */
    }
}

@media (min-width: 1440px) {
    .region-card-grid {
        grid-template-columns: repeat(4, 1fr); /* Force 4 columns for very large screens */
    }
}
        /* Media Queries untuk .region-card-grid */
        @media (min-width: 768px) {
            .region-card-grid {
                grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); /* 2-3 kolom di tablet */
            }
        }

        @media (min-width: 1024px) {
            .region-card-grid {
                grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); /* 3-4 kolom di desktop */
            }
        }

        @media (min-width: 1440px) {
            .region-card-grid {
                grid-template-columns: repeat(4, 1fr); /* Tetapkan 4 kolom untuk layar sangat besar */
            }
        }
        /* About Page */
        .about-content {
            background-color: #fff;
            padding: 2.5rem;
            border-radius: 15px;
            box-shadow: 0 6px 15px var(--color-shadow);
            border: 1px solid var(--color-coffee-light);
            max-width: 900px; /* Lebih lebar untuk desain baru */
            margin: 0 auto 3rem auto;
            text-align: center;
            display: flex;
            flex-direction: column;
            align-items: center;
        }
        .about-content h3 {
            font-family: 'Playfair Display', serif;
            font-size: 2rem;
            color: var(--color-coffee-dark);
            margin-top: 2rem;
            margin-bottom: 1.5rem;
        }
        .about-content p {
            font-size: 1.1rem;
            line-height: 1.8;
            margin-bottom: 1.5rem;
            color: #444;
            max-width: 700px;
        }
        .about-features {
            list-style: none;
            padding: 0;
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
            width: 100%;
        }
        .about-features li {
            background-color: var(--color-cream);
            padding: 1rem 1.5rem;
            border-radius: 10px;
            border: 1px solid var(--color-coffee-light);
            text-align: left;
            display: flex;
            align-items: center;
            gap: 0.8rem;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
        }
        .about-features li i {
            color: var(--color-coffee-medium);
            font-size: 1.5rem;
        }
        .about-features li span {
            font-weight: 600;
            color: var(--color-coffee-dark);
        }

        .contact-section {
            margin-top: 2.5rem;
            border-top: 1px dashed var(--color-coffee-light);
            padding-top: 2.5rem;
            width: 100%;
            max-width: 600px;
        }
        .contact-section h3 {
            margin-bottom: 1.5rem;
            color: var(--color-coffee-dark);
        }
        .social-icons {
            display: flex;
            justify-content: center;
            gap: 1.5rem;
            flex-wrap: wrap;
        }
        .social-icon-link {
            display: flex;
            flex-direction: column;
            align-items: center;
            color: var(--color-coffee-medium);
            font-size: 1.2rem;
            transition: transform 0.2s ease, color 0.3s ease;
        }
        .social-icon-link:hover {
            transform: translateY(-5px);
            color: var(--color-coffee-dark);
        }
        .social-icon-link i {
            font-size: 3rem; /* Ukuran ikon */
            margin-bottom: 0.5rem;
        }
        .social-icon-link span {
            font-size: 0.9rem;
            font-weight: 600;
        }


        /* Footer */
        footer {
            background-color: var(--color-coffee-dark);
            color: var(--color-text-light);
            text-align: center;
            padding: 2rem 1rem;
            margin-top: auto; /* Dorong footer ke bawah */
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
        MEDIA QUERIES (RESPONSIF)
        -------------------------------------------------------------
        */

        /* Untuk Tablet dan Layar Lebih Besar (min-width: 768px) */
        @media (min-width: 768px) {
            .container {
                padding: 2rem;
            }
            .logo {
                font-size: 2.5rem;
            }
            .nav-buttons {
                gap: 1.2rem;
            }
            .nav-button {
                padding: 0.8rem 1.3rem;
                font-size: 1rem;
            }
            .admin-login-button {
                padding: 0.8rem 1.3rem;
            }

            .intro-section h1 {
                font-size: 4.5rem;
            }
            .intro-section p {
                font-size: 1.4rem;
            }
            .intro-section::before, .intro-section::after {
                font-size: 10rem;
            }

            .search-bar-group {
                flex-wrap: nowrap;
            }
            .filter-group {
                justify-content: flex-start;
            }

            .place-card-grid {
                grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); /* 2-3 kolom */
            }

            .edition-slide {
                width: 320px; /* Lebar slide di tablet */
            }
            .edition-slides {
                justify-content: flex-start; /* Untuk slider, mulai dari kiri */
            }

            .search-results-main {
                flex-direction: row; /* Side-by-side on desktop */
                align-items: flex-start; /* Align ke atas */
            }
            .main-content-area {
                flex: 3; /* Ambil 3 bagian ruang */
            }
            .knowledge-sidebar {
                flex: 1; /* Ambil 1 bagian ruang */
                min-width: 280px; /* Lebar minimum sidebar */
            }

            .about-features {
                grid-template-columns: 1fr 1fr; /* 2 kolom di tablet */
            }
        }

        /* Untuk Laptop/PC dan Layar Lebih Besar (min-width: 1024px) */
        @media (min-width: 1024px) {
            .container {
                padding: 2.5rem;
            }
            header {
                padding: 1.5rem 2.5rem;
            }
            .logo {
                font-size: 3rem;
            }
            .nav-buttons {
                gap: 1.5rem;
            }
            .nav-button {
                padding: 0.9rem 1.5rem;
                font-size: 1.05rem;
            }
            .admin-login-button {
                padding: 0.9rem 1.5rem;
            }

            .intro-section h1 {
                font-size: 5.5rem;
            }
            .intro-section p {
                font-size: 1.5rem;
            }
            .intro-section::before, .intro-section::after {
                font-size: 12rem;
            }

            h2.section-title {
                font-size: 3rem;
            }

            .place-card-grid {
                grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); /* 3-4 kolom */
            }

            .edition-slide {
                width: 350px; /* Lebar slide di desktop */
            }

            .knowledge-sidebar {
                min-width: 350px; /* Lebih lebar di desktop */
            }
            .region-card-grid {
                grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); /* 3-4 kolom */
            }

            .about-features {
                grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); /* 2-3 kolom di desktop */
            }
        }

        /* Untuk Layar Sangat Besar (min-width: 1440px) */
        @media (min-width: 1440px) {
            .container {
                max-width: 1500px;
            }
            .place-card-grid {
                grid-template-columns: repeat(4, 1fr); /* 4 kolom */
            }
            .edition-slide {
                width: 380px;
            }
            .region-card-grid {
                grid-template-columns: repeat(4, 1fr); /* 4 kolom */
            }
        }
    </style>
</head>
<body>
    <header>
        <a href="#" class="logo">Spot Ngoffee</a>
        <div class="nav-buttons">
            <button id="homeBtn" class="nav-button active"><i class="fas fa-home"></i> Beranda</button>
            <button id="searchBtn" class="nav-button"><i class="fas fa-search"></i> Pencarian</button>
            <button id="regionBtn" class="nav-button"><i class="fas fa-map-marked-alt"></i> Wilayah</button>
            <button id="aboutBtn" class="nav-button"><i class="fas fa-info-circle"></i> Tentang Kami</button>
            <a href="admin/admin_login.php" target="_blank" class="admin-login-button">
                <i class="fas fa-key"></i> Admin
            </a>
        </div>
    </header>

    <main class="container">
        <section id="home-page" class="page-section active">
            <div class="intro-section">
                <h1>Temukan Spot Ngopi Edisi Lo!</h1>
                <p>"Nyari tempat ngopi di Bekasi  buat Nongkrong, Nugas, atau cari yang aman di kantong karena Kantong lagi Tipis? Tenang, Spot Ngoffee bakal ngasih referensi!"</p>
                <div class="search-bar-group">
                    <input type="text" id="homeSearchInput" placeholder="Cari coffee shop..." autocomplete="off">
                    <div id="homeSuggestions" class="autocomplete-suggestions"></div>
                    <button id="homeSearchButton"><i class="fas fa-search"></i> Cari</button>
                </div>
            </div>

            <h2 class="section-title">TOP REKOMENDASI SPOT NGOFFE</h2>
            <div class="edition-slider-container">
                <div class="edition-tabs">
                    <?php foreach ($editionsFromDb as $index => $edData): // Gunakan data edisi lengkap dari DB ?>
                        <button class="edition-tab-button <?php echo $index === 0 ? 'active' : ''; ?>" data-edition-id="<?php echo htmlspecialchars($edData['id']); ?>" data-edition-name="<?php echo htmlspecialchars($edData['name']); ?>">
                            <?php echo htmlspecialchars($edData['name']); ?>
                        </button>
                    <?php endforeach; ?>
                </div>
                <div id="editionSlides" class="edition-slides">
                    </div>
            </div>
        </section>

        <section id="search-page" class="page-section">
            <h2 class="section-title">CARI COFFEE SHOP</h2>

            <div class="search-filter-section">
                <div class="search-bar-group">
                    <input type="text" id="searchPageInput" placeholder="Cari nama CoffeeShops" autocomplete="off">
                    <div id="searchPageSuggestions" class="autocomplete-suggestions"></div>
                    <button id="searchPageSearchButton"><i class="fas fa-search"></i> Cari</button>
                </div>
                <div class="filter-group">
                    <select id="regionFilter">
                        <option value="">Semua Wilayah</option>
                        <?php foreach ($regions as $region): ?>
                            <option value="<?php echo htmlspecialchars($region); ?>"><?php echo htmlspecialchars($region); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <select id="editionFilter">
    <option value="">Semua Edisi</option>
    <?php foreach ($editionsFromDb as $edData): // Gunakan data edisi lengkap dari DB ?>
        <option value="<?php echo htmlspecialchars($edData['name']); ?>">
            <?php echo htmlspecialchars($edData['name']); ?>
        </option>
    <?php endforeach; ?>
</select>
                    <button id="resetSearchFiltersButton"><i class="fas fa-redo"></i> Reset Filter</button>
                </div>
            </div>

            <div class="search-results-main">
                <div class="main-content-area">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
                        <h3 style="font-size: 1.8rem; color: var(--color-coffee-dark); font-family: 'Playfair Display', serif;">Hasil Pencarian</h3>
                        <button id="refreshSearchButton" class="nav-button"><i class="fas fa-sync-alt"></i> Refresh List</button>
                    </div>
                    <div id="searchResultList" class="place-card-grid">
                        </div>
                    <p id="noSearchResultsMessage" class="hidden text-center" style="margin-top: 2rem; color: #666;"></p>
                </div>

                <aside class="knowledge-sidebar">
                    <h3>Pengetahuan Tentang Spot Ngoffee</h3>
                    <p id="knowledgeText">Pilih filter atau cari untuk menampilkan informasi lebih lanjut tentang wilayah dan edisi yang relevan.</p>
                </aside>
            </div>
        </section>

        <section id="region-page" class="page-section">
            <h2 class="section-title">Jelajahi Coffee Shop Berdasarkan Wilayah</h2>
            <div class="region-card-grid">
                <?php foreach ($regions as $region): ?>
                    <a href="region_detail.php?name=<?php echo urlencode($region); ?>" class="region-card">
                        <i class="fas fa-map-marker-alt"></i>
                        <h3><?php echo htmlspecialchars($region); ?></h3>
                    </a>
                <?php endforeach; ?>
            </div>
        </section>

        <section id="about-page" class="page-section">
            <h2 class="section-title">SPOT NGOFFEE</h2>
            <div class="about-content">
                <p>Selamat datang di <strong>Spot Ngoffee</strong>, platform yang didedikasikan untuk membantu Anda menemukan pengalaman kopi terbaik di sekitar Bekasi. Kami percaya bahwa setiap cangkir kopi memiliki cerita, dan setiap coffee shop memiliki keunikan tersendiri. Melalui edisi edisi yang telah kami buat semoga membantu pencarian coffee shop yang sesuai untuk kamu!</p>
                
                <h3>Apa yang Kami Tawarkan?</h3>
                <ul class="about-features">
                    <li><i class="fas fa-search"></i> <span>Pencarian Intuitif:</span> Temukan coffee shop berdasarkan nama, lokasi, atau edisi favorit Anda.</li>
                    <li><i class="fas fa-star"></i> <span>Rekomendasi Pilihan:</span> Jelajahi spot ngopi terbaik untuk Nugas, Nongkrong, atau yang Kantong Tipis.</li>
                    <li><i class="fas fa-map-marked-alt"></i> <span>Eksplorasi Wilayah:</span> Lihat daftar coffee shop dan peringkatnya di setiap sudut Bekasi.</li>
                    <li><i class="fas fa-mug-hot"></i> <span>Detail Lengkap & Menu Rekomendasi:</span> Dapatkan menu rekomendasi yang bisa jadi pilihan mu.</li>
                </ul>
                 <p>Terima kasih telah menjadikan Spot Ngoffee sebagai teman ngopi Anda!</p>

                <div class="contact-section">
    <h3>Hubungi Saya</h3>
    <div class="social-icons">
        <a href="mailto:nevandzmir@gmail.com" target="_blank" class="social-icon-link">
            <i class="fas fa-envelope"></i>
            <span>Email</span>
        </a>
        <a href="https://wa.me/6282125060232" target="_blank" class="social-icon-link"> <i class="fab fa-whatsapp"></i>
            <span>WhatsApp</span>
        </a>
        <a href="https://www.instagram.com/nevan.dzmrr" target="_blank" class="social-icon-link"> <i class="fab fa-instagram"></i>
            <span>Instagram</span>
        </a>
        <a href="https://www.linkedin.com/in/nevandzmrr" target="_blank" class="social-icon-link"> <i class="fab fa-linkedin"></i>
            <span>linkedln</span>
        </a>
    </div>
</div>
                </div>
            </div>
        </section>
    </main>

    <footer>
        <p>&copy; <?php echo date("Y"); ?>Spot Ngoffee — Created with by Muhammad Nevan Dzamir.</p>
    </footer>

    <script>
        // Data PHP yang di-encode menjadi JavaScript
        const allCoffeeShops = <?php echo $jsAllCoffeeShops; ?>;
        const allRecommendations = <?php echo $jsRecommendations; ?>;
        const allRegions = <?php echo $jsRegions; ?>;
        const allEditions = <?php echo $jsEditions; ?>; // Ini hanya daftar nama edisi
        const allEditionsWithId = <?php echo $jsEditionsWithId; ?>; // Ini daftar edisi dengan ID

        // Elemen Navigasi
        const homeBtn = document.getElementById('homeBtn');
        const searchBtn = document.getElementById('searchBtn');
        const regionBtn = document.getElementById('regionBtn');
        const aboutBtn = document.getElementById('aboutBtn');
        const navButtons = [homeBtn, searchBtn, regionBtn, aboutBtn];

        // Elemen Halaman
        const homePage = document.getElementById('home-page');
        const searchPage = document.getElementById('search-page');
        const regionPage = document.getElementById('region-page');
        const aboutPage = document.getElementById('about-page');
        const pages = {
            'home': homePage,
            'search': searchPage,
            'region': regionPage,
            'about': aboutPage
        };

        // Elemen Halaman Home
        const homeSearchInput = document.getElementById('homeSearchInput');
        const homeSearchButton = document.getElementById('homeSearchButton');
        const homeSuggestions = document.getElementById('homeSuggestions');
        const editionTabsContainer = document.querySelector('.edition-tabs');
        const editionSlides = document.getElementById('editionSlides');

        // Elemen Halaman Search
        const searchPageInput = document.getElementById('searchPageInput');
        const searchPageSuggestions = document.getElementById('searchPageSuggestions');
        const searchPageSearchButton = document.getElementById('searchPageSearchButton');
        const regionFilter = document.getElementById('regionFilter');
        const editionsFilter = document.getElementById('editionsFilter');
        const resetSearchFiltersButton = document.getElementById('resetSearchFiltersButton');
        const refreshSearchButton = document.getElementById('refreshSearchButton');
        const searchResultList = document.getElementById('searchResultList');
        const noSearchResultsMessage = document.getElementById('noSearchResultsMessage');
        const knowledgeText = document.getElementById('knowledgeText');
        const knowledgeImageSlider = document.getElementById('knowledgeImageSlider');
        const knowledgeSliderDots = document.getElementById('knowledgeSliderDots');

        let currentKnowledgeInterval; // Untuk slider gambar pengetahuan

        // --- Fungsi Utama ---

        // Fungsi untuk menampilkan halaman
        function showPage(pageId) {
            // Sembunyikan semua halaman
            for (const key in pages) {
                pages[key].classList.remove('active');
            }
            // Nonaktifkan semua tombol navigasi
            navButtons.forEach(btn => btn.classList.remove('active'));

            // Tampilkan halaman yang diminta
            pages[pageId].classList.add('active');
            // Tandai tombol navigasi yang sesuai
            document.getElementById(`${pageId}Btn`).classList.add('active');

            // Logika spesifik per halaman saat diaktifkan
            if (pageId === 'home') {
                // Pastikan rekomendasi edisi yang pertama aktif
                const firstEditionTab = editionTabsContainer.querySelector('.edition-tab-button');
                if (firstEditionTab) {
                    firstEditionTab.click(); // Trigger click untuk merender edisi pertama
                }
            } else if (pageId === 'search') {
                // Tampilkan 8 coffee shop random saat halaman search dibuka
                displayRandomCoffeeShops(8);
                // Reset filter saat masuk halaman search
                resetSearchFiltersButton.click();
                updateKnowledgeSidebar(); // Reset sidebar pengetahuan
            }
        }

   // Fungsi untuk membuat kartu coffee shop
function createPlaceCard(shop) {
    const placeCard = document.createElement('div');
    placeCard.classList.add('place-card');
    placeCard.dataset.shopId = shop.id;

    const imageUrl = shop.images && shop.images.length > 0 ? shop.images[0] : 'https://raw.githubusercontent.com/Anandax24/SpotNgoffee/main/images/coffee-shop-placeholder.jpg';

    // Mendapatkan edisi dari array edition_names
    // Jika ada edisi, gabungkan menjadi string. Jika tidak ada, tampilkan '-'
    const editionsDisplay = shop.edition_names && shop.edition_names.length > 0 ? shop.edition_names.join(', ') : '-';

    // Mendapatkan rentang harga
    // Pastikan shop.min_price dan shop.max_price ada dan bukan null/undefined
    const priceRangeDisplay = (shop.min_price !== null && shop.max_price !== null)
        ? `${shop.min_price} - ${shop.max_price}`
        : 'Harga tidak tersedia'; // Atau sesuaikan teks jika harga tidak ada

    placeCard.innerHTML = `
        <div class="place-image-wrapper">
            <img src="${imageUrl}" alt="${shop.name}" class="place-image">
        </div>
        <div class="place-info">
            <h3 class="place-name">${shop.name}</h3>
            <p class="place-location"><i class="fas fa-map-marker-alt"></i> ${shop.location}</p>
            <div class="place-details">
                <span class="place-price"><i class="fas fa-money-bill-alt"></i> ${priceRangeDisplay} /Orang</span>
                <span class="place-region"><i class="fas fa-wallet"></i> ${shop.region || '-'}</span>
            </div>
            <a href="${shop.maps_url}" target="_blank" class="btn-maps" onclick="event.stopPropagation();"><i class="fas fa-map"></i> Buka Maps</a>
        </div>
    `;
    placeCard.addEventListener('click', (event) => {
        if (event.target.closest('.btn-maps')) {
            return;
        }
        window.location.href = `detail_coffee_shop.php?id=${shop.id}`;
    });
    return placeCard;
}

        // Fungsi untuk merender daftar kartu
        function renderPlaceList(places, targetElement) {
            targetElement.innerHTML = ''; // Kosongkan
            if (places.length === 0) {
                if (targetElement === searchResultList) {
                    noSearchResultsMessage.classList.remove('hidden');
                }
                return;
            } else {
                noSearchResultsMessage.classList.add('hidden');
            }

            places.forEach(shop => {
                targetElement.appendChild(createPlaceCard(shop));
            });
        }

        // Fungsi untuk slider gambar sederhana
        function setupSlider(containerId, images, dotContainerId) {
            const sliderContainer = document.getElementById(containerId);
            const dotContainer = document.getElementById(dotContainerId);

            if (!sliderContainer || images.length === 0) {
                sliderContainer.innerHTML = '<img src="https://raw.githubusercontent.com/Anandax24/SpotNgoffee/main/images/no-image-available.png" alt="No image" class="active">';
                if (dotContainer) dotContainer.innerHTML = ''; // Hapus dots jika tidak ada gambar
                return null; // Mengembalikan null karena tidak ada interval
            }

            sliderContainer.innerHTML = '';
            if (dotContainer) dotContainer.innerHTML = '';

            images.forEach((imgSrc, index) => {
                const img = document.createElement('img');
                img.src = imgSrc;
                img.alt = 'Coffee Shop Image';
                if (index === 0) img.classList.add('active');
                sliderContainer.appendChild(img);

                if (dotContainer) {
                    const dot = document.createElement('span');
                    dot.classList.add('dot');
                    if (index === 0) dot.classList.add('active');
                    dot.addEventListener('click', () => {
                        showSlide(sliderContainer, dotContainer, index);
                    });
                    dotContainer.appendChild(dot);
                }
            });

            let currentSlide = 0;
            const slideInterval = setInterval(() => {
                currentSlide = (currentSlide + 1) % images.length;
                showSlide(sliderContainer, dotContainer, currentSlide);
            }, 3000); // Ganti gambar setiap 3 detik

            return slideInterval; // Mengembalikan interval ID
        }

        function showSlide(sliderContainer, dotContainer, index) {
            const images = sliderContainer.querySelectorAll('img');
            const dots = dotContainer ? dotContainer.querySelectorAll('.dot') : [];

            images.forEach((img, i) => {
                img.classList.remove('active');
                if (dots.length > 0) dots[i].classList.remove('active');
            });

            images[index].classList.add('active');
            if (dots.length > 0) dots[index].classList.add('active');
        }

        // --- Fungsi Halaman Home ---
        function renderEditionSlides(editionName) {
            const places = allRecommendations[editionName] || [];
            editionSlides.innerHTML = '';
            if (places.length === 0) {
                editionSlides.innerHTML = '<p style="text-align: center; width: 100%; color: #666;">Tidak ada rekomendasi untuk edisi ini.</p>';
                return;
            }
            places.forEach(shop => {
                const card = createPlaceCard(shop);
                card.classList.add('edition-slide'); // Tambahkan kelas untuk gaya slider
                editionSlides.appendChild(card);
            });
        }

        // --- Fungsi Halaman Search ---
        function displayRandomCoffeeShops(count) {
            // Acak array allCoffeeShops dan ambil 'count' item pertama
            const shuffled = [...allCoffeeShops].sort(() => 0.5 - Math.random());
            const randomShops = shuffled.slice(0, count);
            renderPlaceList(randomShops, searchResultList);
        }

        function applySearchFilters() {
    const searchTerm = searchPageInput.value.toLowerCase();
    const selectedRegion = regionFilter.value.toLowerCase();
    // selectedEditionName akan menjadi nama edisi seperti 'Nongkrong', 'Nugas', dll.
    const selectedEditionName = editionFilter.value; 

    const filteredShops = allCoffeeShops.filter(shop => {
        const nameMatch = shop.name.toLowerCase().includes(searchTerm);
        const locationMatch = shop.location.toLowerCase().includes(searchTerm);

        const regionFilterMatch = selectedRegion === '' || (shop.region && shop.region.toLowerCase() === selectedRegion);

        let editionMatch = true;
        if (selectedEditionName !== '') {
            // Asumsi: shop memiliki properti 'edition_name' yang sudah terisi dengan nama edisi
            // Ini akan lebih efisien jika nama edisi sudah disertakan saat mengambil data toko
            editionMatch = shop.edition_name && shop.edition_name.toLowerCase() === selectedEditionName.toLowerCase();
            
            // Alternatif (jika Anda hanya memiliki edition_id dan perlu memetakan di JS)
            // Anda perlu memiliki objek atau map yang memetakan edition_id ke nama edisi
            // const editionIdToNameMap = { 1: 'Nongkrong', 2: 'Nugas', 3: 'Kantong Tipis' }; // Contoh
            // const shopEditionName = editionIdToNameMap[shop.edition_id];
            // editionMatch = shopEditionName && shopEditionName.toLowerCase() === selectedEditionName.toLowerCase();
        }

        return (nameMatch || locationMatch) && regionFilterMatch && editionMatch;
    });
    renderPlaceList(filteredShops, searchResultList);
    updateKnowledgeSidebar(searchTerm, selectedRegion, selectedEditionName);
}

        const knowledgeImages = {
            'bekasi barat': ['https://raw.githubusercontent.com/Anandax24/SpotNgoffee/main/images/bekasi_barat1.jpg', 'https://raw.githubusercontent.com/Anandax24/SpotNgoffee/main/images/bekasi_barat2.jpg'],
            'bekasi timur': ['https://raw.githubusercontent.com/Anandax24/SpotNgoffee/main/images/bekasi_timur1.jpg', 'https://raw.githubusercontent.com/Anandax24/SpotNgoffee/main/images/bekasi_timur2.jpg'],
            'bekasi selatan': ['https://raw.githubusercontent.com/Anandax24/SpotNgoffee/main/images/bekasi_selatan1.jpg', 'https://raw.githubusercontent.com/Anandax24/SpotNgoffee/main/images/bekasi_selatan2.jpg'],
            'bekasi utara': ['https://raw.githubusercontent.com/Anandax24/SpotNgoffee/main/images/bekasi_utara1.jpg', 'https://raw.githubusercontent.com/Anandax24/SpotNgoffee/main/images/bekasi_utara2.jpg'],
            // Tambahkan gambar untuk edisi-edisi baru dari database
            'nongkrong': ['https://raw.githubusercontent.com/Anandax24/SpotNgoffee/main/images/nongkrong1.jpg', 'https://raw.githubusercontent.com/Anandax24/SpotNgoffee/main/images/nongkrong2.jpg'],
            'nugas': ['https://raw.githubusercontent.com/Anandax24/SpotNgoffee/main/images/nugas1.jpg', 'https://raw.githubusercontent.com/Anandax24/SpotNgoffee/main/images/nugas2.jpg'],
            'kantong tipis': ['https://raw.githubusercontent.com/Anandax24/SpotNgoffee/main/images/kantongtipis1.jpg', 'https://raw.githubusercontent.com/Anandax24/SpotNgoffee/main/images/kantongtipis2.jpg'],
            'default': ['https://raw.githubusercontent.com/Anandax24/SpotNgoffee/main/images/default_knowledge1.jpg', 'https://raw.githubusercontent.com/Anandax24/SpotNgoffee/main/images/default_knowledge2.jpg'] // Gambar default
        };

        const knowledgeTexts = {
            'bekasi barat': 'Bekasi Barat dikenal dengan berbagai pusat perbelanjaan dan area komersial yang ramai, menjadikannya lokasi strategis untuk coffee shop modern. Banyak kafe di sini menawarkan suasana yang dinamis, cocok untuk pertemuan atau sekadar bersantai setelah beraktivitas.',
            'bekasi timur': 'Area Bekasi Timur cenderung lebih padat penduduk dengan banyak perumahan. Coffee shop di sini sering kali menjadi titik pertemuan komunitas lokal atau tempat yang nyaman untuk bekerja dari rumah dengan suasana yang lebih tenang dan personal.',
            'bekasi selatan': 'Bekasi Selatan memiliki perpaduan antara area perumahan yang tenang dan beberapa pusat keramaian. Anda bisa menemukan kafe-kafe dengan konsep unik, ruang hijau, atau desain interior yang Instagramable, cocok untuk nongkrong santai atau foto-foto.',
            'bekasi utara': 'Bekasi Utara, dengan perkembangannya yang pesat, mulai menawarkan pilihan coffee shop yang beragam. Dari kafe sederhana hingga yang modern, area ini menyajikan tempat ngopi yang semakin diminati oleh anak muda dan pekerja.',
            // Tambahkan teks untuk edisi-edisi baru dari database
            'nongkrong': 'Edisi Nongkrong adalah panduan Anda untuk menemukan coffee shop dengan suasana paling asik dan nyaman untuk berkumpul bersama teman. Cari tempat dengan banyak kursi, musik yang pas, dan mungkin spot outdoor untuk suasana yang lebih hidup!',
            'nugas': 'Untuk Anda yang mencari tempat fokus untuk mengerjakan tugas atau bekerja, edisi Nugas menyajikan coffee shop dengan koneksi Wi-Fi yang stabil, colokan listrik yang memadai, dan suasana yang tenang. Ideal untuk produktivitas maksimal!',
            'kantong tipis': 'Jangan biarkan budget terbatas menghalangi hasrat ngopi Anda! Edisi Kantong Tipis adalah kumpulan coffee shop yang menawarkan kopi berkualitas dengan harga yang ramah di dompet. Nikmati kopi enak tanpa khawatir dompet tipis.',
            'default': 'Pilih filter atau cari nama coffee shop untuk mendapatkan pengetahuan menarik tentang wilayah atau edisi yang Anda pilih, lengkap dengan ilustrasi gambar yang menawan.'
        };

        function updateKnowledgeSidebar(searchTerm = '', selectedRegion = '', selectedEditionName = '') {
            let key = 'default';
            let text = knowledgeTexts['default'];
            let images = knowledgeImages['default'];

            if (selectedRegion && knowledgeTexts[selectedRegion]) {
                key = selectedRegion;
                text = knowledgeTexts[selectedRegion];
                images = knowledgeImages[selectedRegion];
            } else if (selectedEditionName && knowledgeTexts[selectedEditionName.toLowerCase()]) { // Gunakan nama edisi
                key = selectedEditionName.toLowerCase();
                text = knowledgeTexts[key];
                images = knowledgeImages[key];
            } else if (searchTerm && searchTerm.length > 2) {
                // Untuk demo, kita akan biarkan default atau berdasarkan filter yang lain jika search term tidak cocok dengan kategori.
                // Anda bisa menambahkan logika yang lebih kompleks di sini untuk mencocokkan searchTerm dengan kategori jika perlu.
            }

            knowledgeText.textContent = text;
            // Hentikan interval slider sebelumnya jika ada
            if (currentKnowledgeInterval) {
                clearInterval(currentKnowledgeInterval);
            }
            currentKnowledgeInterval = setupSlider('knowledgeImageSlider', images, 'knowledgeSliderDots');
        }


        // --- Fungsi Auto-suggest (Diperbarui untuk Home Search Langsung ke Profil) ---
        function setupAutoComplete(inputElement, suggestionsContainer, dataArray, isHomePageSearch = false) {
            let currentFocus = -1;

            inputElement.addEventListener('input', function() {
                const val = this.value.toLowerCase();
                suggestionsContainer.innerHTML = '';
                currentFocus = -1;
                if (!val) { return false; }

                const matchingSuggestions = []; // Akan menyimpan { name: "...", id: "..." }

                dataArray.forEach(shop => {
                    const shopNameLower = shop.name.toLowerCase();
                    const shopLocationLower = shop.location.toLowerCase();
                    if (shopNameLower.includes(val) || shopLocationLower.includes(val)) {
                        matchingSuggestions.push({ name: shop.name, id: shop.id, type: shopNameLower.includes(val) ? 'name' : 'location' });
                    }
                });

                // Batasi dan urutkan saran (misalnya yang match nama lebih dulu, atau urutan abjad)
                matchingSuggestions.sort((a, b) => {
                    if (a.type === 'name' && b.type === 'location') return -1;
                    if (a.type === 'location' && b.type === 'name') return 1;
                    return a.name.localeCompare(b.name);
                });

                let count = 0;
                matchingSuggestions.slice(0, 7).forEach(suggestion => { // Batasi 7 saran
                    const div = document.createElement('div');
                    div.classList.add('autocomplete-suggestion-item');
                    // Tampilkan highlight pada bagian yang match
                    const displayVal = suggestion.name;
                    const index = displayVal.toLowerCase().indexOf(val);
                    if (index !== -1) {
                         div.innerHTML = `${displayVal.substring(0, index)}<strong>${displayVal.substring(index, index + val.length)}</strong>${displayVal.substring(index + val.length)}`;
                    } else {
                        div.textContent = displayVal; // Fallback jika tidak ada highlight
                    }
                    div.dataset.shopId = suggestion.id; // Simpan ID di dataset

                    div.addEventListener('click', function() {
                        const shopId = this.dataset.shopId;
                        if (isHomePageSearch) {
                            window.location.href = `detail_coffee_shop.php?id=${shopId}`; // Langsung ke detail page
                        } else {
                            inputElement.value = this.textContent; // Isi input dengan nama yang dipilih
                            suggestionsContainer.innerHTML = '';
                            applySearchFilters(); // Terapkan filter di halaman search
                        }
                    });
                    suggestionsContainer.appendChild(div);
                });
            });

            inputElement.addEventListener('keydown', function(e) {
                let x = suggestionsContainer.getElementsByClassName('autocomplete-suggestion-item');
                if (x.length === 0) return;
                if (e.keyCode == 40) { // Panah Bawah
                    currentFocus = (currentFocus + 1) % x.length;
                    addActive(x);
                } else if (e.keyCode == 38) { // Panah Atas
                    currentFocus = (currentFocus - 1 + x.length) % x.length;
                    addActive(x);
                } else if (e.keyCode == 13) { // Enter
                    e.preventDefault();
                    if (currentFocus > -1) {
                        x[currentFocus].click();
                    } else {
                        // Jika tidak ada yang aktif, trigger pencarian biasa
                        if (isHomePageSearch) {
                            applyHomeSearchAsFilter(); // Cari di search page jika Enter tanpa memilih saran
                        } else {
                            applySearchFilters();
                        }
                    }
                }
            });

            function addActive(x) {
                if (!x) return false;
                removeActive(x);
                if (currentFocus >= x.length) currentFocus = 0;
                if (currentFocus < 0) currentFocus = (x.length - 1);
                x[currentFocus].classList.add('active');
            }

            function removeActive(x) {
                for (let i = 0; i < x.length; i++) {
                    x[i].classList.remove('active');
                }
            }

            document.addEventListener('click', function (e) {
                if (e.target !== inputElement && e.target.parentNode !== suggestionsContainer) {
                    suggestionsContainer.innerHTML = '';
                }
            });
        }

        // Fungsi search untuk home (jika user ketik manual dan ENTER tanpa memilih saran)
        function applyHomeSearchAsFilter() {
            const searchTerm = homeSearchInput.value.toLowerCase();
            if (searchTerm) {
                searchPageInput.value = searchTerm; // Isi input search di halaman search
                showPage('search'); // Pindah ke halaman search
                applySearchFilters(); // Terapkan filter di halaman search
            }
        }


        // --- Event Listeners ---

        // Navigasi
        homeBtn.addEventListener('click', () => showPage('home'));
        searchBtn.addEventListener('click', () => showPage('search'));
        regionBtn.addEventListener('click', () => showPage('region')); // Region sekarang ke landing page
        aboutBtn.addEventListener('click', () => showPage('about'));

        // Home Page Events
        homeSearchButton.addEventListener('click', applyHomeSearchAsFilter); // Jika tombol cari, lakukan filter di search page
        homeSearchInput.addEventListener('keyup', (event) => {
            if (event.key === 'Enter') {
                // Logika Enter sudah diatur dalam setupAutoComplete
            }
        });
        editionTabsContainer.addEventListener('click', (event) => {
            if (event.target.classList.contains('edition-tab-button')) {
                editionTabsContainer.querySelectorAll('.edition-tab-button').forEach(btn => btn.classList.remove('active'));
                event.target.classList.add('active');
                // Menggunakan data-edition-name
                renderEditionSlides(event.target.dataset.editionName);
            }
        });

        // Search Page Events
        searchPageSearchButton.addEventListener('click', applySearchFilters);
        searchPageInput.addEventListener('keyup', (event) => {
            if (event.key === 'Enter') {
                applySearchFilters();
            }
        });
        regionFilter.addEventListener('change', applySearchFilters);
        editionFilter.addEventListener('change', applySearchFilters);
        resetSearchFiltersButton.addEventListener('click', () => {
            searchPageInput.value = '';
            regionFilter.value = '';
            editionFilter.value = '';
            applySearchFilters(); // Terapkan filter kosong
        });
        refreshSearchButton.addEventListener('click', () => {
            displayRandomCoffeeShops(8); // Tampilkan 8 coffee shop acak lagi
            updateKnowledgeSidebar(); // Reset sidebar pengetahuan
        });


        // Inisialisasi saat DOM dimuat
        document.addEventListener('DOMContentLoaded', () => {
            // Setup auto-complete untuk input search di Home (true untuk isHomePageSearch)
            setupAutoComplete(homeSearchInput, homeSuggestions, allCoffeeShops, true);
            // Setup auto-complete untuk input search di Search Page (false untuk isHomePageSearch)
            setupAutoComplete(searchPageInput, searchPageSuggestions, allCoffeeShops, false);

            // Tampilkan halaman Home secara default
            showPage('home');
        });


    </script>
</body>
</html>