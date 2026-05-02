// Simple notification fallback if not defined elsewhere
function showNotification(message, type = 'info') {
  // Puedes personalizar esto para mostrar un toast, snackbar, etc.
  // Por ahora, solo un alert amigable
  alert(message);
}

// HTML escape helper to prevent XSS
function escapeHTML(str) {
  if (!str) return '';
  const div = document.createElement('div');
  div.appendChild(document.createTextNode(str));
  return div.innerHTML;
}

// DOM-ready event handler
document.addEventListener('DOMContentLoaded', function() {
  // Core initializations
  initImageLazyLoading();
  processPlantTitles();
  initUserInterface();
  initModalHandlers();
  initHorizontalScrolling();
  initFullscreenViewer(); // Added for fullscreen functionality
  
  // Initialize mobile viewer with slight delay to ensure DOM is ready
  // setTimeout(initMobileImageViewer, 500); // <-- Elimina o comenta esta línea si no tienes la función
});

// User interface initialization
function initUserInterface() {
  const dropdownToggle = document.getElementById('userDropdownToggle');
  const dropdownMenu = document.getElementById('userDropdownMenu');
  
  if (dropdownToggle && dropdownMenu) {
    // Dropdown toggle
    dropdownToggle.addEventListener('click', function(e) {
      e.stopPropagation();
      dropdownMenu.classList.toggle('show');
    });
    
    // Close dropdown when clicking outside
    document.addEventListener('click', function(e) {
      if (dropdownMenu.classList.contains('show') && 
          !dropdownToggle.contains(e.target) && 
          !dropdownMenu.contains(e.target)) {
        dropdownMenu.classList.remove('show');
      }
    });
  }
  
  // Enhanced login button handling
  const loginButton = document.getElementById('login-button');
  if (loginButton) {
    loginButton.addEventListener('touchstart', function(e) {
      e.preventDefault();
      setTimeout(() => window.location.href = 'login.php', 50);
    }, { passive: false });
  }
}

// Image handling functions
function initImageLazyLoading() {
  if ('IntersectionObserver' in window) {
    const imageObserver = new IntersectionObserver((entries, observer) => {
      entries.forEach(entry => {
        if (entry.isIntersecting) {
          const img = entry.target;
          const src = img.dataset.src;
          
          if (src) {
            img.src = src;
            img.removeAttribute('data-src');
            imageObserver.unobserve(img);
          }
        }
      });
    }, {
      rootMargin: '300px',
      threshold: 0.01
    });
    
    // Observe all images that need lazy loading
    document.querySelectorAll('.plant-card-image img').forEach(img => {
      imageObserver.observe(img);
    });
    
    // Handle horizontal scroll containers
    document.querySelectorAll('.card-container').forEach(container => {
      container.addEventListener('scroll', debounce(function() {
        document.querySelectorAll('.plant-card-image img[data-src]').forEach(img => {
          if (isElementInViewport(img)) {
            img.src = img.dataset.src;
            img.removeAttribute('data-src');
            imageObserver.unobserve(img);
          }
        });
      }, 100));
    });
  } else {
    // Fallback for browsers without IntersectionObserver
    loadVisibleImages();
    window.addEventListener('scroll', debounce(loadVisibleImages, 200));
    document.querySelectorAll('.card-container').forEach(container => {
      container.addEventListener('scroll', debounce(loadVisibleImages, 200));
    });
  }
}

// Helper function to check if element is in viewport
function isElementInViewport(el) {
  const rect = el.getBoundingClientRect();
  const viewportHeight = window.innerHeight || document.documentElement.clientHeight;
  const viewportWidth = window.innerWidth || document.document.documentElement.clientWidth;
  
  return (
    rect.top <= viewportHeight + 300 &&
    rect.left <= viewportWidth + 300 &&
    rect.bottom >= -300 &&
    rect.right >= -300
  );
}

// Fallback lazy loading
function loadVisibleImages() {
  document.querySelectorAll('.plant-card-image img[data-src]').forEach(img => {
    if (isElementInViewport(img)) {
      img.src = img.dataset.src;
      img.removeAttribute('data-src');
    }
  });
}

// Debounce function
function debounce(func, wait) {
  let timeout;
  return function() {
    const context = this, args = arguments;
    clearTimeout(timeout);
    timeout = setTimeout(() => func.apply(context, args), wait);
  };
}

// --- START AI ANALYSIS FUNCTIONS ---
function ensureAnalyzeButtonMarkup(btn) {
    if (!btn) return;
    // Ensure base classes
    btn.classList.add('icon-btn', 'minimal-btn');

    const hasSvg = !!btn.querySelector('svg');
    const hasLabel = !!btn.querySelector('.icon-label');
    if (!hasSvg || !hasLabel) {
        btn.innerHTML = `
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#66b58d" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <rect x="3" y="11" width="18" height="10" rx="2" ry="2"/>
            <circle cx="12" cy="5" r="2"/>
            <path d="m 12,7 v 4"/>
            <line x1="5" y1="16" x2="5" y2="16"/>
            <line x1="19" y1="16" x2="19" y2="16"/>
          </svg>
          <span class="icon-label">Analizar</span>
        `;
    }
}

function analyzeImageWithAI(imagePath, plantNum) {
    const analyzeBtn = document.getElementById('btn-ai-analyze');
    ensureAnalyzeButtonMarkup(analyzeBtn);
  const originalButtonHTML = analyzeBtn ? analyzeBtn.innerHTML : null;
    if (analyzeBtn) {
        analyzeBtn.disabled = true;
    analyzeBtn.classList.add('loading');
    const label = analyzeBtn.querySelector('.icon-label');
    if (label) {
      label.textContent = 'Analizando...';
    } else {
      analyzeBtn.textContent = 'Analizando...';
    }
    }

    fetch('analyze_image.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({ image_path: imagePath, plant_num: plantNum })
    })
    .then(response => {
        // Always try to read text first to debug potential HTML errors or detailed JSON errors
        return response.text().then(text => {
            let data;
            try {
                data = JSON.parse(text);
            } catch (e) {
                console.error("Non-JSON response received:", text);
                throw new Error("Respuesta inválida del servidor (posible error PHP). revisa la consola.");
            }
            
            if (!response.ok) {
                // Throw error using the message from the JSON if available
                throw new Error(data.error || `Error HTTP ${response.status}`);
            }
            
            return data;
        });
    })
    .then(data => {
        if (analyzeBtn) {
            analyzeBtn.disabled = false;
        analyzeBtn.classList.remove('loading');
        if (originalButtonHTML !== null) {
          analyzeBtn.innerHTML = originalButtonHTML;
        }
        }
        if (data.success && data.results) {
            showAISuggestionsDialog(data.results, plantNum);
        } else {
            alert('Error en el análisis IA: ' + (data.error || 'Respuesta desconocida del servidor.'));
        }
    })
    .catch(error => {
        if (analyzeBtn) {
            analyzeBtn.disabled = false;
        analyzeBtn.classList.remove('loading');
        if (originalButtonHTML !== null) {
          analyzeBtn.innerHTML = originalButtonHTML;
        }
        }
        console.error('Error fetching AI analysis:', error);
        alert('Error de conexión al analizar con IA: ' + error.message);
    });
}

function ensureAIAnalyzeButton() {
    // Use the correct selector for the controls container
    // const controlsContainer = document.querySelector('#plant-modal .modal-image-controls');
    const controlsContainer = document.querySelector('#plant-modal .modal-image-controls-icons'); // <-- updated selector

    if (!controlsContainer && isLoggedIn) {
        console.warn("Modal image controls container not found for AI button.");
        return;
    }
    if (!isLoggedIn) return;

    let btn = document.getElementById('btn-ai-analyze');
    if (!btn) {
        btn = document.createElement('button');
        btn.id = 'btn-ai-analyze';
        btn.className = 'icon-btn minimal-btn btn-ai-analyze';
        btn.title = 'Analizar con IA';

        // Append to the controls container, or create one if it's missing (less ideal)
        if (controlsContainer) {
            controlsContainer.appendChild(btn);
        } else {
            // fallback: append to modal
            document.getElementById('plant-modal').appendChild(btn);
        }
    }

      // Normalize markup in case previous code stripped SVG/label
      ensureAnalyzeButtonMarkup(btn);

    btn.onclick = function() {
        if (!currentPlantNum || !currentGallery || currentGallery.length === 0) {
            alert("No hay imagen actual para analizar o la planta no está seleccionada.");
            this.disabled = false; // Re-enable button if it was disabled by mistake
          const label = this.querySelector('.icon-label');
          if (label) label.textContent = 'Analizar';
            return;
        }
        const imageToAnalyze = currentGallery[currentIndex];
        if (!imageToAnalyze) {
            alert("La imagen seleccionada no es válida.");
            this.disabled = false;
          const label = this.querySelector('.icon-label');
          if (label) label.textContent = 'Analizar';
            return;
        }
        analyzeImageWithAI(imageToAnalyze, currentPlantNum);
    };
}
// --- END AI ANALYSIS FUNCTIONS ---

