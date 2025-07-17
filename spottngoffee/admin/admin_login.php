<?php
session_start();
require_once __DIR__ . '/../config/db.php';

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';

    if (empty($username) || empty($password)) {
        $message = 'Username dan password harus diisi.';
    } else {
        $stmt = $pdo->prepare("SELECT id, username, password, role FROM users WHERE username = ?");
        $stmt->execute([$username]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && password_verify($password, $user['password'])) {
            if ($user['role'] === 'admin') {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['role'] = $user['role'];
                header('Location: admin_dashboard.php');
                exit();
            } else {
                $message = 'Anda tidak memiliki akses admin.';
            }
        } else {
            $message = 'Username atau password salah.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login Admin - Spot Ngoffee</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&family=Roboto+Mono:wght@400;500;700&display=swap" rel="stylesheet">
    <style>
        /* Variabel CSS (Root) */
        :root {
            --primary-dark-coffee: #4A3026;
            --primary-medium-coffee: #5C4033;
            --primary-light-coffee: #7B3F00;
            --accent-orange-brown: #B05C35;
            --accent-light-orange: #D4A373;
            --bg-light-cream: #FDF7E4; /* Warna dasar krem */
            --text-dark: #333;
            --text-medium: #666;
            --text-light: #FDF7E4; /* Untuk teks di atas warna gelap */
            --border-light: #ddd;
            --shadow-light: rgba(0,0,0,0.05);
            --shadow-medium: rgba(0,0,0,0.1);
            --shadow-heavy: rgba(0,0,0,0.2);

            --padding-sm: 10px;
            --padding-md: 20px;
            --padding-lg: 30px;
            --margin-sm: 10px;
            --margin-md: 20px;
            --margin-lg: 30px;

            --border-radius-sm: 5px;
            --border-radius-md: 8px;
            --border-radius-lg: 12px;
        }

        body {
            font-family: 'Poppins', sans-serif;
            margin: 0;
            /* Background dengan gambar kopi dan gradien */
            background: linear-gradient(rgba(0, 0, 0, 0.4), rgba(0, 0, 0, 0.6)), url('https://source.unsplash.com/featured/?coffee,cafe,beans') no-repeat center center fixed; /* Ganti URL gambar jika ada gambar spesifik */
            background-size: cover;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            color: var(--text-dark);
        }

        .login-container {
            background-color: rgba(255, 255, 255, 0.95); /* Sedikit transparan */
            padding: var(--padding-lg);
            border-radius: var(--border-radius-lg);
            box-shadow: 0 10px 30px var(--shadow-heavy); /* Bayangan lebih dramatis */
            width: 100%;
            max-width: 450px; /* Lebar lebih fleksibel */
            text-align: center;
            border: 1px solid var(--border-light);
            animation: fadeIn 0.8s ease-out; /* Animasi fade-in */
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .login-container h2 {
            color: var(--primary-dark-coffee); /* Warna kopi gelap untuk judul */
            margin-bottom: var(--margin-lg);
            font-weight: 700;
            font-size: 2.5em; /* Ukuran font lebih besar */
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 15px; /* Jarak antara ikon dan teks */
            text-shadow: 1px 1px 3px var(--shadow-light); /* Sedikit bayangan teks */
        }

        .login-container h2 i {
            font-size: 1.2em;
            color: var(--accent-orange-brown); /* Warna aksen untuk ikon */
        }

        .form-group {
            margin-bottom: var(--margin-md);
            text-align: left;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600; /* Lebih tebal */
            color: var(--primary-medium-coffee); /* Warna kopi sedang untuk label */
            font-size: 1.05em;
        }

        .form-group input[type="text"],
        .form-group input[type="password"] {
            width: calc(100% - (var(--padding-sm) * 2));
            padding: var(--padding-sm);
            border: 1px solid var(--border-light);
            border-radius: var(--border-radius-sm);
            font-size: 1.1em;
            box-sizing: border-box;
            background-color: #fcfcfc; /* Latar belakang input sedikit off-white */
            transition: border-color 0.3s ease, box-shadow 0.3s ease, background-color 0.3s ease;
        }

        .form-group input:focus {
            border-color: var(--accent-orange-brown); /* Border warna aksen saat fokus */
            box-shadow: 0 0 0 3px rgba(176, 92, 53, 0.25); /* Glow yang lebih lembut */
            background-color: #fff; /* Kembali putih bersih saat fokus */
            outline: none;
        }

        .btn-login {
            background-color: var(--accent-orange-brown);
            color: var(--text-light);
            padding: var(--padding-md) var(--padding-lg); /* Padding lebih besar */
            border: none;
            border-radius: var(--border-radius-md); /* Border radius lebih besar */
            cursor: pointer;
            font-size: 1.2em; /* Ukuran font tombol lebih besar */
            font-weight: 700; /* Sangat tebal */
            width: 100%;
            transition: background-color 0.3s ease, transform 0.2s ease, box-shadow 0.3s ease;
            margin-top: var(--margin-lg); /* Jarak lebih besar dari input */
            box-shadow: 0 4px 10px var(--shadow-medium); /* Bayangan untuk tombol */
            letter-spacing: 1px; /* Jarak antar huruf */
            text-transform: uppercase; /* Huruf kapital */
        }

        .btn-login:hover {
            background-color: var(--primary-light-coffee); /* Warna kopi lebih gelap saat hover */
            transform: translateY(-3px); /* Efek naik */
            box-shadow: 0 6px 15px var(--shadow-heavy); /* Bayangan lebih kuat saat hover */
        }

        .message {
            margin-top: var(--margin-md);
            padding: var(--padding-sm);
            border-radius: var(--border-radius-sm);
            font-weight: 500;
            font-size: 0.95em;
            border: 1px solid;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            animation: slideInFromTop 0.5s ease-out; /* Animasi muncul dari atas */
        }

        @keyframes slideInFromTop {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .message i {
            font-size: 1.2em;
        }

        .message.error {
            background-color: #ffe6e6;
            color: #dc3545;
            border-color: #dc3545;
        }

        .message.success { /* Meskipun login sukses akan redirect, tetap sertakan untuk konsistensi */
            background-color: #e6ffe6;
            color: #28a745;
            border-color: #28a745;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <h2><i class="fas fa-mug-hot"></i> Spot Ngoffee</h2>
        <p style="color: var(--text-medium); margin-bottom: var(--margin-lg); font-size: 1.1em;">Admin Panel Login</p>
        <?php if ($message): ?>
            <p class="message error"><i class="fas fa-exclamation-triangle"></i> <?php echo htmlspecialchars($message); ?></p>
        <?php endif; ?>
        <form action="admin_login.php" method="POST">
            <div class="form-group">
                <label for="username">Username:</label>
                <input type="text" id="username" name="username" required autocomplete="username">
            </div>
            <div class="form-group">
                <label for="password">Password:</label>
                <input type="password" id="password" name="password" required autocomplete="current-password">
            </div>
            <button type="submit" class="btn-login">Login</button>
        </form>
    </div>
</body>
</html>