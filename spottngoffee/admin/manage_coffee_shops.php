<?php
session_start();
require_once __DIR__ . '/../config/db.php'; // Sesuaikan path ini

// Auth check
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: admin_login.php');
    exit();
}

$message = '';
$messageType = ''; // 'success' atau 'error'

// Ambil semua kedai kopi untuk ditampilkan
$coffeeShops = $pdo->query("SELECT id, name, description, location, region, min_price, max_price, open_hour, close_hour, maps_url, instagram, images, edition_id FROM coffee_shops ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);

// Ambil semua Edisi dari database (tabel 'editions'), masukkan ke dalam array asosiatif untuk pencarian cepat
$editions = [];
$editionResults = $pdo->query("SELECT id, name FROM editions ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
foreach ($editionResults as $edition) {
    $editions[$edition['id']] = $edition['name']; // Format: [id => name]
}

// Ambil semua Region unik dari database
$regions = $pdo->query("SELECT DISTINCT region FROM coffee_shops WHERE region IS NOT NULL AND region != '' ORDER BY region ASC")->fetchAll(PDO::FETCH_COLUMN);

// Tambahkan beberapa region default jika belum ada (opsional, sesuaikan dengan kebutuhan)
$defaultRegions = ['Bekasi Barat', 'Bekasi Timur', 'Bekasi Selatan', 'Bekasi Utara'];
foreach ($defaultRegions as $defRegion) {
    if (!in_array($defRegion, $regions)) {
        $regions[] = $defRegion;
    }
}
sort($regions); // Urutkan lagi setelah penambahan manual

$editCoffeeShop = null; // Untuk mode edit
$selectedImages = []; // Untuk menyimpan daftar gambar yang ada saat edit

// Tangani pengiriman formulir
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $id = $_POST['id'] ?? null; // ID ini akan digunakan untuk mode edit
    $name = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $location = trim($_POST['location'] ?? '');
    $region = trim($_POST['region'] ?? '');
    $edition_id = $_POST['edition_id'] ?? null; // Foreign key ke tabel editions

    // Validasi dan format harga untuk kelipatan 5000
    $min_price_input = filter_var($_POST['min_price'] ?? '', FILTER_VALIDATE_INT);
    $max_price_input = filter_var($_POST['max_price'] ?? '', FILTER_VALIDATE_INT);

    $min_price = ($min_price_input !== false) ? (int)(round($min_price_input / 5000) * 5000) : null;
    $max_price = ($max_price_input !== false) ? (int)(round($max_price_input / 5000) * 5000) : null;

    // Tambahkan validasi jika harga tidak dalam kelipatan 5000
    if ($min_price_input !== false && $min_price_input % 5000 !== 0) {
        $message = "Harga Minimum harus kelipatan 5000. Nilai dibulatkan ke " . number_format($min_price, 0, ',', '.');
        $messageType = 'error';
    }
    if ($max_price_input !== false && $max_price_input % 5000 !== 0) {
        $message = ($message ? $message . "<br>" : "") . "Harga Maksimum harus kelipatan 5000. Nilai dibulatkan ke " . number_format($max_price, 0, ',', '.');
        $messageType = 'error';
    }


    // Format jam menjadi HH:MM sebelum disimpan
    $open_hour = trim($_POST['open_hour'] ?? '');
    if (!empty($open_hour)) {
        $open_hour = substr($open_hour, 0, 5);
    } else {
        $open_hour = null;
    }

    $close_hour = trim($_POST['close_hour'] ?? '');
    if (!empty($close_hour)) {
        $close_hour = substr($close_hour, 0, 5);
    } else {
        $close_hour = null;
    }

    $maps_url = trim($_POST['maps_url'] ?? '');
    $instagram = trim($_POST['instagram'] ?? '');

    // Convert empty strings to null for optional text fields
    $description = $description === '' ? null : $description;
    $region = $region === '' ? null : $region;
    $maps_url = $maps_url === '' ? null : $maps_url;
    $instagram = $instagram === '' ? null : $instagram;

    // Handle existing images to be removed
    $existingImagesJson = $_POST['existingImagesJson'] ?? '[]';
    $existingImages = json_decode($existingImagesJson, true);
    if (!is_array($existingImages)) {
        $existingImages = [];
    }

    // Ambil gambar yang diupload
    $uploadedImages = [];
    if (isset($_FILES['images']) && is_array($_FILES['images']['name'])) {
        $uploadDir = __DIR__ . '/../uploads/coffee_shops/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }

        foreach ($_FILES['images']['name'] as $key => $imageName) {
            if ($_FILES['images']['error'][$key] === UPLOAD_ERR_OK) {
                $fileTmpPath = $_FILES['images']['tmp_name'][$key];
                $fileExtension = strtolower(pathinfo($imageName, PATHINFO_EXTENSION));
                $newFileName = uniqid('coffee_shop_') . '.' . $fileExtension;
                $destPath = $uploadDir . $newFileName;

                $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif'];
                if (!in_array($fileExtension, $allowedExtensions)) {
                    $message = "Hanya file JPG, JPEG, PNG, dan GIF yang diizinkan.";
                    $messageType = 'error';
                    continue;
                }

                if (move_uploaded_file($fileTmpPath, $destPath)) {
                    $uploadedImages[] = 'uploads/coffee_shops/' . $newFileName;
                } else {
                    $message = "Gagal mengunggah beberapa gambar.";
                    $messageType = 'error';
                }
            }
        }
    }

    $allImages = array_merge($existingImages, $uploadedImages);
    $imagesJson = json_encode($allImages);

    // Hanya lanjutkan operasi DB jika tidak ada error validasi harga
    if ($messageType !== 'error' || empty($message)) {
        if ($action === 'add') {
            if (empty($name) || empty($location) || empty($edition_id)) {
                $message = 'Nama, Alamat (Location), dan Edisi harus diisi.';
                $messageType = 'error';
            } else {
                // --- START: Validasi Nama Unik untuk Tambah Baru ---
                $stmtCheckName = $pdo->prepare("SELECT COUNT(*) FROM coffee_shops WHERE name = ?");
                $stmtCheckName->execute([$name]);
                if ($stmtCheckName->fetchColumn() > 0) {
                    $message = 'Nama kedai kopi sudah ada. Mohon gunakan nama lain.' . htmlspecialchars($name) . '" sudah ada. Mohon gunakan nama lain.';
                    $messageType = 'error';
                } else {
                // --- END: Validasi Nama Unik untuk Tambah Baru ---
                    try {
                        $stmt = $pdo->prepare("INSERT INTO coffee_shops (name, description, location, region, min_price, max_price, open_hour, close_hour, maps_url, instagram, images, edition_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                        $stmt->execute([
                            $name,
                            $description,
                            $location,
                            $region,
                            $min_price,
                            $max_price,
                            $open_hour,
                            $close_hour,
                            $maps_url,
                            $instagram,
                            $imagesJson,
                            $edition_id
                        ]);
                        $message = 'Kedai kopi berhasil ditambahkan!';
                        $messageType = 'success';
                    } catch (PDOException $e) {
                        $message = 'Gagal menambahkan kedai kopi: ' . $e->getMessage();
                        $messageType = 'error';
                    }
                } // End of else for name uniqueness check
            }
        } elseif ($action === 'edit' && $id) {
            if (empty($name) || empty($location) || empty($edition_id)) {
                $message = 'Nama, Alamat (Location), dan Edisi harus diisi.';
                $messageType = 'error';
            } else {
                // --- START: Validasi Nama Unik untuk Edit ---
                $stmtCheckName = $pdo->prepare("SELECT COUNT(*) FROM coffee_shops WHERE name = ? AND id != ?");
                $stmtCheckName->execute([$name, $id]);
                if ($stmtCheckName->fetchColumn() > 0) {
                    $message = 'Nama kedai kopi "' . htmlspecialchars($name) . '" sudah digunakan oleh kedai kopi lain. Mohon gunakan nama lain.';
                    $messageType = 'error';
                } else {
                // --- END: Validasi Nama Unik untuk Edit ---
                    try {
                        $stmt = $pdo->prepare("UPDATE coffee_shops SET name = ?, description = ?, location = ?, region = ?, min_price = ?, max_price = ?, open_hour = ?, close_hour = ?, maps_url = ?, instagram = ?, images = ?, edition_id = ? WHERE id = ?");
                        $stmt->execute([
                            $name,
                            $description,
                            $location,
                            $region,
                            $min_price,
                            $max_price,
                            $open_hour,
                            $close_hour,
                            $maps_url,
                            $instagram,
                            $imagesJson,
                            $edition_id,
                            $id
                        ]);
                        $message = 'Kedai kopi berhasil diperbarui!';
                        $messageType = 'success';
                    } catch (PDOException $e) {
                        $message = 'Gagal memperbarui kedai kopi: ' . $e->getMessage();
                        $messageType = 'error';
                    }
                } // End of else for name uniqueness check for edit
            }
        } elseif ($action === 'delete' && $id) {
            try {
                $stmt = $pdo->prepare("SELECT images FROM coffee_shops WHERE id = ?");
                $stmt->execute([$id]);
                $shopToDelete = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($shopToDelete && !empty($shopToDelete['images'])) {
                    $imagesToDelete = json_decode($shopToDelete['images'], true);
                    if (is_array($imagesToDelete)) {
                        foreach ($imagesToDelete as $imagePath) {
                            $fullPath = __DIR__ . '/../' . $imagePath;
                            if (file_exists($fullPath)) {
                                unlink($fullPath);
                            }
                        }
                    }
                }

                $stmt = $pdo->prepare("DELETE FROM coffee_shops WHERE id = ?");
                $stmt->execute([$id]);
                $message = 'Kedai kopi berhasil dihapus!';
                $messageType = 'success';
            } catch (PDOException $e) {
                $message = 'Gagal menghapus kedai kopi: ' . $e->getMessage();
                $messageType = 'error';
            }
        }
    }
    // Redirect setelah POST agar halaman tidak di-refresh dengan data POST sebelumnya
    header("Location: manage_coffee_shops.php?message=" . urlencode($message) . "&type=" . urlencode($messageType));
    exit();
}