// --- DIRECT AI CHAT (from modal button) ---
function openDirectAIChat(plantNum) {
  // Remove any existing direct chat
  let existing = document.getElementById('ai-direct-chat-dialog');
  if (existing) existing.remove();

  // Get plant name from modal
  let plantName = 'esta planta';
  const modalTitleEl = document.querySelector('#plant-modal .modal-title');
  if (modalTitleEl) {
    const nombreComun = modalTitleEl.querySelector('.nombre-comun');
    plantName = nombreComun ? nombreComun.textContent : modalTitleEl.textContent.trim();
  }

  // Build the chat UI
  const dialog = document.createElement('div');
  dialog.id = 'ai-direct-chat-dialog';
  dialog.style.cssText = 'position:fixed;bottom:32px;right:32px;z-index:5000;width:370px;max-width:98vw;background:#fff;border-radius:16px;box-shadow:0 6px 32px rgba(0,0,0,0.18);overflow:hidden;display:flex;flex-direction:column;';

  dialog.innerHTML = `
    <div style="background:#58a45c;color:#fff;padding:13px 18px 11px 18px;display:flex;align-items:center;justify-content:space-between;">
      <span style="font-weight:600;font-size:16px;letter-spacing:0.01em;">Asistente IA</span>
      <div style="display:flex;gap:8px;">
        <button id="ai-direct-chat-expand" title="Expandir chat" style="background:none;border:none;color:#fff;font-size:18px;cursor:pointer;line-height:1;padding:0 4px 0 0;">&#x2922;</button>
        <button id="ai-direct-chat-close" style="background:none;border:none;color:#fff;font-size:22px;cursor:pointer;line-height:1;padding:0 0 0 10px;">&times;</button>
      </div>
    </div>
    <div id="ai-direct-chat-messages" class="ai-chat-messages" style="flex:1 1 auto;max-height:320px;min-height:120px;overflow-y:auto;padding:18px 12px 12px 12px;background:#f7faf7;scrollbar-width:thin;">
      <div class="ai-chat-bubble ai"><span>¡Hola! Soy tu asistente IA. ¿En qué puedo ayudarte con <strong>${escapeHTML(plantName)}</strong>?</span></div>
    </div>
    <form id="ai-direct-chat-form" style="display:flex;border-top:1px solid #e0e8e0;background:#f7faf7;">
      <input type="text" id="ai-direct-chat-input" placeholder="Pregunta a la IA..." autocomplete="off" style="flex:1;padding:12px 14px;font-size:15px;border:none;background:#f7faf7;">
      <button type="submit" style="padding:0 18px;background:#58a45c;color:#fff;border:none;border-radius:0 0 12px 0;cursor:pointer;font-size:16px;font-weight:600;">Enviar</button>
    </form>
  `;

  document.body.appendChild(dialog);

  // Reset chat history
  aiChatHistory = [];

  // Close button
  dialog.querySelector('#ai-direct-chat-close').onclick = function() {
    dialog.remove();
  };

  // Expand button
  let expanded = false;
  dialog.querySelector('#ai-direct-chat-expand').onclick = function() {
    expanded = !expanded;
    if (expanded) {
      dialog.style.width = '98vw';
      dialog.style.maxWidth = '700px';
      dialog.querySelector('#ai-direct-chat-messages').style.maxHeight = '60vh';
      this.innerHTML = '&#x2923;';
    } else {
      dialog.style.width = '370px';
      dialog.style.maxWidth = '98vw';
      dialog.querySelector('#ai-direct-chat-messages').style.maxHeight = '320px';
      this.innerHTML = '&#x2922;';
    }
  };

  // Send message
  const form = dialog.querySelector('#ai-direct-chat-form');
  const input = dialog.querySelector('#ai-direct-chat-input');
  const messagesDiv = dialog.querySelector('#ai-direct-chat-messages');

  form.onsubmit = function(e) {
    e.preventDefault();
    const message = input.value.trim();
    if (!message) return;
    input.value = '';

    // Add user bubble
    const userBubble = document.createElement('div');
    userBubble.classList.add('ai-chat-bubble', 'user');
    userBubble.innerHTML = '<span>' + escapeHTML(message) + '</span>';
    messagesDiv.appendChild(userBubble);
    messagesDiv.scrollTop = messagesDiv.scrollHeight;

    // Add thinking dots
    const thinkingBubble = document.createElement('div');
    thinkingBubble.classList.add('ai-chat-bubble', 'ai');
    thinkingBubble.innerHTML = '<span class="ai-thinking-dots"><span>.</span><span>.</span><span>.</span></span>';
    messagesDiv.appendChild(thinkingBubble);
    messagesDiv.scrollTop = messagesDiv.scrollHeight;

    const historyForAPI = aiChatHistory.map(entry => ({
      role: entry.role,
      content: entry.content
    }));

    fetch('ai_chat.php', {
      method: 'POST',
      headers: {'Content-Type': 'application/x-www-form-urlencoded'},
      body: new URLSearchParams({
        message: message,
        plant_num: plantNum,
        history: JSON.stringify(historyForAPI)
      })
    })
    .then(response => response.json())
    .then(data => {
      thinkingBubble.remove();
      const aiBubble = document.createElement('div');
      if (data.error) {
        aiBubble.classList.add('ai-chat-bubble', 'ai', 'error');
        aiBubble.innerHTML = '<span>Error: ' + escapeHTML(data.error) + '</span>';
      } else {
        aiBubble.classList.add('ai-chat-bubble', 'ai');
        aiBubble.innerHTML = '<span>' + escapeHTML(data.reply) + '</span>';
        aiChatHistory.push({role: 'user', content: message});
        aiChatHistory.push({role: 'assistant', content: data.reply});
        const maxHistoryLength = 20;
        if (aiChatHistory.length > maxHistoryLength) {
          aiChatHistory = aiChatHistory.slice(-maxHistoryLength);
        }
      }
      messagesDiv.appendChild(aiBubble);
      messagesDiv.scrollTop = messagesDiv.scrollHeight;
    })
    .catch(error => {
      thinkingBubble.remove();
      const errBubble = document.createElement('div');
      errBubble.classList.add('ai-chat-bubble', 'ai', 'error');
      errBubble.innerHTML = '<span>Error de conexión: ' + escapeHTML(error.toString()) + '</span>';
      messagesDiv.appendChild(errBubble);
      messagesDiv.scrollTop = messagesDiv.scrollHeight;
    });
  };

  // Focus input
  setTimeout(() => input.focus(), 100);
}

function ensureAIChatButton() {
  const chatBtn = document.getElementById('btn-ai-chat');
  if (!chatBtn) return;
  chatBtn.onclick = function() {
    if (!currentPlantNum) {
      alert("No hay planta seleccionada.");
      return;
    }
    openDirectAIChat(currentPlantNum);
  };
}
// --- END DIRECT AI CHAT ---

// Image URL helper with cache busting
const pageLoadTime = new Date().getTime();
function getImageUrl(imagePath) {
  return imagePath + "?t=" + pageLoadTime;
}

// Helper functions for images
function handleImageError(img) {
  img.onerror = null;
  img.src = 'images/placeholder.jpg';
}

function updateCardImage(plantNum, newImageUrl) {
  document.querySelectorAll(`.plant-card[data-plant-num="${plantNum}"]`).forEach(card => {
    const img = card.querySelector('img');
    if (img) img.src = getImageUrl(newImageUrl);
  });
}

// Process plant titles for consistent appearance
function processPlantTitles() {
  document.querySelectorAll('.plant-title').forEach(title => {
    const originalText = title.textContent.trim();
    const [nombreComun, nombreCientifico] = originalText.split('\n');
    if (nombreCientifico) {
      // Usar span en vez de <br> para evitar salto de línea real en cards
      title.innerHTML = `<span class="nombre-comun">${nombreComun}</span><span class="nombre-cientifico" style="font-style:italic;color:#3a6b3a;font-size:13px;display:block;">${nombreCientifico}</span>`;
    } else {
      title.innerHTML = `<span class="nombre-comun">${nombreComun}</span>`;
    }
  });
}

// Modal variables and management
let modal, modalTitle, modalImage, modalDetails;
let currentGallery = [];
let currentIndex = 0;
let currentPlantNum = null;
let originalFieldValues = {};
let mainPageScrollPosition = 0;

