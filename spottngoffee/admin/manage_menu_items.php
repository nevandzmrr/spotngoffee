<?php
session_start();
require_once __DIR__ . '/../config/db.php'; // Pastikan path ini benar

// Auth check
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: admin_login.php');
    exit();
}

$message = '';
$messageType = '';

// Ambil semua kedai kopi untuk mengisi dropdown
$coffeeShops = $pdo->query("SELECT id, name FROM coffee_shops ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);

// --- Penentuan selectedShopId yang Tepat ---
// Prioritas: 1. Dari POST (setelah submit form), 2. Dari GET (link/dropdown), 3. Null (default awal)
$selectedShopId = $_POST['coffee_shop_id'] ?? $_GET['shop_id'] ?? null;

$menuItems = []; // Inisialisasi array menuItems
$editMenuItem = null; // Ini akan menampung data item jika dalam mode edit

// Definisi kategori menu yang diizinkan
$menuCategories = ['Makanan', 'Minuman', 'Dessert', 'Snack'];


// --- TANGANI POST REQUEST (ADD, EDIT, DELETE) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $id = $_POST['id'] ?? null; // ID item menu untuk edit/delete
    $coffee_shop_id_from_post = $_POST['coffee_shop_id'] ?? null; // ID kedai kopi dari form POST (digunakan untuk redirect)
    $name = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');

    // VALIDASI DAN FORMAT HARGA (KELIPATAN 100)
    $price_input = filter_var($_POST['price'] ?? '', FILTER_VALIDATE_FLOAT); // Gunakan FLOAT untuk akurasi input
    $price = null;
    if ($price_input !== false) {
        $price = (int)(round($price_input / 100) * 100); // Bulatkan ke kelipatan 100
    }

    $tempMessage = ''; // Untuk pesan error harga sementara
    $tempMessageType = '';

    // Tambahkan validasi jika harga tidak dalam kelipatan 100
    if ($price_input !== false && $price_input % 5 !== 0) {
        $tempMessage = "Harga harus kelipatan 100. Nilai dibulatkan ke " . number_format($price, 0, ',', '.');
        $tempMessageType = 'error';
    }

    $category = trim($_POST['category'] ?? '');

    // --- LOGIKA UTAMA PENANGANAN AKSI POST ---
    if ($action === 'delete') {
        if ($id) {
            try {
                $stmt = $pdo->prepare("DELETE FROM menu_items WHERE id = ?");
                $stmt->execute([$id]);
                $message = 'Item menu berhasil dihapus!';
                $messageType = 'success';
            } catch (PDOException $e) {
                $message = 'Gagal menghapus item menu: ' . $e->getMessage();
                $messageType = 'error';
            }
        } else {
            $message = 'ID item menu tidak ditemukan untuk penghapusan.';
            $messageType = 'error';
        }
    } elseif ($action === 'add' || $action === 'edit') {
        // Logika untuk menambah atau mengedit item menu
        if ($tempMessageType !== 'error') { // Lanjutkan jika tidak ada error harga
            // Validasi input umum untuk add/edit
            if (empty($coffee_shop_id_from_post) || empty($name) || $price === null || empty($category)) { // Cek price === null karena sudah difilter/bulatkan
                $message = 'Kedai Kopi, Nama Item, Harga, dan Kategori harus diisi dengan benar.';
                $messageType = 'error';
            } elseif (!in_array($category, $menuCategories)) {
                $message = 'Kategori tidak valid.';
                $messageType = 'error';
            } else {
                // Convert empty description to null
                $description = $description === '' ? null : $description;

                try {
                    if ($action === 'add') {
                        $stmt = $pdo->prepare("INSERT INTO menu_items (coffee_shop_id, name, description, price, category) VALUES (?, ?, ?, ?, ?)");
                        $stmt->execute([$coffee_shop_id_from_post, $name, $description, $price, $category]);
                        $message = 'Item menu berhasil ditambahkan!';
                        $messageType = 'success';
                    } elseif ($action === 'edit') {
                        if ($id) { // Pastikan ID ada untuk edit
                            $stmt = $pdo->prepare("UPDATE menu_items SET coffee_shop_id = ?, name = ?, description = ?, price = ?, category = ? WHERE id = ?");
                            $stmt->execute([$coffee_shop_id_from_post, $name, $description, $price, $category, $id]);
                            $message = 'Item menu berhasil diperbarui!';
                            $messageType = 'success';
                        } else {
                            $message = 'ID item menu tidak ditemukan untuk pembaruan.';
                            $messageType = 'error';
                        }
                    }
                } catch (PDOException $e) {
                    $message = 'Terjadi kesalahan database: ' . $e->getMessage();
                    $messageType = 'error';
                }
            }
        } else {
            // Jika ada error harga, gunakan pesan dan tipe dari $tempMessage
            $message = $tempMessage;
            $messageType = $tempMessageType;
        }
    }

    // Redirect setelah POST request selesai
    // Penting: gunakan coffee_shop_id_from_post agar redirect kembali ke kedai kopi yang sama
    header("Location: manage_menu_items.php?shop_id=" . urlencode($coffee_shop_id_from_post) . "&message=" . urlencode($message) . "&type=" . urlencode($messageType));
    exit();
}