// Tangani parameter GET untuk mode edit atau pesan
if (isset($_GET['action']) && $_GET['action'] === 'edit' && isset($_GET['id'])) {
    $id = $_GET['id'];
    $stmt = $pdo->prepare("SELECT id, name, description, location, region, min_price, max_price, open_hour, close_hour, maps_url, instagram, images, edition_id FROM coffee_shops WHERE id = ?");
    $stmt->execute([$id]);
    $editCoffeeShop = $stmt->fetch(PDO::FETCH_ASSOC);

    // Format jam buka dan tutup agar hanya menampilkan HH:MM
    if ($editCoffeeShop) {
        if (!empty($editCoffeeShop['open_hour'])) {
            $editCoffeeShop['open_hour'] = substr($editCoffeeShop['open_hour'], 0, 5);
        }
        if (!empty($editCoffeeShop['close_hour'])) {
            $editCoffeeShop['close_hour'] = substr($editCoffeeShop['close_hour'], 0, 5);
        }
    }

    if ($editCoffeeShop && !empty($editCoffeeShop['images'])) {
        $selectedImages = json_decode($editCoffeeShop['images'], true);
        if (!is_array($selectedImages)) {
            $selectedImages = [];
        }
    }
}

if (isset($_GET['message'])) {
    $message = htmlspecialchars($_GET['message']);
    $messageType = htmlspecialchars($_GET['type'] ?? 'info');
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Kedai Kopi - Spot Ngoffee Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        /* CSS tetap sama */
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            margin: 0;
            background-color: #FDF7E4;
            color: #333;
            display: flex;
            min-height: 100vh;
        }
        .main-content {
            flex-grow: 1;
            padding: 20px;
            background-color: #fff;
            border-radius: 8px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.05);
            margin: 20px;
        }

        h1 {
            color: #5C4033;
            margin-bottom: 20px;
        }

        .message {
            padding: 10px;
            margin-bottom: 15px;
            border-radius: 5px;
            font-weight: bold;
        }
        .message.success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .message.error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .form-container, .list-container {
            background-color: #fff;
            padding: 25px;
            border-radius: 8px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }

        .form-group {
            margin-bottom: 15px;
        }

        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
            color: #5C4033;
        }

        .form-group input[type="text"],
        .form-group input[type="url"],
        .form-group input[type="number"],
        .form-group input[type="time"],
        .form-group textarea,
        .form-group select {
            width: calc(100% - 22px);
            padding: 10px;
            border: 1px solid #ccc;
            border-radius: 4px;
            font-size: 1em;
            box-sizing: border-box;
        }

        .form-group textarea {
            resize: vertical;
            min-height: 80px;
        }

        .form-group input[type="file"] {
            padding: 5px 0;
        }

        .btn-submit {
            background-color: #7B3F00;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 1em;
            transition: background-color 0.3s ease;
        }

        .btn-submit:hover {
            background-color: #8B4513;
        }

        .table-container {
            overflow-x: auto;
            margin-top: 20px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }

        table th, table td {
            border: 1px solid #ddd;
            padding: 10px;
            text-align: left;
            vertical-align: top;
        }

        table th {
            background-color: #e6e6e6;
            color: #5C4033;
        }

        table tr:nth-child(even) {
            background-color: #f9f9f9;
        }

        .action-buttons a, .action-buttons button {
            display: inline-block;
            margin-right: 5px;
            padding: 6px 10px;
            border-radius: 4px;
            text-decoration: none;
            font-size: 0.9em;
            cursor: pointer;
            border: none;
            transition: background-color 0.3s ease;
        }
        .action-buttons a i, .action-buttons button i {
            margin-right: 3px;
        }

        .action-buttons a[title="Edit"] {
            background-color: #007bff;
            color: white;
        }
        .action-buttons a[title="Edit"]:hover {
            background-color: #0056b3;
        }
        .action-buttons button[type="submit"] {
            background-color: #dc3545;
            color: white;
        }
        .action-buttons button[type="submit"]:hover {
            background-color: #c82333;
        }

        .current-images-container {
            margin-top: 10px;
            border: 1px dashed #ccc;
            padding: 10px;
            border-radius: 5px;
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            align-items: center;
        }

        .current-image-item {
            position: relative;
            width: 100px;
            height: 100px;
            border: 1px solid #eee;
            border-radius: 5px;
            overflow: hidden;
            flex-shrink: 0;
        }

        .current-image-item img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .current-image-item .remove-image-btn {
            position: absolute;
            top: 2px;
            right: 2px;
            background-color: rgba(255, 0, 0, 0.7);
            color: white;
            border: none;
            border-radius: 50%;
            width: 20px;
            height: 20px;
            display: flex;
            justify-content: center;
            align-items: center;
            cursor: pointer;
            font-size: 0.8em;
            line-height: 1;
            padding: 0;
        }

        .current-image-item .remove-image-btn:hover {
            background-color: rgba(255, 0, 0, 1);
        }
    </style>