// Initialize modal handlers
function initModalHandlers() {
  modal = document.getElementById("plant-modal");
  modalTitle = document.getElementById("modal-title");
  modalImage = document.getElementById("modal-image");
  modalDetails = document.getElementById("modal-details");

  // --- MODIFIED: Use event delegation for opening plant modal ---
  document.body.addEventListener("click", function(e) {
    // Exclude clicks on controls/buttons inside the plant card
    if (
      e.target.closest('.plant-action-controls') ||
      e.target.closest('.add-plant-card') ||
      e.target.closest('.rename-zone-btn, .delete-zone-btn, .move-zone-up-btn, .move-zone-down-btn') ||
      e.target.closest('.zone-upload-btn') ||
      e.target.closest('#userDropdownToggle') ||
      e.target.closest('#userDropdownMenu a') ||
      e.target.closest('.scroll-button') ||
      e.target.closest('.gallery .arrow') ||
      e.target.closest('#btn-ai-chat') ||
      e.target.closest('#ai-suggestions-dialog button') ||
      e.target.closest('#ai-chat-container button') ||
      e.target.closest('#ai-chat-container input') ||
      e.target.closest('.modal-buttons button:not(#btn-back)') ||
      e.target.closest('.modal-image-controls button, .modal-image-controls label') ||
      e.target.closest('#fullscreen-viewer')
    ) {
      return; // Do NOT open modal if click is on any of these controls
    }

    // Only open modal if click is on a plant card (or its children), but not on excluded controls
    const card = e.target.closest('.plant-card:not(.add-plant-card)');
    if (card) {
      // Prevent double opening if already open
      if (modal && modal.classList.contains('show')) return;
      // Get plant number from data attribute
      const plantNum = card.getAttribute('data-plant-num');
      if (plantNum) {
        // Fetch plant data and open modal
        fetch('get_plants.php?' + new Date().getTime())
          .then(response => response.json())
          .then(data => {
            const plantData = Array.isArray(data)
              ? data.find(p => p.num == plantNum)
              : null;
            if (plantData) {
              openModal(plantData);
            }
          });
      }
    }
  });

  // Content editable field handlers
  if (modalTitle) {
    modalTitle.addEventListener("blur", function() {
      if (currentPlantNum) {
        updateField(currentPlantNum, "identificacion", modalTitle.innerText);
      }
    });
  }

  if (modalDetails) {
    modalDetails.querySelectorAll("[contenteditable]").forEach(elem => {
      elem.addEventListener("blur", function() {
        if (currentPlantNum) {
          updateField(currentPlantNum, this.getAttribute("data-field"), this.innerText);
        }
      });
    });
  }
  
  // Navigation buttons
  const prevPlantButton = document.getElementById('prev-plant');
  const nextPlantButton = document.getElementById('next-plant');
  
  if (prevPlantButton) {
    prevPlantButton.addEventListener('click', () => navigateToPlant('prev'));
    prevPlantButton.addEventListener('touchend', function(e) {
      e.preventDefault(); // Prevent click event from firing subsequently
      navigateToPlant('prev');
    });
  }
  
  if (nextPlantButton) {
    nextPlantButton.addEventListener('click', () => navigateToPlant('next'));
    nextPlantButton.addEventListener('touchend', function(e) {
      e.preventDefault(); // Prevent click event from firing subsequently
      navigateToPlant('next');
    });
  }
  
  // Close buttons
  document.getElementById("btn-back").addEventListener("click", closeModal);
  document.getElementById("close-x-button").addEventListener("click", closeModal);
  
  // Image upload handling
  const modalUpload = document.getElementById("modal-upload");
  if (modalUpload) {
    modalUpload.addEventListener("change", handleImageUpload);
  }
  
  // Image delete handling
  const btnDelete = document.getElementById("btn-delete");
  if (btnDelete) {
    btnDelete.addEventListener("click", handleImageDelete);
  }
  
  // Enhanced click outside to close for desktop
  window.addEventListener('click', function(event) {
    if (modal.classList.contains('show') && window.innerWidth >= 601 && 
        event.target === modal) {
      closeModal();
    }
  });
  
  // Prevent modal body scroll from affecting main page
  document.addEventListener('touchmove', function(e) {
    if (modal.classList.contains('show')) {
      const modalContent = document.querySelector('.modal-content');
      // Permitir scroll en elementos del chat IA (y otros scrollables)
      let el = e.target;
      let allowScroll = false;
      while (el && el !== document.body) {
        if (
          el.classList &&
          (
            el.classList.contains('ai-chat-messages') ||
            el.id === 'ai-chat-messages' ||
            el.id === 'ai-chat-container'
          )
        ) {
          allowScroll = true;
          break;
        }
        el = el.parentElement;
      }
      if (!allowScroll && !modalContent.contains(e.target)) {
        e.preventDefault();
      }
    }
  }, { passive: false });
  
  // Initialize gallery controls
  addEnhancedGalleryControls();
  // Add AI analyze button - REMOVED FROM HERE, openModal will call it.
  // ensureAIAnalyzeButton(); 
}

// Body scroll management
function lockBodyScroll() {
  document.body.style.position = 'fixed';
  document.body.style.top = `-${mainPageScrollPosition}px`;
  document.body.style.left = '0';
  document.body.style.right = '0';
  document.body.style.bottom = '0';
  document.body.style.overflowY = 'scroll';
}

function unlockBodyScroll() {
  document.body.style.position = '';
  document.body.style.top = '';
  document.body.style.left = '';
  document.body.style.right = '';
  document.body.style.bottom = '';
  document.body.style.overflowY = '';
  window.scrollTo(0, mainPageScrollPosition);
}

// --- START navigateToPlant FUNCTION ---
function navigateToPlant(direction) {
    if (!currentPlantNum || !originalFieldValues.zona) {
        console.warn("Cannot navigate: current plant or zone not set.");
        return;
    }

    const currentZone = originalFieldValues.zona;

    fetch('get_plants.php?' + new Date().getTime())
        .then(response => {
            if (!response.ok) throw new Error('Network response was not ok.');
            return response.json();
        })
        .then(allPlants => {
            if (!Array.isArray(allPlants)) {
                throw new Error('Invalid plants data received');
            }

            const plantsInZone = allPlants
                .filter(p => p.zona === currentZone)
                .sort((a, b) => (a.orden || 0) - (b.orden || 0));

            if (plantsInZone.length === 0) {
                alert('No hay plantas en esta zona.');
                return;
            }
            
            let currentPlantIndexInZone = plantsInZone.findIndex(p => p.num == currentPlantNum);

            if (currentPlantIndexInZone === -1) {
                alert('Planta actual no encontrada en la zona.');
                return;
            }

            let nextPlantIndex;
            if (direction === 'next') {
                nextPlantIndex = (currentPlantIndexInZone + 1) % plantsInZone.length;
            } else {
                nextPlantIndex = (currentPlantIndexInZone - 1 + plantsInZone.length) % plantsInZone.length;
            }
            
            // Disable buttons if only one plant in zone
            const prevBtn = document.getElementById('prev-plant');
            const nextBtn = document.getElementById('next-plant');
            if (plantsInZone.length <= 1) {
                if (prevBtn) prevBtn.disabled = true;
                if (nextBtn) nextBtn.disabled = true;
            } else {
                if (prevBtn) prevBtn.disabled = false;
                if (nextBtn) nextBtn.disabled = false;
            }

            openModal(plantsInZone[nextPlantIndex]);
        })
        .catch(error => {
            console.error("Error navigating plant:", error);
            alert("Error al navegar entre plantas: " + error.message);
        });
}
// --- END navigateToPlant FUNCTION ---