// --- TANGANI GET REQUEST (MODE EDIT atau MENAMPILKAN PESAN) ---
// Bagian ini hanya berjalan saat halaman dimuat pertama kali atau setelah klik link GET
if (isset($_GET['action']) && $_GET['action'] === 'edit' && isset($_GET['id'])) {
    $id = $_GET['id'];
    $stmt = $pdo->prepare("SELECT * FROM menu_items WHERE id = ?");
    $stmt->execute([$id]);
    $editMenuItem = $stmt->fetch(PDO::FETCH_ASSOC); // Data item menu untuk form edit

    if (!$editMenuItem) {
        // Jika item tidak ditemukan, batalkan mode edit dan set pesan error
        $message = 'Item menu tidak ditemukan.';
        $messageType = 'error';
        $editMenuItem = null; // Pastikan ini null agar form ditampilkan sebagai 'Add New'
    } else {
        // Jika item ditemukan, pastikan $selectedShopId sesuai dengan item yang diedit
        $selectedShopId = $editMenuItem['coffee_shop_id'];
    }
}

// Ambil pesan dari URL jika ada (setelah redirect dari POST)
if (isset($_GET['message'])) {
    $message = htmlspecialchars($_GET['message']);
    $messageType = htmlspecialchars($_GET['type'] ?? 'info');
}

