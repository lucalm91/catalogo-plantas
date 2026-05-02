<?php
session_start();
$error = "";
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = trim($_POST["username"]);
    $password = trim($_POST["password"]);
    // Credenciales fijas para este ejemplo
    if (
        ($username === "Luca" && $password === "plantas") ||
        ($username === "Ale" && $password === "1234")
    ) {
        $_SESSION["user"] = $username;
        header("Location: index.php");
        exit;
    } else {
        $error = "Usuario o contraseña incorrectos.";
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
  <title>Login - Catálogo de Plantas</title>
  <style>
    :root {
      --primary: #58a45c;
      --primary-dark: #458a49;
      --background: #f5fff5;
      --text: #333333;
      --error: #d9534f;
      --shadow: rgba(0, 0, 0, 0.1);
    }
    
    * {
      box-sizing: border-box;
      margin: 0;
      padding: 0;
      -webkit-tap-highlight-color: transparent;
    }
    
    html {
      height: 100%;
      width: 100%;
      overflow: hidden;
    }
    
    body {
      font-family: 'Helvetica Neue', Arial, sans-serif;
      background-color: var(--background);
      color: var(--text);
      display: flex;
      align-items: center;
      justify-content: center;
      height: 100%;
      width: 100%;
      overflow: hidden;
      position: fixed;
      touch-action: manipulation;
    }
    
    .login-container {
      background-color: white;
      width: 100%;
      max-width: 400px;
      padding: 30px 25px;
      border-radius: 12px;
      box-shadow: 0 8px 24px var(--shadow);
      margin: 0 20px;
    }
    
    .login-header {
      text-align: center;
      margin-bottom: 30px;
    }
    
    .login-header h2 {
      font-size: 28px;
      color: var(--primary);
      margin-bottom: 8px;
    }
    
    .login-header p {
      font-size: 16px;
      color: #666;
    }
    
    .form-group {
      margin-bottom: 24px;
    }
    
    .form-group label {
      display: block;
      font-size: 14px;
      margin-bottom: 8px;
      color: #555;
      font-weight: 500;
    }
    
    .form-control {
      width: 100%;
      padding: 14px 16px;
      border: 1px solid #ddd;
      border-radius: 6px;
      font-size: 16px;
      transition: border-color 0.2s, box-shadow 0.2s;
      background-color: #f9f9f9;
    }
    
    .form-control:focus {
      border-color: var(--primary);
      box-shadow: 0 0 0 3px rgba(88, 164, 92, 0.2);
      outline: none;
      background-color: white;
    }
    
    .btn-login {
      width: 100%;
      padding: 14px;
      background-color: var(--primary);
      color: white;
      border: none;
      border-radius: 6px;
      font-size: 16px;
      font-weight: 500;
      cursor: pointer;
      transition: background-color 0.2s, transform 0.1s;
    }
    
    .btn-login:hover {
      background-color: var(--primary-dark);
    }
    
    .btn-login:active {
      transform: scale(0.98);
    }
    
    .error-message {
      color: var(--error);
      background-color: rgba(217, 83, 79, 0.1);
      border-left: 3px solid var(--error);
      padding: 10px 15px;
      margin-bottom: 20px;
      border-radius: 4px;
      font-size: 14px;
    }
    
    .back-link {
      text-align: center;
      margin-top: 20px;
      font-size: 14px;
    }
    
    .back-link a {
      color: var(--primary);
      text-decoration: none;
      transition: color 0.2s;
    }
    
    .back-link a:hover {
      color: var(--primary-dark);
      text-decoration: underline;
    }
    
    @media (max-width: 480px) {
      .login-container {
        padding: 25px 20px;
        margin: 0 10px;
        max-height: 95%;
      }
      
      .login-header h2 {
        font-size: 24px;
      }
      
      .form-group {
        margin-bottom: 20px;
      }
      
      .form-control {
        padding: 12px 14px;
      }
    }
    
    /* iOS specific fixes */
    @supports (-webkit-touch-callout: none) {
      body {
        /* iOS specific styles */
        height: -webkit-fill-available;
      }
      
      .login-container {
        max-height: 90vh; /* Safe value for iOS */
      }
    }
  </style>
</head>
<body>
  <div class="login-container">
    <div class="login-header">
      <h2>Catálogo de Plantas</h2>
      <p>Iniciar sesión para editar</p>
    </div>
    
    <?php if ($error): ?>
      <div class="error-message">
        <?php echo $error; ?>
      </div>
    <?php endif; ?>
    
    <form method="post" action="login.php">
      <div class="form-group">
        <label for="username">Usuario</label>
        <input type="text" name="username" id="username" class="form-control" required autofocus>
      </div>
      
      <div class="form-group">
        <label for="password">Contraseña</label>
        <input type="password" name="password" id="password" class="form-control" required>
      </div>
      
      <button type="submit" class="btn-login">Iniciar Sesión</button>
    </form>
    
    <div class="back-link">
      <a href="index.php">Volver al catálogo</a>
    </div>
  </div>
  <script>
    // iOS-specific viewport fix
    document.addEventListener('DOMContentLoaded', function() {
      // Prevent scroll on iOS
      document.body.addEventListener('touchmove', function(e) {
        e.preventDefault();
      }, { passive: false });
      
      // Fix for iOS viewport height issues
      function fixIOSViewport() {
        const vh = window.innerHeight * 0.01;
        document.documentElement.style.setProperty('--vh', `${vh}px`);
      }
      
      fixIOSViewport();
      window.addEventListener('resize', fixIOSViewport);
      window.addEventListener('orientationchange', fixIOSViewport);
    });
  </script>
</body>
</html>