// Open modal function
function openModal(plantData) {
  // Si se pasa un número, busca en JSON (retrocompatibilidad)
  if (typeof plantData === 'number' || (typeof plantData === 'string' && !plantData.identificacion)) {
    fetch('get_plants.php?' + new Date().getTime())
      .then(response => response.json())
      .then(data => {
        const plant = data.find(p => p.num == plantData);
        if (plant) {
          openModal(plant); // Correctly call openModal with the fetched plant object
        } else {
          console.error('Plant not found for num:', plantData);
          // Optionally, display an error to the user or close modal if it was partially opened
        }
      })
      .catch(error => {
        console.error("Error fetching plant data in openModal:", error);
        alert("Error al cargar los datos de la planta.");
      });
    return; // Important: return here to prevent rest of function running with incomplete data
  }
  
  // Store main page scroll position only when opening modal first time
  if (!modal.classList.contains('show')) {
    mainPageScrollPosition = window.pageYOffset || document.documentElement.scrollTop;
  }
  
  // Set up plant data
  currentPlantNum = plantData.num;
  document.getElementById('current-plant-number').textContent = currentPlantNum;
  
  currentGallery = plantData.imagenes?.length > 0 ? [...plantData.imagenes] : [];
  currentIndex = 0;
  
  // Update modal fields
  const [nombreComun, nombreCientifico] = plantData.identificacion.split('\n');
  if (nombreCientifico) {
    modalTitle.innerHTML = `<span class="nombre-comun">${nombreComun}</span><br><span class="nombre-cientifico" style="font-style:italic;color:#3a6b3a;font-size:16px;">${nombreCientifico}</span>`;
  } else {
    modalTitle.innerHTML = `<span class="nombre-comun">${nombreComun}</span>`;
  }
  modalDetails.querySelector("[data-field='estado']").innerText = plantData.estado;
  modalDetails.querySelector("[data-field='descripcion']").innerText = plantData.descripcion;
  modalDetails.querySelector("[data-field='zona']").innerText = plantData.zona;
  modalDetails.querySelector("[data-field='riego']").innerText = plantData.riego;
  modalDetails.querySelector("[data-field='sistema_riego']").innerText = plantData.sistema_riego;
  
  // Store original field values
  originalFieldValues = {
    identificacion: plantData.identificacion,
    estado: plantData.estado,
    descripcion: plantData.descripcion,
    zona: plantData.zona,
    riego: plantData.riego,
    sistema_riego: plantData.sistema_riego
  };
  
  // Make modal visible first
  modal.style.display = "flex";
  resetModalScroll();
  updateGalleryState();
  
  // Initialize gallery and display first image
  setTimeout(() => addEnhancedGalleryControls(), 100);
  displayCurrentImage();
  
  // Add AI analyze button - THIS IS THE CORRECT PLACE
  if (isLoggedIn) { // Only ensure button if user is logged in
    ensureAIAnalyzeButton();
    ensureAIChatButton();
  }
  
  // Lock body scroll and start fade in animation
  requestAnimationFrame(() => {
    lockBodyScroll();
    modal.classList.add('show');
    fetchPlantHistory(currentPlantNum);

    // Update navigation button states after modal is open and currentPlantNum is set
    // This can be part of navigateToPlant or called here if openModal is used directly too
    // For simplicity, let's ensure navigateToPlant handles its own button states.
    // If openModal is called directly (e.g. first card click), we might need to update buttons here too.
    // Let's add a call to update nav buttons based on the new plant's context.
    updateModalNavButtonsState(plantData.zona);


  });
}

// Helper to update modal navigation buttons state
function updateModalNavButtonsState(currentPlantZone) {
    const prevBtn = document.getElementById('prev-plant');
    const nextBtn = document.getElementById('next-plant');
    if (!prevBtn || !nextBtn) return;

    fetch('get_plants.php?' + new Date().getTime())
        .then(response => response.json())
        .then(allPlants => {
            const plantsInCurrentZone = allPlants.filter(p => p.zona === currentPlantZone);
            if (plantsInCurrentZone.length <= 1) {
                prevBtn.disabled = true;
                nextBtn.disabled = true;
            } else {
                prevBtn.disabled = false;
                nextBtn.disabled = false;
            }
        }).catch(err => {
            console.error("Failed to update modal nav buttons state:", err);
            // Keep them enabled as a fallback, or disable if preferred
            prevBtn.disabled = false;
            nextBtn.disabled = false;
        });
}


function closeModal() {
  modal.classList.remove('show');
  // Close any open AI chat dialogs
  const directChat = document.getElementById('ai-direct-chat-dialog');
  if (directChat) directChat.remove();
  const suggestionsDialog = document.getElementById('ai-suggestions-dialog');
  if (suggestionsDialog) suggestionsDialog.remove();
  setTimeout(() => {
    modal.style.display = "none";
    unlockBodyScroll();
  }, 300);
}

// Reset modal scroll position
function resetModalScroll() {
  const modalContent = document.querySelector('.modal-content');
  if (modalContent) modalContent.scrollTop = 0;
}

// Gallery functions
function updateGalleryState() {
  document.querySelector('.gallery').classList.toggle(
    'single-image', currentGallery.length <= 1
  );
}

function addEnhancedGalleryControls() {
  const leftArrow = document.querySelector(".gallery .arrow.left");
  const rightArrow = document.querySelector(".gallery .arrow.right");
  
  if (!leftArrow || !rightArrow) return;
  
  // Replace with clones to remove previous listeners
  const leftClone = leftArrow.cloneNode(true);
  const rightClone = rightArrow.cloneNode(true);
  leftArrow.parentNode.replaceChild(leftClone, leftArrow);
  rightArrow.parentNode.replaceChild(rightClone, rightArrow);
  
  // Add navigation handlers
  const addNavHandler = (element, direction) => {
    const navigate = (e) => {
      e.preventDefault();
      e.stopPropagation();
      
      if (currentGallery?.length > 1) {
        direction === 'prev' ? prevImage() : nextImage();
      }
    };
    
    element.addEventListener('touchstart', navigate, { passive: false });
    element.addEventListener('click', navigate);
  };
  
  addNavHandler(leftClone, 'prev');
  addNavHandler(rightClone, 'next');
}

// Image navigation functions
function prevImage() {
  if (!currentGallery || currentGallery.length <= 1) return;
  
  currentIndex = (currentIndex - 1 + currentGallery.length) % currentGallery.length;
  displayCurrentImage();
  updateFullscreenImage();
}

function nextImage() {
  if (!currentGallery || currentGallery.length <= 1) return;
  
  currentIndex = (currentIndex + 1) % currentGallery.length;
  displayCurrentImage();
  updateFullscreenImage();
}

function updateFullscreenImage() {
  const fullscreenViewer = document.getElementById('fullscreen-viewer');
  const fullscreenImage = document.getElementById('fullscreen-image');
  
  if (fullscreenViewer?.classList.contains('active') && fullscreenImage) {
    fullscreenImage.src = getImageUrl(currentGallery[currentIndex]);
  }
}

function displayCurrentImage() {
  modalImage.classList.remove('loaded');
  
  if (!currentGallery || currentGallery.length === 0) {
    modalImage.src = 'images/placeholder.jpg';
    modalImage.classList.add('loaded');
    return;
  }
  
  currentIndex = Math.max(0, Math.min(currentIndex, currentGallery.length - 1));
  
  const img = new Image();
  img.onload = function() {
    modalImage.src = this.src;
    modalImage.classList.add('loaded');
  };
  img.onerror = function() {
    modalImage.src = 'images/placeholder.jpg';
    modalImage.classList.add('loaded');
  };
  img.src = getImageUrl(currentGallery[currentIndex]);
}

// Field update function
function updateField(plantNum, field, value, force = false) {
  // No registrar en historial si el valor no cambia
  if (!force && originalFieldValues[field] === value) return;
  const previousValue = originalFieldValues[field];

  fetch('update_field.php', {
    method: 'POST',
    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
    body: new URLSearchParams({ plant_num: plantNum, field: field, value: value })
  })
  .then(response => response.json())
  .then(data => {
    if (data.error) {
      alert("Error: " + data.error);
      return;
    }

    document.querySelectorAll(`.plant-card[data-plant-num="${plantNum}"]`).forEach(card => {
      switch(field) {
        case 'identificacion':
          const [nombreComun, nombreCientifico] = value.split('\n');
          const cardTitle = card.querySelector('.plant-title');
          if (cardTitle) {
            if (nombreCientifico) {
              cardTitle.innerHTML = `<span class="nombre-comun">${nombreComun}</span><span class="nombre-cientifico" style="font-style:italic;color:#3a6b3a;font-size:13px;display:block;">${nombreCientifico}</span>`;
            } else {
              cardTitle.innerHTML = `<span class="nombre-comun">${nombreComun}</span>`;
            }
          }
          if (modalTitle && currentPlantNum == plantNum) {
            if (nombreCientifico) {
              modalTitle.innerHTML = `<span class="nombre-comun">${nombreComun}</span><br><span class="nombre-cientifico" style="font-style:italic;color:#3a6b3a;font-size:16px;">${nombreCientifico}</span>`;
            } else {
              modalTitle.innerHTML = `<span class="nombre-comun">${nombreComun}</span>`;
            }
          }
          break;
        case 'estado':
          card.querySelector('.plant-estado').textContent = value;
          break;
        case 'descripcion':
          card.querySelector('.plant-descripcion').textContent = value;
          break;
        case 'riego':
          card.querySelector('.plant-riego').textContent = value;
          break;
        case 'sistema_riego':
          card.querySelector('.plant-sistema').textContent = value;
          break;
      }
    });

    // Log changes with old and new values for specific fields
    if (['identificacion', 'estado', 'descripcion'].includes(field)) {
      logPlantChange(plantNum, field, "", previousValue, value);
    } else {
      logPlantChange(plantNum, field, value);
    }
    originalFieldValues[field] = value;
  });
}

