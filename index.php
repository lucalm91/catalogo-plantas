<?php
session_start();
header("Cache-Control: no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");
require_once __DIR__ . '/includes/app.php';

// --- NUEVO: función para obtener el archivo de plantas del usuario ---
// Add missing getImageUrl function
function getImageUrl($imagePath) {
    return $imagePath . "?t=" . time();
}

function ui_icon(string $name): string {
    $icons = [
        'edit' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4Z"/></svg>',
        'trash' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 6h18"/><path d="M8 6V4h8v2"/><path d="M19 6l-1 14H6L5 6"/><path d="M10 11v5"/><path d="M14 11v5"/></svg>',
        'arrow-up' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 19V5"/><path d="m5 12 7-7 7 7"/></svg>',
        'arrow-down' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 5v14"/><path d="m19 12-7 7-7-7"/></svg>',
        'arrow-left' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M19 12H5"/><path d="m12 19-7-7 7-7"/></svg>',
        'arrow-right' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 12h14"/><path d="m12 5 7 7-7 7"/></svg>',
        'image' => '<svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="5" width="18" height="14" rx="2"/><circle cx="8.5" cy="10" r="1.5"/><path d="m21 15-5-5L5 19"/></svg>',
        'plus' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 5v14"/><path d="M5 12h14"/></svg>',
        'leaf' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 21c0-7 4-11 9-14-7-1-13 2-16 8 3 1 5 3 7 6Z"/><path d="M12 21c0-5-3-8-8-10"/></svg>',
    ];
    return $icons[$name] ?? '';
}