// --- PENGAMBILAN DATA MENU ITEMS UNTUK TAMPILAN ---
// Blok ini harus selalu dijalankan jika $selectedShopId sudah ada
// untuk memastikan daftar menu selalu terisi dengan benar.
if ($selectedShopId) {
    $stmt = $pdo->prepare("SELECT * FROM menu_items WHERE coffee_shop_id = ? ORDER BY category, name ASC");
    $stmt->execute([$selectedShopId]);
    $menuItems = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// --- AKHIR LOGIKA PHP ---

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Item Menu - Spot Ngoffee Admin</title>
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

        .form-group input[type="text"],
        .form-group input[type="number"],
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
            min-height: 60px;
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
        .select-coffeeshop {
            margin-bottom: 20px;
            padding: 15px;
            border: 1px solid #ddd;
            border-radius: 8px;
            background-color: #f8f8f8;
        }
        .select-coffeeshop label {
            font-weight: bold;
            margin-right: 10px;
            color: #5C4033;
        }
        .select-coffeeshop select {
            padding: 8px;
            border-radius: 4px;
            border: 1px solid #ccc;
            width: 200px;
        }
        .select-coffeeshop button {
            padding: 8px 15px;
            background-color: #7B3F00;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            margin-left: 10px;
        }
        .select-coffeeshop button:hover {
            background-color: #8B4513;
        }

    </style>
</head>
<body>
    <?php include 'admin_sidebar.php'; ?>

    <div class="main-content">
        <h1>Kelola Item Menu</h1>

        <?php if ($message): ?>
            <p class="message <?php echo $messageType; ?>"><?php echo htmlspecialchars($message); ?></p>
        <?php endif; ?>

        <div class="select-coffeeshop">
            <form action="manage_menu_items.php" method="GET">
                <label for="shop_id_selector">Pilih Kedai Kopi:</label>
                <select id="shop_id_selector" name="shop_id" onchange="this.form.submit()">
                    <option value="" <?php echo ($selectedShopId === null) ? 'selected' : ''; ?>>-- Pilih Kedai Kopi --</option>
                    <?php foreach ($coffeeShops as $shop): ?>
                        <option value="<?php echo htmlspecialchars($shop['id']); ?>"
                            <?php echo ($selectedShopId == $shop['id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($shop['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </form>
        </div>

        <?php
        // Hanya tampilkan form tambah/edit dan daftar menu jika kedai kopi sudah dipilih ($selectedShopId tidak null)
        if ($selectedShopId):
        ?>
            <div class="form-container">
                <h2><?php echo $editMenuItem ? 'Edit Item Menu' : 'Tambah Item Menu Baru'; ?></h2>
                <form action="manage_menu_items.php" method="POST">
                    <input type="hidden" name="action" value="<?php echo $editMenuItem ? 'edit' : 'add'; ?>">
                    <?php if ($editMenuItem): ?>
                        <input type="hidden" name="id" value="<?php echo htmlspecialchars($editMenuItem['id']); ?>">
                    <?php endif; ?>
                    <input type="hidden" name="coffee_shop_id" value="<?php echo htmlspecialchars($selectedShopId); ?>">

                    <div class="form-group">
                        <label for="name">Nama Item:</label>
                        <input type="text" id="name" name="name" value="<?php echo htmlspecialchars($editMenuItem['name'] ?? ''); ?>" required>
                    </div>

                    <div class="form-group">
                        <label for="description">Deskripsi:</label>
                        <textarea id="description" name="description"><?php echo htmlspecialchars($editMenuItem['description'] ?? ''); ?></textarea>
                    </div>

                    <div class="form-group">
                        <label for="price">Harga (Rp):</label>
                        <input type="number" id="price" name="price" value="<?php echo htmlspecialchars($editMenuItem['price'] ?? ''); ?>" step="100" required>
                    </div>

                    <div class="form-group">
                        <label for="category">Kategori:</label>
                        <select id="category" name="category" required>
                            <option value="">Pilih Kategori</option>
                            <?php foreach ($menuCategories as $cat): ?>
                                <option value="<?php echo htmlspecialchars($cat); ?>"
                                    <?php echo ($editMenuItem && $editMenuItem['category'] == $cat) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($cat); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <button type="submit" class="btn-submit"><?php echo $editMenuItem ? 'Update Item Menu' : 'Tambah Item Menu'; ?></button>
                    <?php if ($editMenuItem): ?>
                        <a href="manage_menu_items.php?shop_id=<?php echo htmlspecialchars($selectedShopId); ?>" class="btn-submit" style="background-color: #6c757d;">Batal</a>
                    <?php endif; ?>
                </form>
            </div>

            <div class="list-container">
                <h2>Daftar Item Menu untuk <?php
                    $currentShopName = 'Kedai Kopi Tidak Ditemukan';
                    foreach ($coffeeShops as $shop) {
                        if ($shop['id'] == $selectedShopId) {
                            $currentShopName = $shop['name'];
                            break;
                        }
                    }
                    echo htmlspecialchars($currentShopName);
                ?></h2>
                <?php if (empty($menuItems)): ?>
                    <p>Belum ada item menu untuk kedai kopi ini.</p>
                <?php else: ?>
                    <div class="table-container">
                        <table>
                            <thead>
                                <tr>
                                    <th>Nama Item</th>
                                    <th>Deskripsi</th>
                                    <th>Harga</th>
                                    <th>Kategori</th>
                                    <th>Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($menuItems as $item): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($item['name']); ?></td>
                                        <td><?php echo htmlspecialchars($item['description'] ?? '-'); ?></td>
                                        <td>Rp <?php echo number_format($item['price'], 0, ',', '.'); ?></td>
                                        <td><?php echo htmlspecialchars($item['category']); ?></td>
                                        <td class="action-buttons">
                                            <a href="manage_menu_items.php?action=edit&id=<?php echo htmlspecialchars($item['id']); ?>&shop_id=<?php echo htmlspecialchars($selectedShopId); ?>" title="Edit"><i class="fas fa-edit"></i> Edit</a>
                                            <form action="manage_menu_items.php" method="POST" style="display:inline;" onsubmit="return confirm('Anda yakin ingin menghapus item menu ini?');">
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="id" value="<?php echo htmlspecialchars($item['id']); ?>">
                                                <input type="hidden" name="coffee_shop_id" value="<?php echo htmlspecialchars($selectedShopId); ?>">
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
        <?php else: ?>
            <p>Silakan pilih kedai kopi dari dropdown di atas untuk mengelola item menunya.</p>
        <?php endif; ?>
    </div>
</body>
</html>