// --- START MOVED AI CHAT FUNCTIONS ---
// --- Chat con IA ---
let aiChatHistory = []; // Global para mantener el historial de la sesión actual del chat

// --- Añadir mensaje al chat ---
function addMessageToAIChat(role, message, dialogBox) {
    const messagesDiv = dialogBox.querySelector('#ai-chat-messages');
    if (!messagesDiv) return;

    // Eliminar "pensando..." si existe
    const thinking = messagesDiv.querySelector('.ai-thinking-dots');
    if (thinking) thinking.parentElement.remove();

    const bubble = document.createElement('div');
    bubble.classList.add('ai-chat-bubble', role);
    const span = document.createElement('span');
    span.textContent = message;
    bubble.appendChild(span);
    messagesDiv.appendChild(bubble);
    messagesDiv.scrollTop = messagesDiv.scrollHeight;
}

// --- Enviar mensaje a ai_chat.php ---
function sendAIChatMessage(message, plantNum, dialogBox) {
    const messagesDiv = dialogBox.querySelector('#ai-chat-messages');
    if (!messagesDiv) return;

    addMessageToAIChat('user', message, dialogBox);

    // Añadir "pensando..."
    const thinkingBubble = document.createElement('div');
    thinkingBubble.classList.add('ai-chat-bubble', 'ai');
    const thinkingSpan = document.createElement('span');
    thinkingSpan.classList.add('ai-thinking-dots');
    thinkingSpan.innerHTML = '<span>.</span><span>.</span><span>.</span>';
    thinkingBubble.appendChild(thinkingSpan);
    messagesDiv.appendChild(thinkingBubble);
    messagesDiv.scrollTop = messagesDiv.scrollHeight;

    // Prepara el historial para enviar
    const currentChatHistoryForAPI = aiChatHistory.map(entry => ({
        role: entry.role,
        content: entry.content
    }));


    fetch('ai_chat.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: new URLSearchParams({
            message: message,
            plant_num: plantNum,
            history: JSON.stringify(currentChatHistoryForAPI) // Enviar historial
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.error) {
            addMessageToAIChat('ai error', `Error: ${data.error}`, dialogBox);
        } else {
            addMessageToAIChat('ai', data.reply, dialogBox);
            // Actualizar historial global
            aiChatHistory.push({role: 'user', content: message});
            aiChatHistory.push({role: 'assistant', content: data.reply});
            // Limitar historial a N mensajes (ej. últimos 20)
            const maxHistoryLength = 20;
            if (aiChatHistory.length > maxHistoryLength) {
                aiChatHistory = aiChatHistory.slice(-maxHistoryLength);
            }
        }
    })
    .catch(error => {
        addMessageToAIChat('ai error', `Error de conexión: ${error}`, dialogBox);
    });
}

// --- Inicializar diálogo de chat IA ---
// Esta función se llama desde showAISuggestionsDialog
function initAIChatDialog(dialogBox, plantNum) {
    const chatContainer = dialogBox.querySelector('#ai-chat-container');
    const chatForm = dialogBox.querySelector('#ai-chat-form');
    const chatInput = dialogBox.querySelector('#ai-chat-input');
    const messagesDiv = dialogBox.querySelector('#ai-chat-messages');
    const chatCloseBtn = dialogBox.querySelector('#ai-chat-close');
    const chatExpandBtn = dialogBox.querySelector('#ai-chat-expand');

    if (!chatContainer || !chatForm || !chatInput || !messagesDiv || !chatCloseBtn || !chatExpandBtn) {
        console.error("Elementos del chat IA no encontrados en el diálogo.");
        return;
    }
    
    // Limpiar historial y mensajes al iniciar un nuevo diálogo de sugerencias
    aiChatHistory = [];
    messagesDiv.innerHTML = ''; // Limpiar mensajes previos

    chatForm.onsubmit = function(e) {
        e.preventDefault();
        const message = chatInput.value.trim();
        if (message) {
            sendAIChatMessage(message, plantNum, dialogBox);
            chatInput.value = '';
            chatInput.focus();
        }
    };

    chatCloseBtn.onclick = function() {
        chatContainer.style.display = 'none';
    };

    let isChatExpanded = false;
    chatExpandBtn.onclick = function() {
        isChatExpanded = !isChatExpanded;
        chatContainer.classList.toggle('ai-chat-expanded', isChatExpanded);
        chatExpandBtn.innerHTML = isChatExpanded ? '&#x2923;' : '&#x2922;'; // Cambiar icono
        chatExpandBtn.title = isChatExpanded ? 'Minimizar chat' : 'Expandir chat';
        // Forzar reflow para que el scroll funcione bien tras cambio de tamaño
        setTimeout(() => {
            if (messagesDiv) messagesDiv.scrollTop = messagesDiv.scrollHeight;
        }, 50);
    };
    
    // Añadir mensaje inicial de la IA si es necesario o deseado
    // addMessageToAIChat('ai', 'Hola, ¿cómo puedo ayudarte con esta planta?', dialogBox);
}
// --- END MOVED AI CHAT FUNCTIONS ---

