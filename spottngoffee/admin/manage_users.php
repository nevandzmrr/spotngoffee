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

// Ambil semua pengguna
$users = $pdo->query("SELECT id, username, role, created_at FROM users ORDER BY username ASC")->fetchAll(PDO::FETCH_ASSOC);

$editUser = null; // Untuk mode edit

// Tangani pengiriman formulir
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $id = $_POST['id'] ?? null;
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    $role = $_POST['role'] ?? 'user';

    if ($action === 'add') {
        if (empty($username) || empty($password)) {
            $message = 'Username dan password harus diisi.';
            $messageType = 'error';
        } else {
            // Cek apakah username sudah ada
            $stmtCheck = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = ?");
            $stmtCheck->execute([$username]);
            if ($stmtCheck->fetchColumn() > 0) {
                $message = 'Username sudah ada. Pilih username lain.';
                $messageType = 'error';
            } else {
                $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                try {
                    $stmt = $pdo->prepare("INSERT INTO users (username, password, role) VALUES (?, ?, ?)");
                    $stmt->execute([$username, $hashedPassword, $role]);
                    $message = 'User berhasil ditambahkan!';
                    $messageType = 'success';
                } catch (PDOException $e) {
                    $message = 'Gagal menambahkan user: ' . $e->getMessage();
                    $messageType = 'error';
                }
            }
        }
    } elseif ($action === 'edit' && $id) {
        if (empty($username)) { // Password bisa kosong jika tidak diubah
            $message = 'Username harus diisi.';
            $messageType = 'error';
        } else {
            try {
                // Cek apakah username sudah ada untuk user lain (saat edit)
                $stmtCheck = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = ? AND id != ?");
                $stmtCheck->execute([$username, $id]);
                if ($stmtCheck->fetchColumn() > 0) {
                    $message = 'Username sudah ada untuk user lain. Pilih username lain.';
                    $messageType = 'error';
                } else {
                    $updateFields = ['username = ?', 'role = ?'];
                    $updateParams = [$username, $role];

                    if (!empty($password)) {
                        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                        $updateFields[] = 'password = ?';
                        $updateParams[] = $hashedPassword;
                    }
                    $updateParams[] = $id; // ID sebagai parameter terakhir untuk WHERE clause

                    $stmt = $pdo->prepare("UPDATE users SET " . implode(', ', $updateFields) . " WHERE id = ?");
                    $stmt->execute($updateParams);

                    // Jika admin mengedit perannya sendiri dan mengubahnya menjadi non-admin
                    if ($id == $_SESSION['user_id'] && $role !== 'admin') {
                        // Logout diri sendiri karena tidak lagi admin
                        session_unset();
                        session_destroy();
                        header('Location: admin_login.php?message=' . urlencode('Peran Anda diubah. Silakan login kembali.') . '&type=info');
                        exit();
                    }

                    $message = 'User berhasil diperbarui!';
                    $messageType = 'success';
                }
            } catch (PDOException $e) {
                $message = 'Gagal memperbarui user: ' . $e->getMessage();
                $messageType = 'error';
            }
        }
    } elseif ($action === 'delete' && $id) {
        // Mencegah admin menghapus akunnya sendiri
        if ($id == $_SESSION['user_id']) {
            $message = 'Anda tidak bisa menghapus akun Anda sendiri.';
            $messageType = 'error';
        } else {
            try {
                $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
                $stmt->execute([$id]);
                $message = 'User berhasil dihapus!';
                $messageType = 'success';
            } catch (PDOException $e) {
                $message = 'Gagal menghapus user: ' . $e->getMessage();
                $messageType = 'error';
            }
        }
    }
    // Redirect untuk mencegah resubmisi formulir
    header("Location: manage_users.php?message=" . urlencode($message) . "&type=" . urlencode($messageType));
    exit();
}

// Tangani parameter GET untuk mode edit atau pesan
if (isset($_GET['action']) && $_GET['action'] === 'edit' && isset($_GET['id'])) {
    $id = $_GET['id'];
    $stmt = $pdo->prepare("SELECT id, username, role FROM users WHERE id = ?");
    $stmt->execute([$id]);
    $editUser = $stmt->fetch(PDO::FETCH_ASSOC);
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
    <title>Kelola Pengguna - Spot Ngoffee Admin</title>
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
        .form-group input[type="password"],
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
        <h1>Kelola Admin</h1>

        <?php if ($message): ?>
            <p class="message <?php echo $messageType; ?>"><?php echo htmlspecialchars($message); ?></p>
        <?php endif; ?>

        <div class="form-container">
            <h2><?php echo $editUser ? 'Edit Pengguna' : 'Tambah Admin Baru'; ?></h2>
            <form action="manage_users.php" method="POST">
                <input type="hidden" name="action" value="<?php echo $editUser ? 'edit' : 'add'; ?>">
                <?php if ($editUser): ?>
                    <input type="hidden" name="id" value="<?php echo htmlspecialchars($editUser['id']); ?>">
                <?php endif; ?>

                <div class="form-group">
                    <label for="username">Username:</label>
                    <input type="text" id="username" name="username" value="<?php echo htmlspecialchars($editUser['username'] ?? ''); ?>" required>
                </div>

                <div class="form-group">
                    <label for="password">Password <?php echo $editUser ? '(Kosongkan jika tidak diubah)' : ''; ?>:</label>
                    <input type="password" id="password" name="password" <?php echo $editUser ? '' : 'required'; ?>>
                </div>

                <div class="form-group">
                    <label for="role">Peran (Role):</label>
                    <select id="role" name="role" required>
                        <option value="admin" <?php echo ($editUser && $editUser['role'] === 'admin') ? 'selected' : ''; ?>>Admin</option>
                    </select>
                </div>

                <button type="submit" class="btn-submit"><?php echo $editUser ? 'Update Pengguna' : 'Tambah Pengguna'; ?></button>
                <?php if ($editUser): ?>
                    <a href="manage_users.php" class="btn-submit" style="background-color: #6c757d;">Batal</a>
                <?php endif; ?>
            </form>
        </div>

        <div class="list-container">
            <h2>Daftar Admin</h2>
            <?php if (empty($users)): ?>
                <p>Belum ada pengguna yang terdaftar.</p>
            <?php else: ?>
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>Username</th>
                                <th>Peran</th>
                                <th>Dibuat Pada</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($users as $user): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($user['username']); ?></td>
                                    <td><?php echo htmlspecialchars($user['role']); ?></td>
                                    <td><?php echo htmlspecialchars($user['created_at']); ?></td>
                                    <td class="action-buttons">
                                        <?php if ($user['id'] !== $_SESSION['user_id']): // Mencegah admin menghapus/mengedit akunnya sendiri secara langsung ?>
                                            <a href="manage_users.php?action=edit&id=<?php echo htmlspecialchars($user['id']); ?>" title="Edit"><i class="fas fa-edit"></i> Edit</a>
                                            <form action="manage_users.php" method="POST" style="display:inline;" onsubmit="return confirm('Anda yakin ingin menghapus pengguna ini?');">
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="id" value="<?php echo htmlspecialchars($user['id']); ?>">
                                                <button type="submit" title="Hapus"><i class="fas fa-trash-alt"></i> Hapus</button>
                                            </form>
                                        <?php else: ?>
                                            <span style="color: #999;">(Akun Anda)</span>
                                            <a href="manage_users.php?action=edit&id=<?php echo htmlspecialchars($user['id']); ?>" title="Edit" style="background-color: #007bff; color: white;">Edit Diri Sendiri</a>
                                        <?php endif; ?>
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