</head>
<body>
    <?php include 'admin_sidebar.php'; ?>

    <div class="main-content">
        <h1>Kelola Kedai Kopi</h1>

        <?php if ($message): ?>
            <p class="message <?php echo $messageType; ?>"><?php echo htmlspecialchars($message); ?></p>
        <?php endif; ?>

        <div class="form-container">
            <h2><?php echo $editCoffeeShop ? 'Edit Kedai Kopi' : 'Tambah Kedai Kopi Baru'; ?></h2>
            <form action="manage_coffee_shops.php" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="action" value="<?php echo $editCoffeeShop ? 'edit' : 'add'; ?>">
                <?php if ($editCoffeeShop): ?>
                    <input type="hidden" name="id" value="<?php echo htmlspecialchars($editCoffeeShop['id']); ?>">
                    <input type="hidden" id="existingImagesJson" name="existingImagesJson" value='<?php echo json_encode($selectedImages); ?>'>
                <?php endif; ?>
                <div class="form-group">
                    <label for="name">Nama Kedai Kopi:</label>
                    <input type="text" id="name" name="name" value="<?php echo htmlspecialchars($editCoffeeShop['name'] ?? ''); ?>" required>
                </div>

                <div class="form-group">
                    <label for="location">Alamat (Location):</label>
                    <textarea id="location" name="location" required><?php echo htmlspecialchars($editCoffeeShop['location'] ?? ''); ?></textarea>
                </div>

                <div class="form-group">
                    <label for="description">Deskripsi:</label>
                    <textarea id="description" name="description"><?php echo htmlspecialchars($editCoffeeShop['description'] ?? ''); ?></textarea>
                </div>

                <div class="form-group">
                    <label for="min_price">Harga Minimum (Rp):</label>
                    <input type="number" id="min_price" name="min_price" value="<?php echo htmlspecialchars($editCoffeeShop['min_price'] ?? ''); ?>" step="5000">
                </div>

                <div class="form-group">
                    <label for="max_price">Harga Maksimum (Rp):</label>
                    <input type="number" id="max_price" name="max_price" value="<?php echo htmlspecialchars($editCoffeeShop['max_price'] ?? ''); ?>" step="5000">
                </div>

                <div class="form-group">
                    <label for="open_hour">Jam Buka:</label>
                    <input type="time" id="open_hour" name="open_hour" value="<?php echo htmlspecialchars($editCoffeeShop['open_hour'] ?? ''); ?>">
                </div>

                <div class="form-group">
                    <label for="close_hour">Jam Tutup:</label>
                    <input type="time" id="close_hour" name="close_hour" value="<?php echo htmlspecialchars($editCoffeeShop['close_hour'] ?? ''); ?>">
                </div>

                <div class="form-group">
                    <label for="maps_url">Google Maps URL:</label>
                    <input type="url" id="maps_url" name="maps_url" value="<?php echo htmlspecialchars($editCoffeeShop['maps_url'] ?? ''); ?>">
                </div>

                <div class="form-group">
                    <label for="instagram">Akun Instagram:</label>
                    <input type="text" id="instagram" name="instagram" value="<?php echo htmlspecialchars($editCoffeeShop['instagram'] ?? ''); ?>">
                </div>

                <div class="form-group">
                    <label for="edition_id">Edisi (Kategori):</label>
                    <select id="edition_id" name="edition_id" required>
                        <option value="">Pilih Edisi</option>
                        <?php foreach ($editions as $id_edition => $editionName): // Ganti $id jadi $id_edition untuk menghindari konflik nama ?>
                            <option value="<?php echo htmlspecialchars($id_edition); ?>"
                                <?php echo ($editCoffeeShop && $editCoffeeShop['edition_id'] == $id_edition) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($editionName); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="region">Region:</label>
                    <select id="region" name="region">
                        <option value="">Pilih Region</option>
                        <?php foreach ($regions as $regionOption): ?>
                            <option value="<?php echo htmlspecialchars($regionOption); ?>"
                                <?php echo ($editCoffeeShop && $editCoffeeShop['region'] == $regionOption) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($regionOption); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="images">Gambar (Upload baru atau pilih yang sudah ada):</label>
                    <input type="file" id="images" name="images[]" accept="image/*" multiple>
                    <?php if ($editCoffeeShop && !empty($selectedImages)): ?>
                        <p style="margin-top: 10px; font-weight: bold; color: #5C4033;">Gambar Saat Ini:</p>
                        <div id="currentImagesContainer" class="current-images-container">
                            <?php foreach ($selectedImages as $imagePath): ?>
                                <div class="current-image-item" data-image-url="<?php echo htmlspecialchars($imagePath); ?>">
                                    <img src="../<?php echo htmlspecialchars($imagePath); ?>" alt="Gambar Kedai Kopi">
                                    <button type="button" class="remove-image-btn" onclick="removeImage(this)">X</button>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <button type="submit" class="btn-submit"><?php echo $editCoffeeShop ? 'Update Kedai Kopi' : 'Tambah Kedai Kopi'; ?></button>
                <?php if ($editCoffeeShop): ?>
                    <a href="manage_coffee_shops.php" class="btn-submit" style="background-color: #6c757d;">Batal</a>
                <?php endif; ?>
            </form>
        </div>

        <div class="list-container">
            <h2>Daftar Kedai Kopi</h2>
            <?php if (empty($coffeeShops)): ?>
                <p>Belum ada kedai kopi yang terdaftar.</p>
            <?php else: ?>
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>Nama</th>
                                <th>Alamat</th>
                                <th>Edisi</th>
                                <th>Region</th>
                                <th>Jam Buka</th>
                                <th>Jam Tutup</th>
                                <th>Harga Min</th>
                                <th>Harga Max</th>
                                <th>Instagram</th>
                                <th>Gambar</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($coffeeShops as $shop): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($shop['name'] ?? 'N/A'); ?></td>
                                    <td><?php echo htmlspecialchars($shop['location'] ?? 'Tidak Ada Alamat'); ?></td>
                                    <td>
                                        <?php
                                            $editionName = $editions[$shop['edition_id']] ?? 'Tidak Diketahui';
                                            echo htmlspecialchars($editionName);
                                        ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($shop['region'] ?? 'Tidak Ada Region'); ?></td>
                                    <td><?php echo htmlspecialchars(substr($shop['open_hour'] ?? '', 0, 5) ?: '-'); ?></td>
                                    <td><?php echo htmlspecialchars(substr($shop['close_hour'] ?? '', 0, 5) ?: '-'); ?></td>
                                    <td><?php echo htmlspecialchars($shop['min_price'] ?? '-'); ?></td>
                                    <td><?php echo htmlspecialchars($shop['max_price'] ?? '-'); ?></td>
                                    <td><?php echo htmlspecialchars($shop['instagram'] ?? '-'); ?></td>
                                    <td>
                                        <?php
                                        $images = json_decode($shop['images'] ?? '[]', true);
                                        if (!empty($images) && is_array($images)) {
                                            echo '<img src="../' . htmlspecialchars($images[0]) . '" alt="' . htmlspecialchars($shop['name']) . '" style="width: 50px; height: 50px; object-fit: cover; border-radius: 5px;">';
                                        } else {
                                            echo 'Tidak ada gambar';
                                        }
                                        ?>
                                    </td>
                                    <td class="action-buttons">
                                        <a href="manage_coffee_shops.php?action=edit&id=<?php echo htmlspecialchars($shop['id']); ?>" title="Edit"><i class="fas fa-edit"></i> Edit</a>
                                        <form action="manage_coffee_shops.php" method="POST" style="display:inline;" onsubmit="return confirm('Anda yakin ingin menghapus kedai kopi ini? Semua item menu dan rekomendasi yang terkait juga akan dihapus.');">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="id" value="<?php echo htmlspecialchars($shop['id']); ?>">
                                            <button type="submit" title="Hapus"><i class="fas fa-trash-alt"></i> Hapus</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        function removeImage(button) {
            if (confirm('Anda yakin ingin menghapus gambar ini? Gambar akan dihapus setelah Anda menyimpan perubahan.')) {
                const parentDiv = button.closest('.current-image-item');
                const imageUrlToRemove = parentDiv.dataset.imageUrl;

                const existingImagesJsonInput = document.getElementById('existingImagesJson');
                let existingImages = JSON.parse(existingImagesJsonInput.value);

                existingImages = existingImages.filter(url => url !== imageUrlToRemove);

                existingImagesJsonInput.value = JSON.stringify(existingImages);

                parentDiv.remove();

                const currentImagesContainer = document.getElementById('currentImagesContainer');
                if (currentImagesContainer && currentImagesContainer.children.length === 0) {
                    currentImagesContainer.style.display = 'none';
                }
            }
        }
    </script>
</body>
</html>