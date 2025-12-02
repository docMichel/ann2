<?php
require_once __DIR__ . '/auth.php';

if (Auth::isAuthenticated()) {
    header('Location: ../index.php');
    exit;
}

$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';

    $result = Auth::login($email, $password);

    if ($result['success']) {
        header('Location: ../index.php');
        exit;
    }

    $message = $result['message'] ?? 'Identifiants incorrects';
    $messageType = $result['status'] === 'pending' ? 'warning' : 'error';
}
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Connexion</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .login-container {
            background: #2a2a2a;
            padding: 40px;
            border-radius: 12px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.5);
            width: 100%;
            max-width: 400px;
        }

        h1 {
            color: #4a9eff;
            font-size: 24px;
            margin-bottom: 8px;
            text-align: center;
        }

        p {
            color: #888;
            font-size: 14px;
            text-align: center;
            margin-bottom: 30px;
        }

        .message {
            padding: 12px;
            border-radius: 6px;
            margin-bottom: 20px;
            font-size: 14px;
            text-align: center;
        }

        .message.error {
            background: #5a2828;
            border: 1px solid #8a3838;
            color: #ff6b6b;
        }

        .message.warning {
            background: #5a4a28;
            border: 1px solid #8a7a38;
            color: #ffb84d;
        }

        .form-group {
            margin-bottom: 20px;
        }

        label {
            display: block;
            color: #e0e0e0;
            margin-bottom: 8px;
            font-size: 14px;
        }

        input {
            width: 100%;
            padding: 12px 15px;
            background: #1e1e1e;
            border: 1px solid #3a3a3a;
            border-radius: 6px;
            color: #e0e0e0;
            font-size: 14px;
        }

        input:focus {
            outline: none;
            border-color: #4a9eff;
        }

        button {
            width: 100%;
            padding: 12px;
            background: #4a9eff;
            border: none;
            border-radius: 6px;
            color: white;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
        }

        button:hover {
            background: #3a8edf;
        }
    </style>
</head>

<body>
    <div class="login-container">
        <h1>📨 Messages Annonces.nc</h1>
        <p>Connexion avec vos identifiants annonces.nc</p>

        <?php if ($message): ?>
            <div class="message <?= $messageType ?>">
                <?= $messageType === 'warning' ? '⏳' : '❌' ?> <?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>

        <form method="POST">
            <div class="form-group">
                <label>Email annonces.nc</label>
                <input type="email" name="email" required autofocus>
            </div>

            <div class="form-group">
                <label>Mot de passe</label>
                <input type="password" name="password" required>
            </div>

            <button type="submit">Se connecter</button>
        </form>
    </div>
</body>

</html>