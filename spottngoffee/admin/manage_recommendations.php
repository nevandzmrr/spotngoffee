<?php
session_start();
require_once __DIR__ . '/../config/db.php'; // Sesuaikan path ini

// Auth check
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: admin_login.php');
    exit();
}

$message = '';
$messageType = '';

// Ambil semua kedai kopi beserta regionnya untuk dropdown
$coffeeShops = $pdo->query("SELECT id, name, region FROM coffee_shops ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);

// Ambil semua edisi dari database
$editions = $pdo->query("SELECT id, name FROM editions ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);

// Ambil semua rekomendasi (sekarang join dengan editions dan coffee_shops untuk menampilkan nama edisi dan nama kedai kopi beserta region)
$recommendations = $pdo->query("
    SELECT r.id, r.coffee_shop_id, r.edition_id, cs.name AS coffee_shop_name, cs.region AS coffee_shop_region, e.name AS edition_name
    FROM recommendations r
    JOIN coffee_shops cs ON r.coffee_shop_id = cs.id
    JOIN editions e ON r.edition_id = e.id
    ORDER BY e.name, cs.name ASC
")->fetchAll(PDO::FETCH_ASSOC);

$editRecommendation = null; // Untuk mode edit

// Tangani pengiriman formulir
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $id = $_POST['id'] ?? null;
    $coffee_shop_id = $_POST['coffee_shop_id'] ?? null;
    $edition_id = $_POST['edition_id'] ?? null;

    // Batasan maksimal rekomendasi per edisi
    $MAX_RECOMMENDATIONS_PER_EDITION = 34;

    if ($action === 'add') {
        if (empty($coffee_shop_id) || empty($edition_id)) {
            $message = 'Kedai Kopi dan Edisi harus diisi.';
            $messageType = 'error';
        } else {
            try {
                // Cek apakah rekomendasi sudah ada untuk kombinasi kedai kopi dan edisi ini
                $stmtCheck = $pdo->prepare("SELECT COUNT(*) FROM recommendations WHERE coffee_shop_id = ? AND edition_id = ?");
                $stmtCheck->execute([$coffee_shop_id, $edition_id]);
                if ($stmtCheck->fetchColumn() > 0) {
                    $message = 'Rekomendasi untuk kedai kopi dan edisi ini sudah ada.';
                    $messageType = 'error';
                } else {
                    // Cek jumlah rekomendasi untuk edisi ini
                    $stmtCount = $pdo->prepare("SELECT COUNT(*) FROM recommendations WHERE edition_id = ?");
                    $stmtCount->execute([$edition_id]);
                    if ($stmtCount->fetchColumn() >= $MAX_RECOMMENDATIONS_PER_EDITION) {
                        $message = 'Edisi ini telah mencapai batas maksimum ' . $MAX_RECOMMENDATIONS_PER_EDITION . ' rekomendasi.';
                        $messageType = 'error';
                    } else {
                        // Ambil region dari coffee shop yang akan ditambahkan
                        $stmtCoffeeShopRegion = $pdo->prepare("SELECT region FROM coffee_shops WHERE id = ?");
                        $stmtCoffeeShopRegion->execute([$coffee_shop_id]);
                        $newCoffeeShopRegion = $stmtCoffeeShopRegion->fetchColumn();

                        // Cek apakah sudah ada kedai kopi dari region yang sama di edisi ini
                        $stmtCheckRegion = $pdo->prepare("
                            SELECT COUNT(r.id)
                            FROM recommendations r
                            JOIN coffee_shops cs ON r.coffee_shop_id = cs.id
                            WHERE r.edition_id = ? AND cs.region = ?
                        ");
                        $stmtCheckRegion->execute([$edition_id, $newCoffeeShopRegion]);
                        if ($stmtCheckRegion->fetchColumn() > 0) {
                            $message = 'Sudah ada rekomendasi dari region "' . htmlspecialchars($newCoffeeShopRegion) . '" untuk edisi ini. Silakan pilih kedai kopi dari region yang berbeda.';
                            $messageType = 'error';
                        } else {
                            // Jika semua validasi lolos, tambahkan rekomendasi
                            $stmt = $pdo->prepare("INSERT INTO recommendations (coffee_shop_id, edition_id) VALUES (?, ?)");
                            $stmt->execute([$coffee_shop_id, $edition_id]);
                            $message = 'Rekomendasi berhasil ditambahkan!';
                            $messageType = 'success';
                        }
                    }
                }
            } catch (PDOException $e) {
                $message = 'Gagal menambahkan rekomendasi: ' . $e->getMessage();
                $messageType = 'error';
            }
        }
    } elseif ($action === 'edit' && $id) {
        if (empty($coffee_shop_id) || empty($edition_id)) {
            $message = 'Kedai Kopi dan Edisi harus diisi.';
            $messageType = 'error';
        } else {
            try {
                // Ambil region dari coffee shop yang akan diperbarui
                $stmtCoffeeShopRegion = $pdo->prepare("SELECT region FROM coffee_shops WHERE id = ?");
                $stmtCoffeeShopRegion->execute([$coffee_shop_id]);
                $newCoffeeShopRegion = $stmtCoffeeShopRegion->fetchColumn();

                // Cek apakah rekomendasi yang diperbarui akan membuat duplikat (coffee shop dan edition sama)
                $stmtCheckDuplicate = $pdo->prepare("SELECT COUNT(*) FROM recommendations WHERE coffee_shop_id = ? AND edition_id = ? AND id != ?");
                $stmtCheckDuplicate->execute([$coffee_shop_id, $edition_id, $id]);
                if ($stmtCheckDuplicate->fetchColumn() > 0) {
                    $message = 'Rekomendasi dengan kombinasi kedai kopi dan edisi ini sudah ada.';
                    $messageType = 'error';
                } else {
                    // Cek apakah setelah update, akan ada dua atau lebih kedai kopi dari region yang sama di edisi yang sama (selain rekomendasi yang sedang diedit)
                    $stmtCheckRegionAfterUpdate = $pdo->prepare("
                        SELECT COUNT(r.id)
                        FROM recommendations r
                        JOIN coffee_shops cs ON r.coffee_shop_id = cs.id
                        WHERE r.edition_id = ? AND cs.region = ? AND r.id != ?
                    ");
                    $stmtCheckRegionAfterUpdate->execute([$edition_id, $newCoffeeShopRegion, $id]);
                    if ($stmtCheckRegionAfterUpdate->fetchColumn() > 0) {
                        $message = 'Mengubah ke kedai kopi ini akan membuat duplikasi region "' . htmlspecialchars($newCoffeeShopRegion) . '" dalam edisi ini. Silakan pilih kedai kopi dari region yang berbeda.';
                        $messageType = 'error';
                    } else {
                        $stmt = $pdo->prepare("UPDATE recommendations SET coffee_shop_id = ?, edition_id = ? WHERE id = ?");
                        $stmt->execute([$coffee_shop_id, $edition_id, $id]);
                        $message = 'Rekomendasi berhasil diperbarui!';
                        $messageType = 'success';
                    }
                }
            } catch (PDOException $e) {
                $message = 'Gagal memperbarui rekomendasi: ' . $e->getMessage();
                $messageType = 'error';
            }
        }
    } elseif ($action === 'delete' && $id) {
        try {
            $stmt = $pdo->prepare("DELETE FROM recommendations WHERE id = ?");
            $stmt->execute([$id]);
            $message = 'Rekomendasi berhasil dihapus!';
            $messageType = 'success';
        } catch (PDOException $e) {
            $message = 'Gagal menghapus rekomendasi: ' . $e->getMessage();
            $messageType = 'error';
        }
    }
    // Redirect untuk mencegah resubmisi formulir
    header("Location: manage_recommendations.php?message=" . urlencode($message) . "&type=" . urlencode($messageType));
    exit();
}

// Tangani parameter GET untuk mode edit atau pesan
if (isset($_GET['action']) && $_GET['action'] === 'edit' && isset($_GET['id'])) {
    $id = $_GET['id'];
    $stmt = $pdo->prepare("SELECT * FROM recommendations WHERE id = ?");
    $stmt->execute([$id]);
    $editRecommendation = $stmt->fetch(PDO::FETCH_ASSOC);
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
    <title>Kelola Rekomendasi - Spot Ngoffee Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        /* General Admin Styles */
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            margin: 0;
            background-color: #FDF7E4; /* Krem Muda */
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

        .form-group select {
            width: calc(100% - 22px);
            padding: 10px;
            border: 1px solid #ccc;
            border-radius: 4px;
            font-size: 1em;
            box-sizing: border-box;
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
    </style>
</head>
<body>
    <?php include 'admin_sidebar.php'; // Include the sidebar ?>

    <div class="main-content">
        <h1>Kelola Rekomendasi</h1>

        <?php if ($message): ?>
            <p class="message <?php echo $messageType; ?>"><?php echo htmlspecialchars($message); ?></p>
        <?php endif; ?>

        <div class="form-container">
            <h2><?php echo $editRecommendation ? 'Edit Rekomendasi' : 'Tambah Rekomendasi Baru'; ?></h2>
            <form action="manage_recommendations.php" method="POST">
                <input type="hidden" name="action" value="<?php echo $editRecommendation ? 'edit' : 'add'; ?>">
                <?php if ($editRecommendation): ?>
                    <input type="hidden" name="id" value="<?php echo htmlspecialchars($editRecommendation['id']); ?>">
                <?php endif; ?>

                <div class="form-group">
                    <label for="coffee_shop_id">Kedai Kopi:</label>
                    <select id="coffee_shop_id" name="coffee_shop_id" required>
                        <option value="">Pilih Kedai Kopi</option>
                        <?php foreach ($coffeeShops as $shop): ?>
                            <option value="<?php echo htmlspecialchars($shop['id']); ?>"
                                <?php echo ($editRecommendation && $editRecommendation['coffee_shop_id'] == $shop['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($shop['name']); ?> (<?php echo htmlspecialchars($shop['region']); ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="edition_id">Edisi:</label>
                    <select id="edition_id" name="edition_id" required>
                        <option value="">Pilih Edisi</option>
                        <?php foreach ($editions as $edition): ?>
                            <option value="<?php echo htmlspecialchars($edition['id']); ?>"
                                <?php echo ($editRecommendation && $editRecommendation['edition_id'] == $edition['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($edition['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <button type="submit" class="btn-submit"><?php echo $editRecommendation ? 'Update Rekomendasi' : 'Tambah Rekomendasi'; ?></button>
                <?php if ($editRecommendation): ?>
                    <a href="manage_recommendations.php" class="btn-submit" style="background-color: #6c757d;">Batal</a>
                <?php endif; ?>
            </form>
        </div>

        <div class="list-container">
            <h2>Daftar Rekomendasi</h2>
            <?php if (empty($recommendations)): ?>
                <p>Belum ada rekomendasi yang terdaftar.</p>
            <?php else: ?>
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>Kedai Kopi</th>
                                <th>Region</th>
                                <th>Edisi</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recommendations as $rec): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($rec['coffee_shop_name']); ?></td>
                                    <td><?php echo htmlspecialchars($rec['coffee_shop_region']); ?></td>
                                    <td><?php echo htmlspecialchars($rec['edition_name']); ?></td>
                                    <td class="action-buttons">
                                        <a href="manage_recommendations.php?action=edit&id=<?php echo htmlspecialchars($rec['id']); ?>" title="Edit"><i class="fas fa-edit"></i> Edit</a>
                                        <form action="manage_recommendations.php" method="POST" style="display:inline;" onsubmit="return confirm('Anda yakin ingin menghapus rekomendasi ini?');">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="id" value="<?php echo htmlspecialchars($rec['id']); ?>">
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
</body>
</html>