// --- Diálogo resumen de sugerencias IA ---
function showAISuggestionsDialog(ai, plantNum) {
  // Eliminar cualquier diálogo previo
  let existing = document.getElementById('ai-suggestions-dialog');
  if (existing) existing.remove();

  // Imagen analizada (thumbnail 16:8)
  let analyzedImage = '';
  if (Array.isArray(currentGallery) && currentGallery.length > 0 && currentGallery[0]) {
    analyzedImage = currentGallery[0];
  }

  let html = `<h3 style="margin-top:0;">Sugerencias de la IA</h3>`;
  // --- Thumbnail justo debajo del título ---
  if (analyzedImage) {
    html += `
      <div id="ai-analyzed-thumb" style="width:100%;display:flex;justify-content:center;margin-bottom:14px;">
        <img src="${getImageUrl(analyzedImage)}" alt="Imagen analizada" style="display:block;width:100%;max-width:320px;aspect-ratio:2/1;height:auto;border-radius:8px;object-fit:cover;box-shadow:0 2px 10px rgba(0,0,0,0.08);background:#f0f0f0;">
      </div>
    `;
  }

  // Sugerencias con checkboxes
  html += `<form id="ai-apply-form"><div style="margin-bottom:12px;">`;
  if (ai.identificacion) {
    const [nombreComun, nombreCientifico] = ai.identificacion.split('\n');
    html += `<label style="display:block;margin-bottom:7px;"><input type="checkbox" name="identificacion" checked> <strong>Nombre sugerido:</strong> <span style="color:#58a45c">${nombreComun}</span>`;
    if (nombreCientifico) html += `<span style="color:#3a6b3a;font-style:italic;font-size:13px;display:block;">${nombreCientifico}</span>`;
    html += `</label>`;
  }
  if (ai.descripcion) {
    html += `<label style="display:block;margin-bottom:7px;"><input type="checkbox" name="descripcion" checked> <strong>Descripción sugerida:</strong> <span style="color:#58a45c">${ai.descripcion}</span></label>`;
  }
  if (ai.estado) {
    html += `<label style="display:block;margin-bottom:7px;"><input type="checkbox" name="estado" checked> <strong>Estado sugerido:</strong> <span style="color:#58a45c">${ai.estado}</span></label>`;
  }
  html += `</div>`;

  // Botones
  html += `
    <div class="ai-suggestions-actions" style="display:flex;gap:10px;margin-top:18px;flex-wrap:wrap;">
      <button id="ai-apply-selected" class="btn-ai-analyze ai-sugg-btn" type="submit" style="flex:1 1 90px;min-width:70px;">Aplicar</button>
      <button id="btn-ai-chat-suggestions" class="btn-ai-analyze ai-sugg-btn" type="button" style="flex:1 1 90px;min-width:70px;display:flex;align-items:center;justify-content:center;gap:7px;background:#66b58d;">
        Chat IA
      </button>
      <button id="ai-cancel" class="btn-ai-analyze ai-sugg-btn" type="button" style="flex:1 1 90px;min-width:70px;background:#eee;color:#333;">Cerrar</button>
    </div>
    </form>
    <button id="ai-close-x" style="position:absolute;top:8px;right:12px;background:none;border:none;font-size:20px;cursor:pointer;color:#aaa;">&times;</button>
    <div id="ai-chat-container" style="display:none;position:fixed;bottom:32px;right:32px;z-index:5000;width:370px;max-width:98vw;background:#fff;border-radius:16px;box-shadow:0 6px 32px rgba(0,0,0,0.18);overflow:hidden;transition:box-shadow 0.2s, width 0.2s, height 0.2s;display:flex;flex-direction:column;">
      <div style="background:#58a45c;color:#fff;padding:13px 18px 11px 18px;display:flex;align-items:center;justify-content:space-between;">
        <span style="font-weight:600;font-size:16px;letter-spacing:0.01em;">Asistente IA</span>
        <div style="display:flex;gap:8px;">
          <button id="ai-chat-expand" title="Expandir chat" style="background:none;border:none;color:#fff;font-size:18px;cursor:pointer;line-height:1;padding:0 4px 0 0;">&#x2922;</button>
          <button id="ai-chat-close" style="background:none;border:none;color:#fff;font-size:22px;cursor:pointer;line-height:1;padding:0 0 0 10px;">&times;</button>
        </div>
      </div>
      <div id="ai-chat-messages" class="ai-chat-messages" style="flex:1 1 auto;max-height:320px;min-height:120px;overflow-y:auto;padding:18px 12px 12px 12px;background:#f7faf7;scrollbar-width:thin;"></div>
      <form id="ai-chat-form" style="display:flex;border-top:1px solid #e0e8e0;background:#f7faf7;">
        <input type="text" id="ai-chat-input" placeholder="Pregunta a la IA..." autocomplete="off" style="flex:1;padding:12px 14px;font-size:15px;border:none;background:#f7faf7;">
        <button type="submit" style="padding:0 18px;background:#58a45c;color:#fff;border:none;border-radius:0 0 12px 0;cursor:pointer;font-size:16px;font-weight:600;">Enviar</button>
      </form>
    </div>
  `;

  // Crear el diálogo
  const dialog = document.createElement('div');
  dialog.id = 'ai-suggestions-dialog';
  dialog.style.position = 'fixed';
  dialog.style.top = '0';
  dialog.style.left = '0';
  dialog.style.width = '100vw';
  dialog.style.height = '100vh';
  dialog.style.background = 'rgba(0,0,0,0.35)';
  dialog.style.zIndex = '4000';
  dialog.style.display = 'flex';
  dialog.style.alignItems = 'center';
  dialog.style.justifyContent = 'center';

  const box = document.createElement('div');
  box.style.background = '#fff';
  box.style.borderRadius = '10px';
  box.style.boxShadow = '0 4px 24px rgba(0,0,0,0.18)';
  box.style.padding = '28px 24px 20px 24px';
  box.style.maxWidth = '90vw';
  box.style.width = '400px';
  box.style.textAlign = 'left';
  box.style.position = 'relative';
  box.innerHTML = html;

  dialog.appendChild(box);
  document.body.appendChild(dialog);

  // --- FUNCIONES DE APLICAR CAMBIOS ---
  // Reemplaza la función applyAISuggestions dentro de showAISuggestionsDialog por esta versión robusta:
  function applyAISuggestions(selected) {
    // Aplica los cambios uno a uno y espera a que cada uno termine antes de continuar (para evitar solapamientos de escritura)
    const updates = [];
    if (selected.estado && ai.estado) {
      updates.push(() =>
        updateFieldPromise(plantNum, "estado", ai.estado, true).then(() => {
          if (modalDetails) {
            const estadoElem = modalDetails.querySelector("[data-field='estado']");
            if (estadoElem) estadoElem.innerText = ai.estado;
          }
          document.querySelectorAll(`.plant-card[data-plant-num="${plantNum}"] .plant-estado`).forEach(span => {
            span.textContent = ai.estado;
          });
          originalFieldValues.estado = ai.estado;
        })
      );
    }
    if (selected.descripcion && ai.descripcion) {
      updates.push(() =>
        updateFieldPromise(plantNum, "descripcion", ai.descripcion, true).then(() => {
          if (modalDetails) {
            const descElem = modalDetails.querySelector("[data-field='descripcion']");
            if (descElem) descElem.innerText = ai.descripcion;
          }
          document.querySelectorAll(`.plant-card[data-plant-num="${plantNum}"] .plant-descripcion`).forEach(span => {
            span.textContent = ai.descripcion;
          });
          originalFieldValues.descripcion = ai.descripcion;
        })
      );
    }
    if (selected.identificacion && ai.identificacion) {
      updates.push(() =>
        updateFieldPromise(plantNum, "identificacion", ai.identificacion, true).then(() => {
          const [nombreComun, nombreCientifico] = ai.identificacion.split('\n');
          if (modalTitle) {
            if (nombreCientifico) {
              modalTitle.innerHTML = `<span class="nombre-comun">${nombreComun}</span><br><span class="nombre-cientifico" style="font-style:italic;color:#3a6b3a;font-size:16px;">${nombreCientifico}</span>`;
            } else {
              modalTitle.innerHTML = `<span class="nombre-comun">${nombreComun}</span>`;
            }
          }
          document.querySelectorAll(`.plant-card[data-plant-num="${plantNum}"] .plant-title`).forEach(cardTitle => {
            if (nombreCientifico) {
              cardTitle.innerHTML = `<span class="nombre-comun">${nombreComun}</span><span class="nombre-cientifico" style="font-style:italic;color:#3a6b3a;font-size:13px;display:block;">${nombreCientifico}</span>`;
            } else {
              cardTitle.innerHTML = `<span class="nombre-comun">${nombreComun}</span>`;
            }
          });
          originalFieldValues.identificacion = ai.identificacion;
        })
      );
    }

    // Ejecuta las actualizaciones en serie (no en paralelo)
    updates.reduce((p, fn) => p.then(fn), Promise.resolve());
  }

  // Helper: versión Promise de updateField para asegurar escritura secuencial
  function updateFieldPromise(plantNum, field, value, force = false) {
    return new Promise((resolve, reject) => {
      fetch('update_field.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: new URLSearchParams({ plant_num: plantNum, field: field, value: value })
      })
      .then(response => response.json())
      .then(data => {
        if (data.error) {
          alert("Error: " + data.error);
          reject(data.error);
          return;
        }
        // Log changes with old and new values for specific fields
        if (['identificacion', 'estado', 'descripcion'].includes(field)) {
          logPlantChange(plantNum, field, "", originalFieldValues[field], value);
        } else {
          logPlantChange(plantNum, field, value);
        }
        originalFieldValues[field] = value;
        resolve();
      })
      .catch(err => {
        alert("Error de red al guardar: " + err);
        reject(err);
      });
    });
  }

  // Botón aplicar (form submit)
  box.querySelector('#ai-apply-form').onsubmit = function(e) {
    e.preventDefault();
    const form = e.target;
    applyAISuggestions({
      estado: form.estado && form.estado.checked,
      descripcion: form.descripcion && form.descripcion.checked,
      identificacion: form.identificacion && form.identificacion.checked
    });
    dialog.remove();
  };
  box.querySelector('#ai-cancel').onclick = function() {
    dialog.remove();
  };
  box.querySelector('#ai-close-x').onclick = function() {
    dialog.remove();
  };

  // --- Chat con IA ---
  const chatBtn = box.querySelector('#btn-ai-chat-suggestions');
  const chatContainer = box.querySelector('#ai-chat-container');
  if (chatBtn && chatContainer) {
    chatContainer.style.display = 'none';
    chatBtn.onclick = function() {
      chatContainer.style.display = chatContainer.style.display === 'none' ? 'flex' : 'none';
      if (chatContainer.style.display === 'flex') {
        const chatMessages = chatContainer.querySelector('#ai-chat-messages');
        if (chatMessages && chatMessages.children.length === 0) {
          let saludo = "¡Hola! Soy tu asistente IA. ";
          if (ai.identificacion) {
            const [nombreComun, nombreCientifico] = ai.identificacion.split('\n');
            saludo += `Esta planta parece ser <strong>${escapeHTML(nombreComun)}</strong>`;
            if (nombreCientifico) saludo += ` (<span style='font-style:italic;color:#3a6b3a;'>${escapeHTML(nombreCientifico)}</span>)`;
            saludo += ". ";
          }
          if (ai.descripcion) {
            saludo += escapeHTML(ai.descripcion) + ". ";
          }
          saludo += "¿En qué puedo ayudarte con esta planta?";
          chatMessages.innerHTML = `<div class="ai-chat-bubble ai"><span>${saludo}</span></div>`;
        }
        setTimeout(() => {
          const input = chatContainer.querySelector('#ai-chat-input');
          if (input) input.focus();
        }, 100);
      }
    };
    // Cerrar chat con X
    const closeBtn = chatContainer.querySelector('#ai-chat-close');
    if (closeBtn) {
      closeBtn.onclick = function() {
        chatContainer.style.display = 'none';
      };
    }
    // Expandir chat
    const expandBtn = chatContainer.querySelector('#ai-chat-expand');
    if (expandBtn) {
      let expanded = false;
      expandBtn.onclick = function() {
        expanded = !expanded;
        if (expanded) {
          chatContainer.classList.add('ai-chat-expanded');
          expandBtn.title = "Reducir chat";
          expandBtn.innerHTML = "&#x2923;";
          const messages = chatContainer.querySelector('#ai-chat-messages');
          if (messages) {
            messages.style.maxHeight = "none";
            messages.style.minHeight = "0";
            messages.style.height = "100%";
            messages.style.flex = "1 1 auto";
          }
        } else {
          chatContainer.classList.remove('ai-chat-expanded');
          expandBtn.title = "Expandir chat";
          expandBtn.innerHTML = "&#x2922;";
          const messages = chatContainer.querySelector('#ai-chat-messages');
          if (messages) {
            messages.style.maxHeight = "320px";
            messages.style.minHeight = "120px";
            messages.style.height = "";
            messages.style.flex = "";
          }
        }
      };
    }
  }
  // Inicializar chat IA SIEMPRE después de crear el HTML
  initAIChatDialog(box, plantNum);
}