// --- NUEVO: mostrar home de bienvenida si no está logueado ---
if (!isset($_SESSION['user'])): ?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Bienvenido - Catálogo de Plantas</title>
  <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
  <link rel="stylesheet" href="assets/styles.css?v=<?php echo filemtime(__DIR__ . '/assets/styles.css'); ?>">
  <style>
    .welcome-home {
      max-width: 500px;
      margin: 80px auto 0 auto;
      background: #fff;
      border-radius: 14px;
      box-shadow: 0 6px 32px rgba(0,0,0,0.10);
      padding: 38px 28px 32px 28px;
      text-align: center;
    }
    .welcome-home h1 {
      font-size: 2.2em;
      color: #58a45c;
      margin-bottom: 18px;
      font-weight: 700;
    }
    .welcome-home p {
      font-size: 1.15em;
      color: #444;
      margin-bottom: 28px;
    }
    .welcome-home .btn-login {
      background: #58a45c;
      color: #fff;
      border: none;
      border-radius: 6px;
      font-size: 1.1em;
      padding: 13px 32px;
      font-weight: 600;
      cursor: pointer;
      transition: background 0.18s;
      text-decoration: none;
      display: inline-block;
    }
    .welcome-home .btn-login:hover { background: #458a49; }
    .welcome-home .logo-plant {
      width: 58px;
      height: 58px;
      margin-bottom: 18px;
      color: #3f7f43;
      background: #eaf7ea;
      border: 1px solid rgba(88, 164, 92, 0.25);
      border-radius: 16px;
      display: inline-flex;
      align-items: center;
      justify-content: center;
    }
    .welcome-home .logo-plant svg {
      width: 30px;
      height: 30px;
      fill: none;
      stroke: currentColor;
      stroke-width: 2;
      stroke-linecap: round;
      stroke-linejoin: round;
    }
  </style>
</head>
<body>
  <div class="welcome-home">
    <span class="logo-plant"><?php echo ui_icon('leaf'); ?></span>
    <h1>Bienvenido al Catálogo de Plantas</h1>
    <p>
      Gestiona y explora tu colección de plantas.<br>
      Inicia sesión para acceder a tu catálogo personalizado, añadir fotos, registrar cambios y mucho más.
    </p>
    <a href="login.php" class="btn-login">Iniciar sesión</a>
  </div>
</body>
</html>
<?php exit; endif;

// --- SOLO SI ESTÁ LOGUEADO SE MUESTRA EL CATÁLOGO ---
try {
    $plantas = app_fetch_plants(app_current_user());
} catch (Throwable $e) {
    http_response_code(500);
    echo "<h1>Error de base de datos</h1><p>" . htmlspecialchars($e->getMessage()) . "</p>";
    exit;
}

// --- NUEVO: obtener zonas dinámicamente según usuario ---
// Restaurar la lógica para Luca: mostrar siempre todas las zonas presentes en el JSON, incluyendo "Huerta" si hay plantas ahí.
$zonas = [];
foreach ($plantas as $planta) {
    $zona = $planta['zona'];
    if (!isset($zonas[$zona])) $zonas[$zona] = [];
    $zonas[$zona][] = $planta;
}

// --- ORDENAR zonas por orden_zona si existe, si no por aparición ---
$zonas_ordenadas = $zonas;
if (count($zonas) > 1) {
    $ordenes = [];
    foreach ($zonas as $z => $lista) {
        $ordenes[$z] = isset($lista[0]['orden_zona']) ? intval($lista[0]['orden_zona']) : 9999;
    }
    uksort($zonas_ordenadas, function($a, $b) use ($ordenes) {
        return $ordenes[$a] <=> $ordenes[$b];
    });
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Catálogo de Plantas</title>
  <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
  <link rel="icon" type="image/x-icon" href="favicon.ico">
  <link rel="preload" href="images/placeholder.jpg" as="image">
  <link rel="stylesheet" href="assets/styles.css?v=<?php echo filemtime(__DIR__ . '/assets/styles.css'); ?>">
</head>
<body>
  <header>
    <div class="site-title">Catálogo de Plantas</div>
    <div class="login-info">
      <?php if(isset($_SESSION['user'])): ?>
        <div class="dropdown">
          <div class="user-avatar" id="userDropdownToggle">
            <?php echo substr($_SESSION['user'], 0, 1); ?>
          </div>
          <div class="user-name">
            <?php echo $_SESSION['user']; ?>
          </div>
          <div class="dropdown-menu" id="userDropdownMenu">
            <a href="#"><?php echo $_SESSION['user']; ?></a>
            <a href="logout.php" class="logout">Cerrar sesión</a>
          </div>
        </div>
        <a href="logout.php" class="btn btn-logout">Cerrar sesión</a>
      <?php else: ?>
        <a href="login.php" id="login-button" class="btn btn-login" aria-label="Iniciar sesión">
          <svg class="login-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" role="img" aria-hidden="true">
            <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
            <circle cx="12" cy="7" r="4"></circle>
          </svg>
          <span class="login-text">Iniciar sesión</span>
        </a>
      <?php endif; ?>
    </div>
  </header>

  <div id="page-wrapper">
    <?php
    // --- SOLO PARA ALE: mostrar solo zonas Interior/Exterior, pero mostrar también la zona recién creada si existe al menos una planta en ella
    $zonas_a_mostrar = $zonas_ordenadas;
    if (isset($_SESSION['user']) && $_SESSION['user'] === 'Ale') {
        $zonas_a_mostrar = [];
        foreach ($zonas_ordenadas as $zona => $lista) {
            if ($zona === 'Interior' || $zona === 'Exterior' || count($lista) > 0) {
                $zonas_a_mostrar[$zona] = $lista;
            }
        }
        // Solo mostrar zonas con al menos una planta
        $zonas_a_mostrar = array_filter($zonas_a_mostrar, function($lista) { return count($lista) > 0; });
    }
    $zonas_keys = array_keys($zonas_a_mostrar);
    foreach ($zonas_keys as $i => $zona):
        $listaPlantas = $zonas_a_mostrar[$zona];
        // --- ORDENAR plantas por campo 'orden' si existe, si no por aparición ---
        usort($listaPlantas, function($a, $b) {
            $oa = isset($a['orden']) ? intval($a['orden']) : 9999;
            $ob = isset($b['orden']) ? intval($b['orden']) : 9999;
            return $oa <=> $ob;
        });
        $banner = !empty($listaPlantas) && !empty($listaPlantas[0]['imagenes']) ? $listaPlantas[0]['imagenes'][0] : 'images/placeholder.jpg';
        
        // Detectar imagen de zona en varios formatos (priorizando WebP)
        $zoneHash = md5($zona);
        $foundZoneImg = false;
        foreach (["webp", "jpg", "jpeg", "png", "gif"] as $ext) {
            $potentialImg = "images/zone_{$zoneHash}.{$ext}";
            if (file_exists($potentialImg)) {
                $banner = $potentialImg;
                $foundZoneImg = true;
                break;
            }
        }
    ?>
    <div class="zone-section" data-zone="<?php echo htmlspecialchars($zona); ?>">
      <div class="scroll-button-container left">
        <button class="scroll-button scroll-left"></button>
      </div>
      <div class="scroll-button-container right">
        <button class="scroll-button scroll-right"></button>
      </div>
      <div class="zone-banner" style="background-image: url('<?php echo htmlspecialchars(getImageUrl($banner)); ?>');">
        <div class="banner-overlay"></div>
        <h2 class="zone-title"><?php echo htmlspecialchars($zona); ?></h2>
        
        <?php if(isset($_SESSION['user'])): ?>
        <div class="zone-banner-controls">
          <button class="zone-control-btn rename-zone-btn" data-zone="<?php echo htmlspecialchars($zona); ?>" title="Renombrar zona" aria-label="Renombrar zona"><?php echo ui_icon('edit'); ?></button>
          <button class="zone-control-btn delete-zone-btn" data-zone="<?php echo htmlspecialchars($zona); ?>" title="Eliminar zona" aria-label="Eliminar zona"><?php echo ui_icon('trash'); ?></button>
          <button class="zone-control-btn move-zone-up-btn" data-zone="<?php echo htmlspecialchars($zona); ?>" title="Mover zona arriba" aria-label="Mover zona arriba" <?php if($i === 0) echo 'disabled'; ?>><?php echo ui_icon('arrow-up'); ?></button>
          <button class="zone-control-btn move-zone-down-btn" data-zone="<?php echo htmlspecialchars($zona); ?>" title="Mover zona abajo" aria-label="Mover zona abajo" <?php if($i === count($zonas_keys)-1) echo 'disabled'; ?>><?php echo ui_icon('arrow-down'); ?></button>
          <form class="zone-upload-form" method="post" enctype="multipart/form-data" data-zone="<?php echo htmlspecialchars($zona); ?>" style="display:inline;">
            <label class="zone-control-btn zone-upload-btn" title="Cambiar imagen de la zona" aria-label="Cambiar imagen de la zona">
              <input type="file" name="zone_image" accept="image/*" style="display:none;">
              <?php echo ui_icon('image'); ?>
            </label>
          </form>
        </div>
        <?php endif; ?>
      </div>
      
      <div class="card-container-wrapper">
        <div class="card-container" data-zone="<?php echo htmlspecialchars($zona); ?>">
          <?php foreach ($listaPlantas as $j => $planta): ?>
            <div class="plant-item-column"> <!-- New wrapper -->
              <div class="plant-card" data-plant-num="<?php echo $planta['num']; ?>">
                <div class="plant-card-image">
                  <img 
                    loading="lazy" 
                    src="<?php echo (!empty($planta['imagenes'][0]) && file_exists($planta['imagenes'][0])) ? $planta['imagenes'][0] : 'images/placeholder.jpg'; ?>" 
                    alt="<?php echo $planta['identificacion']; ?>"
                    data-src="<?php echo (!empty($planta['imagenes'][0]) && file_exists($planta['imagenes'][0])) ? $planta['imagenes'][0] : 'images/placeholder.jpg'; ?>"
                  >
                </div>
                <h3 class="plant-title"><?php echo $planta['identificacion']; ?></h3>
                <p class="plant-descripcion"><?php echo $planta['descripcion']; ?></p>
                <p class="plant-estado-container"><strong>Estado:</strong> <span class="plant-estado"><?php echo $planta['estado']; ?></span></p>
                <p><strong>Riego:</strong> <span class="plant-riego"><?php echo $planta['riego']; ?></span> <span class="sistema-container">| <strong>Sistema:</strong> <span class="plant-sistema"><?php echo $planta['sistema_riego']; ?></span></span></p>
              </div>
              <?php if(isset($_SESSION['user'])): ?>
              <div class="plant-action-controls">
                <button class="move-plant-left-btn" data-plant-num="<?php echo $planta['num']; ?>" data-zone="<?php echo htmlspecialchars($zona); ?>" title="Mover planta a la izquierda" aria-label="Mover planta a la izquierda" <?php if($j === 0) echo 'disabled'; ?>><?php echo ui_icon('arrow-left'); ?></button>
                <button class="move-plant-right-btn" data-plant-num="<?php echo $planta['num']; ?>" data-zone="<?php echo htmlspecialchars($zona); ?>" title="Mover planta a la derecha" aria-label="Mover planta a la derecha" <?php if($j === count($listaPlantas)-1) echo 'disabled'; ?>><?php echo ui_icon('arrow-right'); ?></button>
                <button class="delete-plant-btn" data-plant-num="<?php echo $planta['num']; ?>" title="Eliminar planta" aria-label="Eliminar planta"><?php echo ui_icon('trash'); ?></button>
              </div>
              <?php endif; ?>
            </div> <!-- End plant-item-column -->
          <?php endforeach; ?>
          <?php if(isset($_SESSION['user'])): ?>
            <div class="plant-item-column"> <!-- New wrapper for add-plant-card -->
              <div class="plant-card add-plant-card" data-zone="<?php echo htmlspecialchars($zona); ?>">
                <span class="add-plant-icon"><?php echo ui_icon('plus'); ?></span>
                <span class="add-plant-label">Añadir planta</span>
              </div>
            </div> <!-- End plant-item-column for add-plant-card -->
          <?php endif; ?>
        </div>
      </div>
      
      <div class="scroll-indicator">
        <img src="images/swipe.png" alt="Deslizar horizontalmente">
        <span>Desliza para ver más plantas</span>
      </div>
    </div> <!-- End zone-section -->
    <?php endforeach; ?>
    <!-- Move the add zone button here, centered -->
    <?php if(isset($_SESSION['user'])): ?>
      <div style="margin: 30px 0 20px 0; text-align: center;">
        <button id="add-zone-btn" style="background:#58a45c;color:#fff;border:none;padding:12px 28px;border-radius:7px;font-size:17px;cursor:pointer;font-weight:600;">+ Añadir zona</button>
      </div>
    <?php endif; ?>
  </div> <!-- End page-wrapper -->
  
  <div id="plant-modal" class="modal">
    <div class="modal-content">
      <!-- Keep the X button at the top right -->
      <button id="close-x-button" class="close-x-button">&times;</button>
      
      <!-- Plant navigation buttons -->
      <div class="plant-navigation">
        <button id="prev-plant" class="nav-button">&larr; Anterior</button>
        <span class="plant-number">Planta <span id="current-plant-number"></span></span>
        <button id="next-plant" class="nav-button">Siguiente &rarr;</button>
      </div>
      
      <h2 class="modal-title" id="modal-title"
          contenteditable="<?php echo isset($_SESSION['user']) ? 'true' : 'false'; ?>"
          data-field="identificacion"></h2>
      <div class="gallery">
        <!-- Replace arrow spans with empty divs that will be styled with ::after pseudo-elements -->
        <div class="arrow left" aria-label="Imagen anterior"></div>
        <img id="modal-image" src="images/placeholder.jpg" alt="Galería">
        <div class="arrow right" aria-label="Imagen siguiente"></div>
        <div class="zoom-instruction desktop-only">
          <span>Pasa el cursor para hacer zoom</span>
        </div>
      </div>
      <?php if(isset($_SESSION['user'])): ?>
      <div class="modal-image-controls-icons">
        <label class="icon-btn minimal-btn" title="Añadir imagen">
          <input type="file" id="modal-upload" class="hidden-input" accept="image/*">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#58a45c" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21,15 16,10 5,21"/></svg>
          <span class="icon-label">Añadir</span>
        </label>
        <button class="icon-btn minimal-btn" id="btn-delete" title="Eliminar imagen">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#d9534f" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3,6 5,6 21,6"/><path d="m 19,6 v 14 a 2,2 0 0 1 -2,2 H 7 a 2,2 0 0 1 -2,-2 V 6 m 3,0 V 4 a 2,2 0 0 1 2,-2 h 4 a 2,2 0 0 1 2,2 v 2"/></svg>
          <span class="icon-label">Eliminar</span>
        </button>
      </div>
      <?php endif; ?>
      
      <div id="modal-details">
        <p><strong>Estado:</strong> <span contenteditable="<?php echo isset($_SESSION['user']) ? 'true' : 'false'; ?>" data-field="estado"></span></p>
        <p><strong>Descripción:</strong> <span contenteditable="<?php echo isset($_SESSION['user']) ? 'true' : 'false'; ?>" data-field="descripcion"></span></p>
        <div class="modal-zona-riego-row">
          <span><strong>Zona:</strong> <span contenteditable="<?php echo isset($_SESSION['user']) ? 'true' : 'false'; ?>" data-field="zona"></span></span>
          <span class="separator">|</span>
          <span><strong>Riego:</strong> <span contenteditable="<?php echo isset($_SESSION['user']) ? 'true' : 'false'; ?>" data-field="riego"></span></span>
          <span class="separator sistema-separator">|</span>
          <span class="sistema-label"><strong>Sistema:</strong> <span contenteditable="<?php echo isset($_SESSION['user']) ? 'true' : 'false'; ?>" data-field="sistema_riego" class="sistema-value"></span></span>
        </div>
      </div>
      
      <?php if(isset($_SESSION['user'])): ?>
      <div class="modal-ia-section">
        <h4 class="ia-section-title">Funciones de Inteligencia Artificial</h4>
        <div class="modal-ia-controls">
          <button id="btn-ai-analyze" class="icon-btn minimal-btn" title="Analizar con IA">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#66b58d" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="10" rx="2" ry="2"/><circle cx="12" cy="5" r="2"/><path d="m 12,7 v 4"/><line x1="5" y1="16" x2="5" y2="16"/><line x1="19" y1="16" x2="19" y2="16"/></svg>
            <span class="icon-label">Analizar</span>
          </button>
          <button id="btn-ai-chat" class="icon-btn minimal-btn" title="Chat con IA">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#58a45c" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m3 21 1.9-5.7a8.5 8.5 0 1 1 3.8 3.8z"/></svg>
            <span class="icon-label">Chat IA</span>
          </button>
        </div>
      </div>
      <?php endif; ?>
      
      <!-- Add history table before the buttons -->
      <div class="history-section">
        <h3>Historial de cambios</h3>
        <div class="history-table-container">
          <table id="history-table">
            <thead>
              <tr>
                <th>Fecha</th>
                <?php if(isset($_SESSION['user'])): ?>
                  <th>User</th>
                <?php endif; ?>
                <th>Acción</th>
                <th>Detalles</th>
                <?php if(isset($_SESSION['user'])): ?>
                  <th class="delete-column"><span aria-hidden="true"></span></th>
                <?php endif; ?>
              </tr>
            </thead>
            <tbody id="history-tbody">
              <!-- Will be populated dynamically -->
            </tbody>
          </table>
        </div>
      </div>
      
      <div class="modal-buttons">
        <button id="btn-back">Volver al Catálogo</button>
      </div>
    </div>
  </div>
  
  <!-- Improved mobile fullscreen viewer structure -->
  <div id="fullscreen-viewer" class="fullscreen-viewer">
    <div class="fullscreen-img-container">
      <img id="fullscreen-image" src="images/placeholder.jpg" alt="Imagen ampliada">
    </div>
    <div class="fullscreen-controls">
      <button id="fullscreen-close" class="fullscreen-btn close-btn" aria-label="Cerrar">&times;</button>
      <div class="fullscreen-navigation">
        <button id="fullscreen-prev" class="fullscreen-btn nav-btn" aria-label="Anterior">&larr;</button>
        <button id="fullscreen-next" class="fullscreen-btn nav-btn" aria-label="Siguiente">&rarr;</button>
      </div>
    </div>
  </div>

  <script>
    // Pass PHP variables to JavaScript
    const isLoggedIn = <?php echo isset($_SESSION['user']) ? 'true' : 'false'; ?>;
    function getImageUrl(imagePath) { // Helper function for JS side cache busting if needed
        return imagePath + "?t=" + new Date().getTime();
    }
    
    <?php if(isset($_SESSION['user'])): ?>
    // JS para subir imagen de zona
    document.querySelectorAll('.zone-upload-form').forEach(form => {
      const input = form.querySelector('input[type="file"]');
      input.addEventListener('change', function(e) {
        if (!this.files.length) return;
        const zona = form.getAttribute('data-zone');
        const fd = new FormData();
        fd.append('zone_image', this.files[0]);
        fd.append('zona', zona);
        fetch('api/zones/upload-image.php', { method: 'POST', body: fd })
          .then(r => r.json())
          .then(data => {
            if (data.success && data.image) {
              // Actualiza el banner visualmente
              const bannerElement = form.closest('.zone-banner');
              if (bannerElement) {
                bannerElement.style.backgroundImage = "url('" + getImageUrl(data.image) + "')";
              }
            } else {
              alert(data.error || "Error al subir la imagen de la zona.");
            }
          });
      });
    });
    <?php endif; ?>

    document.addEventListener('DOMContentLoaded', function() {
      if (typeof isLoggedIn !== 'undefined' && isLoggedIn) {
                // ... existing event listeners for plant actions (move, delete, add) ...
        document.querySelectorAll('.card-container').forEach(function(container) {
          container.addEventListener('click', function(e) {
            const target = e.target;
            let plantCardElement; // To be used for opening modal if it's a plant card click

            // Try to find the closest action button that was clicked
            const actionButton = target.closest('.move-plant-left-btn, .move-plant-right-btn, .delete-plant-btn');
            const addPlantCardButton = target.closest('.add-plant-card');

            if (actionButton) {
              e.stopPropagation(); 
              e.preventDefault();  

              const plantNum = actionButton.getAttribute('data-plant-num');
              const zona = actionButton.getAttribute('data-zone'); 

              if (actionButton.classList.contains('move-plant-left-btn')) {
                if (!actionButton.disabled) reorderPlantUI(plantNum, zona, 'left');
              } else if (actionButton.classList.contains('move-plant-right-btn')) {
                if (!actionButton.disabled) reorderPlantUI(plantNum, zona, 'right');
              } else if (actionButton.classList.contains('delete-plant-btn')) {
                if (!plantNum) return;
                if (!confirm('¿Seguro que deseas eliminar esta planta?')) return;
                
                fetch('api/plants/delete.php', {
                  method: 'POST',
                  headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                  body: new URLSearchParams({ num: plantNum })
                })
                .then(r => r.json())
                .then(data => {
                  if (data.success) {
                    const columnToRemove = actionButton.closest('.plant-item-column');
                    const parentContainer = columnToRemove ? columnToRemove.parentNode : null;
                    if (columnToRemove) columnToRemove.remove();
                    if (parentContainer && parentContainer.classList.contains('card-container')) {
                         updatePlantActionButtons(parentContainer);
                    }
                  } else {
                    alert(data.error || 'Error al eliminar la planta.');
                  }
                });
              }
              return; 
            }

            if (addPlantCardButton) {
              e.stopPropagation(); 
              const zona = addPlantCardButton.getAttribute('data-zone') || '';
              fetch('api/plants/add.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: new URLSearchParams({
                  zona: zona,
                  identificacion: 'Nueva planta',
                  descripcion: 'Descripción pendiente',
                  estado: 'Estado pendiente',
                  riego: '',
                  sistema_riego: ''
                })
              })
              .then(r => r.json())
              .then(data => {
                if (data.success && data.plant) {
                  const newPlantData = data.plant;
                  const targetContainer = document.querySelector(`.card-container[data-zone="${CSS.escape(newPlantData.zona)}"]`);
                  const addPlantCardColumn = targetContainer ? targetContainer.querySelector('.add-plant-card').closest('.plant-item-column') : null;

                  if (targetContainer && addPlantCardColumn) {
                    const newPlantColumn = document.createElement('div');
                    newPlantColumn.className = 'plant-item-column';
                    
                    const imageUrl = (newPlantData.imagenes && newPlantData.imagenes.length > 0 && newPlantData.imagenes[0]) ? newPlantData.imagenes[0] : 'images/placeholder.jpg';

                    newPlantColumn.innerHTML = `
                      <div class="plant-card" data-plant-num="${newPlantData.num}">
                        <div class="plant-card-image">
                          <img loading="lazy" src="${imageUrl}" alt="${newPlantData.identificacion}" data-src="${imageUrl}">
                        </div>
                        <h3 class="plant-title">${newPlantData.identificacion}</h3>
                        <p class="plant-descripcion">${newPlantData.descripcion}</p>
                        <p class="plant-estado-container"><strong>Estado:</strong> <span class="plant-estado">${newPlantData.estado}</span></p>
                        <p><strong>Riego:</strong> <span class="plant-riego">${newPlantData.riego}</span> <span class="sistema-container">| <strong>Sistema:</strong> <span class="plant-sistema">${newPlantData.sistema_riego}</span></span></p>
                      </div>
                      <div class="plant-action-controls">
                        <button class="move-plant-left-btn" data-plant-num="${newPlantData.num}" data-zone="${newPlantData.zona}" title="Mover planta a la izquierda" aria-label="Mover planta a la izquierda"><?php echo ui_icon('arrow-left'); ?></button>
                        <button class="move-plant-right-btn" data-plant-num="${newPlantData.num}" data-zone="${newPlantData.zona}" title="Mover planta a la derecha" aria-label="Mover planta a la derecha"><?php echo ui_icon('arrow-right'); ?></button>
                        <button class="delete-plant-btn" data-plant-num="${newPlantData.num}" title="Eliminar planta" aria-label="Eliminar planta"><?php echo ui_icon('trash'); ?></button>
                      </div>
                    `;
                    
                    targetContainer.insertBefore(newPlantColumn, addPlantCardColumn);
                    updatePlantActionButtons(targetContainer);

                    if (typeof window.processPlantTitles === 'function') {
                        window.processPlantTitles(); 
                    }
                  }
                  if (window.openModal) {
                    openModal(newPlantData);
                  } else {
                    location.reload(); 
                  }
                } else {
                  alert(data.error || 'Error al añadir la planta.');
                }
              });
              return; 
            }
            
          });
        });

        function reorderPlantUI(plantNum, zona, direction) {
            const container = document.querySelector(`.card-container[data-zone="${CSS.escape(zona)}"]`);
            if (!container) return;
            
            const columns = Array.from(container.children).filter(node => 
              node.classList && node.classList.contains('plant-item-column') && node.querySelector('.plant-card[data-plant-num]')
            );
            
            const currentColumnIndex = columns.findIndex(column => 
              column.querySelector(`.plant-card[data-plant-num="${plantNum}"]`)
            );
            
            if (currentColumnIndex === -1) return;
            
            const currentColumn = columns[currentColumnIndex];
            let targetColumnIndex;

            if (direction === 'left') {
              if (currentColumnIndex === 0) return; 
              targetColumnIndex = currentColumnIndex - 1;
              container.insertBefore(currentColumn, columns[targetColumnIndex]);
            } else { 
              if (currentColumnIndex === columns.length - 1) return; 
              targetColumnIndex = currentColumnIndex + 1;
              container.insertBefore(currentColumn, columns[targetColumnIndex].nextSibling);
            }

            // Smooth scroll to the moved plant
            setTimeout(() => {
              currentColumn.scrollIntoView({ 
                behavior: 'smooth', 
                block: 'center',
                inline: 'center'
              });
            }, 100);

            const newOrderPayload = [];
            const updatedColumns = Array.from(container.children).filter(node => 
              node.classList && node.classList.contains('plant-item-column') && node.querySelector('.plant-card[data-plant-num]')
            );

            updatedColumns.forEach((col, i) => {
              const card = col.querySelector('.plant-card[data-plant-num]');
              if (card) {
                newOrderPayload.push({
                  plant_num: card.getAttribute('data-plant-num'),
                  orden: i,
                  zona: zona 
                });
              }
            });
            
            fetch('api/plants/update-order.php', {
              method: 'POST',
              headers: {'Content-Type': 'application/x-www-form-urlencoded'},
              body: new URLSearchParams({ order: JSON.stringify(newOrderPayload) })
            })
            .then(r => r.json())
            .then(data => {
              if (!data.success) {
                console.error('Error updating order:', data.error);
              }
              updatePlantActionButtons(container); 
            })
            .catch(err => {
              console.error('Fetch error for update_order:', err);
            });
        }

        function updatePlantActionButtons(container) {
            if (!container) return;
            const columns = Array.from(container.children).filter(node => 
                node.classList && node.classList.contains('plant-item-column') && node.querySelector('.plant-card[data-plant-num]')
            );

            columns.forEach((column, i) => {
              const controls = column.querySelector('.plant-action-controls');
              if (!controls) return; 

              const leftBtn = controls.querySelector('.move-plant-left-btn');
              const rightBtn = controls.querySelector('.move-plant-right-btn');
              
              if (leftBtn) leftBtn.disabled = (i === 0);
              if (rightBtn) rightBtn.disabled = (i === columns.length - 1);
            });
        }
        
        document.querySelectorAll('.card-container').forEach(updatePlantActionButtons);
      } 

      // Zone management JS (add, rename, delete, reorder zones)
      <?php if(isset($_SESSION['user'])): ?>
        const addZoneBtn = document.getElementById('add-zone-btn');
        if (addZoneBtn) {
          addZoneBtn.addEventListener('click', function() {
            const newZoneName = prompt("Nombre para la nueva zona:");
            if (newZoneName && newZoneName.trim() !== "") {
                fetch('api/zones/add.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                    body: new URLSearchParams({ zona: newZoneName.trim() })
                })
                .then(r => r.json())
                .then(data => {
                    if (data.success) location.reload();
                    else alert(data.error || 'Error al añadir zona.');
                });
            }
          });
        }
        
        document.querySelectorAll('.rename-zone-btn').forEach(function(btn) {
            btn.addEventListener('click', function(e) {
                e.stopPropagation();
                const oldZona = btn.getAttribute('data-zone');
                let newZona = prompt('Nuevo nombre para la zona:', oldZona);
                if (newZona && newZona.trim() !== "" && newZona.trim() !== oldZona) {
                    newZona = newZona.trim();
                    fetch('api/zones/rename.php', { 
                        method: 'POST',
                        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                        body: new URLSearchParams({ old_zona: oldZona, new_zona: newZona })
                    })
                    .then(r => r.json())
                    .then(data => { 
                        if (data.success) location.reload(); 
                        else alert(data.error || 'Error al renombrar zona.'); 
                    });
                }
            });
        });

        document.querySelectorAll('.delete-zone-btn').forEach(function(btn) {
            btn.addEventListener('click', function(e) {
                e.stopPropagation();
                const zonaToDelete = btn.getAttribute('data-zone');
                if (confirm(`¿Seguro que deseas eliminar la zona "${zonaToDelete}" y todas sus plantas?`)) {
                    fetch('api/zones/delete.php', {
                        method: 'POST',
                        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                        body: new URLSearchParams({ zona: zonaToDelete })
                    })
                    .then(r => r.json())
                    .then(data => {
                        if (data.success) location.reload();
                        else alert(data.error || 'Error al eliminar la zona.');
                    });
                }
            });
        });
        
        // --- MODIFIED ZONE REORDERING (was SIMPLIFIED ZONE REORDERING) ---
        function handleZoneMove(zoneElement, direction) {
            const pageWrapper = document.getElementById('page-wrapper');
            if (!pageWrapper || !zoneElement) return;

            let targetElement = null;
            if (direction === 'up') {
                targetElement = zoneElement.previousElementSibling;
                if (targetElement && targetElement.classList.contains('zone-section')) {
                    pageWrapper.insertBefore(zoneElement, targetElement);
                } else {
                    return; 
                }
            } else if (direction === 'down') {
                targetElement = zoneElement.nextElementSibling;
                let actualNextZoneElement = targetElement;
                while(actualNextZoneElement && !actualNextZoneElement.classList.contains('zone-section')) {
                    actualNextZoneElement = actualNextZoneElement.nextElementSibling;
                }

                if (actualNextZoneElement) { 
                  pageWrapper.insertBefore(zoneElement, actualNextZoneElement.nextElementSibling);
                } else if (targetElement) { 
                   pageWrapper.appendChild(zoneElement);
                } else {
                    return;
                }
            }

            // Smooth scroll to the moved zone with delay for DOM update
            setTimeout(() => {
              zoneElement.scrollIntoView({ 
                behavior: 'smooth', 
                block: 'center'
              });
            }, 150);

            const orderedZoneNames = [];
            pageWrapper.querySelectorAll('.zone-section').forEach(section => {
                orderedZoneNames.push(section.getAttribute('data-zone'));
            });

            document.querySelectorAll('.move-zone-up-btn, .move-zone-down-btn').forEach(b => b.disabled = true);

            fetch('api/zones/update-order.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: new URLSearchParams({ ordered_zones: JSON.stringify(orderedZoneNames) })
            })
            .then(r => r.json())
            .then(data => {
                if (!data.success) {
                    alert(data.error || 'Error al actualizar el orden de las zonas.');
                    location.reload(); 
                } else {
                     document.querySelectorAll('.move-zone-up-btn, .move-zone-down-btn').forEach(b => b.disabled = false);
                     // Update button states more accurately
                     const allZoneSections = Array.from(pageWrapper.querySelectorAll('.zone-section'));
                     allZoneSections.forEach((sec, index) => {
                        const upBtn = sec.querySelector('.move-zone-up-btn');
                        const downBtn = sec.querySelector('.move-zone-down-btn');
                        if (upBtn) upBtn.disabled = (index === 0);
                        if (downBtn) downBtn.disabled = (index === allZoneSections.length - 1);
                     });
                }
            })
            .catch(err => {
                console.error('Error en fetch update_zone_order:', err);
                alert('Error de conexión al actualizar el orden de las zonas.');
            });
        }

        document.querySelectorAll('.move-zone-up-btn').forEach(btn => {
            btn.addEventListener('click', function(e) {
                e.stopPropagation();
                const zoneSection = this.closest('.zone-section');
                if (zoneSection && !this.disabled) handleZoneMove(zoneSection, 'up');
            });
        });

        document.querySelectorAll('.move-zone-down-btn').forEach(btn => {
            btn.addEventListener('click', function(e) {
                e.stopPropagation();
                const zoneSection = this.closest('.zone-section');
                if (zoneSection && !this.disabled) handleZoneMove(zoneSection, 'down');
            });
        });
        // --- END MODIFIED ZONE REORDERING ---

      <?php endif; ?>
    }); 
  </script>
  <script src="assets/scripts.js?v=<?php echo filemtime(__DIR__ . '/assets/scripts.js'); ?>" defer="defer"></script>
</body>
</html>