// Image upload handling
function handleImageUpload(event) {
  const file = event.target.files[0];
  if (!file) return;

  modalImage.classList.remove('loaded');
  modalImage.src = 'images/placeholder.jpg';

  const formData = new FormData();
  formData.append("nueva_imagen", file);
  formData.append("plant_num", currentPlantNum);

  fetch('upload.php', { method: 'POST', body: formData })
    .then(response => response.json())
    .then(data => {
      if (data.error) {
        alert("Error: " + data.error);
        return;
      }
      if (data.success === false && data.error) {
        alert(data.error);
        return;
      }

      // Show the image immediately
      currentGallery.unshift(data.imagen);
      currentIndex = 0;
      updateGalleryState();
      displayCurrentImage();
      updateCardImage(currentPlantNum, data.imagen);
      logPlantChange(currentPlantNum, "subida_imagen", "Nueva imagen añadida: " + file.name);

      // --- Guardar automáticamente la planta tras añadir la imagen ---
      if (modalDetails && typeof updateField === "function") {
        if (modalTitle && modalTitle.innerText && modalTitle.innerText !== originalFieldValues.identificacion) {
          updateField(currentPlantNum, "identificacion", modalTitle.innerText, true);
        }
        const estadoElem = modalDetails.querySelector("[data-field='estado']");
        if (estadoElem && estadoElem.innerText !== originalFieldValues.estado) {
          updateField(currentPlantNum, "estado", estadoElem.innerText, true);
        }
        const descElem = modalDetails.querySelector("[data-field='descripcion']");
        if (descElem && descElem.innerText !== originalFieldValues.descripcion) {
          updateField(currentPlantNum, "descripcion", descElem.innerText, true);
        }
        const zonaElem = modalDetails.querySelector("[data-field='zona']");
        if (zonaElem && zonaElem.innerText !== originalFieldValues.zona) {
          updateField(currentPlantNum, "zona", zonaElem.innerText, true);
        }
        const riegoElem = modalDetails.querySelector("[data-field='riego']");
        if (riegoElem && riegoElem.innerText !== originalFieldValues.riego) {
          updateField(currentPlantNum, "riego", riegoElem.innerText, true);
        }
        const sistemaRiegoElem = modalDetails.querySelector("[data-field='sistema_riego']");
        if (sistemaRiegoElem && sistemaRiegoElem.innerText !== originalFieldValues.sistema_riego) {
          updateField(currentPlantNum, "sistema_riego", sistemaRiegoElem.innerText, true);
        }
      }
    })
    .catch(error => {
      modalImage.src = 'images/placeholder.jpg';
      console.error("Error uploading image:", error);
      alert("Error al subir la imagen: " + error.message);
    });
}

// Image delete handling
function handleImageDelete() {
  if (!currentGallery || !currentGallery.length || typeof currentIndex === 'undefined') {
    alert("No hay imagen para eliminar.");
    return;
  }
  if (!confirm("¿Estás seguro de que deseas eliminar esta imagen?")) return;

  var formData = new FormData();
  formData.append('plant_num', currentPlantNum);
  formData.append('imagen', currentGallery[currentIndex]);

  fetch('delete_image.php', {
    method: 'POST',
    body: formData
  })
  .then(response => {
    // Try to parse JSON, but handle parse errors gracefully
    return response.text().then(text => {
      try {
        return JSON.parse(text);
      } catch (e) {
        throw new Error("Respuesta inesperada del servidor: " + text);
      }
    });
  })
  .then(data => {
    if (data.success) {
      // Remove image from gallery and update modal
      currentGallery.splice(currentIndex, 1);
      if (currentIndex >= currentGallery.length) currentIndex = Math.max(0, currentGallery.length - 1);
      displayCurrentImage();
      showNotification('Imagen eliminada exitosamente', 'success');
    } else {
      alert(data.error || 'Error al eliminar la imagen');
    }
  })
  .catch(error => {
    alert('Error de conexión al eliminar la imagen: ' + error.message);
  });
}

// Horizontal scroll functionality
function initHorizontalScrolling() {
  document.querySelectorAll('.scroll-button').forEach(button => {
    button.addEventListener('click', function() {
      const wrapper = this.closest('.zone-section'); // Ensure this selector is correct
      if (!wrapper) {
        // console.warn("Scroll button clicked, but no '.zone-section' parent found.", this);
        return;
      }
      const container = wrapper.querySelector('.card-container'); // Ensure this selector is correct
      if (!container) {
        // console.warn("Scroll button clicked, but no '.card-container' found in zone.", wrapper);
        return;
      }
      
      const scrollAmount = container.clientWidth * 0.7; // Scroll 70% of visible width
      if (this.classList.contains('scroll-left')) {
        container.scrollBy({ left: -scrollAmount, behavior: 'smooth' });
      } else {
        container.scrollBy({ left: scrollAmount, behavior: 'smooth' });
      }
    });
  });
  
  // Add scroll tracking to show/hide buttons
  document.querySelectorAll('.card-container').forEach(container => {
    const updateScrollButtons = function() {
      const zoneSection = container.closest('.zone-section');
      if (!zoneSection) return;

      const scrollLeftButton = zoneSection.querySelector('.scroll-button.scroll-left');
      const scrollRightButton = zoneSection.querySelector('.scroll-button.scroll-right');

      if (!scrollLeftButton || !scrollRightButton) return;

      const maxScrollLeft = container.scrollWidth - container.clientWidth;
      
      // A small tolerance for floating point inaccuracies
      scrollLeftButton.disabled = container.scrollLeft < 1;
      scrollRightButton.disabled = container.scrollLeft > maxScrollLeft - 1;

      // Hide buttons if no scroll is possible
      if (container.scrollWidth <= container.clientWidth) {
        scrollLeftButton.style.display = 'none';
        scrollRightButton.style.display = 'none';
      } else {
        scrollLeftButton.style.display = '';
        scrollRightButton.style.display = '';
      }
    };
    
    container.addEventListener('scroll', debounce(updateScrollButtons, 50));
    // Initialize button states
    // Use a small timeout to ensure layout is stable for initial calculation
    setTimeout(() => updateScrollButtons(), 100); 
    // Also listen for window resize
    window.addEventListener('resize', debounce(updateScrollButtons, 100));
  });
}

// Add this function to avoid the ReferenceError
function fetchPlantHistory(plantNum) {
  // Fetch and render plant history in the modal
  fetch('get_history.php?plant_num=' + encodeURIComponent(plantNum))
    .then(response => response.json())
    .then(history => {
      const tbody = document.getElementById('history-tbody');
      if (!tbody) return;
      tbody.innerHTML = '';
      if (!Array.isArray(history) || history.length === 0) {
        tbody.innerHTML = '<tr><td colspan="5" style="text-align:center;color:#999;">No hay historial registrado</td></tr>';
        return;
      }
      history.forEach(entry => {
        const row = document.createElement('tr');
        row.innerHTML = `
          <td>${entry.fecha || 'N/A'}</td>
          ${isLoggedIn ? `<td>${entry.usuario || 'N/A'}</td>` : ''}
          <td>${entry.accion || 'N/A'}</td>
          <td>${entry.detalles || 'N/A'}</td>
          ${isLoggedIn ? `<td><button class="btn-delete-log" onclick="deleteLogEntry(${plantNum}, '${entry.fecha || ''}')" title="Eliminar entrada">🗑️</button></td>` : ''}
        `;
        tbody.appendChild(row);
      });
    })
    .catch(() => {
      const tbody = document.getElementById('history-tbody');
      if (tbody) {
        tbody.innerHTML = '<tr><td colspan="5" style="text-align:center;color:#999;">Error al cargar historial</td></tr>';
      }
    });
}

// Añade esta función global para registrar cambios en el historial (logPlantChange)
function logPlantChange(plantNum, accion, detalles = "", old_value = null, new_value = null) {
  const params = new URLSearchParams({
    plant_num: plantNum,
    accion: accion,
    detalles: detalles
  });
  if (old_value !== null && old_value !== undefined) params.append('old_value', old_value);
  if (new_value !== null && new_value !== undefined) params.append('new_value', new_value);

  fetch('log_change.php', {
    method: 'POST',
    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
    body: params
  })
  .then(r => r.json())
  .then(data => {
    // Opcional: puedes refrescar el historial aquí si quieres
    // fetchPlantHistory(plantNum);
  })
  .catch(() => {
    // Silenciar errores de log para no molestar al usuario
  });
}

// Eliminar entrada de historial
function deleteLogEntry(plantNum, fecha) {
  if (!plantNum || !fecha) return;
  if (!confirm('¿Eliminar esta entrada del historial?')) return;

  fetch('delete_log.php', {
    method: 'POST',
    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
    body: new URLSearchParams({ plant_num: plantNum, fecha: fecha })
  })
  .then(r => r.json())
  .then(data => {
    if (data.error) {
      alert('Error al eliminar: ' + data.error);
      return;
    }
    fetchPlantHistory(plantNum);
  })
  .catch(err => {
    alert('Error de red al eliminar: ' + err.message);
  });
}

// --- START FULLSCREEN VIEWER FUNCTIONALITY ---
function initFullscreenViewer() {
  const modalImageElement = document.getElementById('modal-image');
  const fullscreenViewer = document.getElementById('fullscreen-viewer');
  const fullscreenImage = document.getElementById('fullscreen-image');
  const fullscreenClose = document.getElementById('fullscreen-close');
  const fullscreenPrev = document.getElementById('fullscreen-prev');
  const fullscreenNext = document.getElementById('fullscreen-next');

  if (!modalImageElement || !fullscreenViewer || !fullscreenImage || !fullscreenClose || !fullscreenPrev || !fullscreenNext) {
    console.warn('Fullscreen viewer elements not found.');
    return;
  }

  // Open fullscreen viewer
  modalImageElement.addEventListener('click', function() {
    if (currentGallery && currentGallery.length > 0) {
      fullscreenImage.src = getImageUrl(currentGallery[currentIndex]);
      fullscreenViewer.classList.add('active');
      document.body.style.overflow = 'hidden'; // Lock body scroll for fullscreen
    }
  });

  // Close fullscreen viewer
  fullscreenClose.addEventListener('click', function() {
    fullscreenViewer.classList.remove('active');
    document.body.style.overflow = ''; // Unlock body scroll
  });

  // Fullscreen previous image
  fullscreenPrev.addEventListener('click', function(e) {
    e.stopPropagation();
    if (currentGallery && currentGallery.length > 1) {
      prevImage(); // This already calls updateFullscreenImage
    }
  });

  // Fullscreen next image
  fullscreenNext.addEventListener('click', function(e) {
    e.stopPropagation();
    if (currentGallery && currentGallery.length > 1) {
      nextImage(); // This already calls updateFullscreenImage
    }
  });

  // Update fullscreen button states (similar to modal nav buttons)
  function updateFullscreenNavButtons() {
    if (currentGallery && currentGallery.length > 1) {
      fullscreenPrev.disabled = false;
      fullscreenNext.disabled = false;
    } else {
      fullscreenPrev.disabled = true;
      fullscreenNext.disabled = true;
    }
  }

  // Call it initially and whenever the gallery might change (e.g., after image upload/delete, or when modal opens)
  // For simplicity, we can call this from displayCurrentImage or openModal
  // Adding it to displayCurrentImage as it's called whenever the image changes.
  const originalDisplayCurrentImage = displayCurrentImage;
  window.displayCurrentImage = function() {
    originalDisplayCurrentImage.apply(this, arguments);
    if (fullscreenViewer.classList.contains('active')) { // Only if fullscreen is active
        updateFullscreenImage(); // Ensure fullscreen image is also updated
    }
    updateFullscreenNavButtons();
  };
   // Also update when modal opens
  const originalOpenModal = window.openModal;
  window.openModal = function() {
    originalOpenModal.apply(this, arguments);
    updateFullscreenNavButtons();
  };
}
// --- END FULLSCREEN VIEWER FUNCTIONALITY ---

// Event listeners setup
function setupEventListeners() {
    // Plant card clicks
    document.addEventListener('click', function(e) {
        var plantCard = e.target.closest('.plant-card:not(.add-plant-card)');
        if (plantCard) {
            var plantNum = plantCard.dataset.plantNum;
            if (plantNum) {
                openPlantModal(parseInt(plantNum));
            }
        }
    });
    
    // Modal close
    var closeBtn = document.getElementById('close-x-button');
    if (closeBtn) {
        closeBtn.addEventListener('click', closeModal);
    }
    
    var backBtn = document.getElementById('btn-back');
    if (backBtn) {
        backBtn.addEventListener('click', closeModal);
    }
    
    // Gallery navigation
    var leftArrow = document.querySelector('.gallery .arrow.left');
    var rightArrow = document.querySelector('.gallery .arrow.right');
    
    if (leftArrow) {
        leftArrow.addEventListener('click', function() {
            if (currentImageIndex > 0) {
                currentImageIndex--;
                updateModalImage();
            }
        });
    }
    
    if (rightArrow) {
        rightArrow.addEventListener('click', function() {
            if (currentImageIndex < plantImages.length - 1) {
                currentImageIndex++;
                updateModalImage();
            }
        });
    }
    
    // Image deletion
    var deleteBtn = document.getElementById('btn-delete');
    if (deleteBtn) {
        deleteBtn.addEventListener('click', function() {
            if (typeof isLoggedIn !== 'undefined' && isLoggedIn && currentPlantNum && plantImages.length > 0) {
                deleteCurrentImage();
            }
        });
    }
    
    // Close modal when clicking outside
    var plantModal = document.getElementById('plant-modal');
    if (plantModal) {
        plantModal.addEventListener('click', function(e) {
            if (e.target === this) {
                closeModal();
            }
        });
    }
}

// Add the missing deleteCurrentImage function
function deleteCurrentImage() {
    if (!currentPlantNum || !plantImages.length || currentImageIndex >= plantImages.length) {
        showNotification('No hay imagen para eliminar', 'warning');
        return;
    }
    
    var imageToDelete = plantImages[currentImageIndex];
    if (!imageToDelete) {
        showNotification('Imagen no válida', 'error');
        return;
    }
    
    if (!confirm('¿Estás seguro de que quieres eliminar esta imagen?')) {
        return;
    }
    
    // Show loading state
    var deleteBtn = document.getElementById('btn-delete');
    if (deleteBtn) {
        deleteBtn.style.opacity = '0.6';
        deleteBtn.style.pointerEvents = 'none';
        var iconLabel = deleteBtn.querySelector('.icon-label');
        if (iconLabel) iconLabel.textContent = 'Eliminando...';
    }
    
    showNotification('Eliminando imagen...', 'info');
    
    var formData = new FormData();
    formData.append('plant_num', currentPlantNum);
    formData.append('imagen', imageToDelete);
    
    fetch('delete_image.php', {
        method: 'POST',
        body: formData
    })
    .then(function(response) {
        if (!response.ok) {
            throw new Error('HTTP error! status: ' + response.status);
        }
        return response.json();
    })
    .then(function(data) {
        if (data.success) {
            // Remove the image from the local array
            plantImages.splice(currentImageIndex, 1);
            
            // Adjust current index if necessary
            if (currentImageIndex >= plantImages.length) {
                currentImageIndex = Math.max(0, plantImages.length - 1);
            }
            
            // Update the modal image display
            updateModalImage();
            
            // Update the plant data in allPlants array
            var updatedPlant = allPlants.find(function(p) { return p.num == currentPlantNum; });
            if (updatedPlant) {
                updatedPlant.imagenes = plantImages.slice(); // Update with current array
                // Update the plant card image in the catalog
                updatePlantCardImage(currentPlantNum, updatedPlant.imagenes);
            }
            
            // Log the change
            logChange(currentPlantNum, 'eliminacion_imagen', imageToDelete, '', 'Imagen eliminada: ' + imageToDelete);
            
            showNotification('Imagen eliminada exitosamente', 'success');
        } else {
            throw new Error(data.error || 'Error desconocido al eliminar la imagen');
        }
    })
    .catch(function(error) {
        console.error('Delete error:', error);
        var errorMessage = 'Error al eliminar la imagen';
        
        if (error.message) {
            errorMessage = error.message;
        }
        
        showNotification(errorMessage, 'error');
    })
    .finally(function() {
        // Restore delete button
        if (deleteBtn) {
            deleteBtn.style.opacity = '1';
            deleteBtn.style.pointerEvents = 'auto';
            var iconLabel = deleteBtn.querySelector('.icon-label');
            if (iconLabel) iconLabel.textContent = 'Eliminar';
        }
    });
}
