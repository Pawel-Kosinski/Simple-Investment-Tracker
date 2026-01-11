/**
 * Fetch API - Asynchroniczne operacje CRUD
 * Investment Tracker
 * 
 * Ten plik implementuje komunikację z serwerem za pomocą Fetch API
 * zamiast tradycyjnych formularzy z przeładowaniem strony.
 */

// ============================================
// KONFIGURACJA ENDPOINTÓW API
// ============================================

const API_ENDPOINTS = {
    PORTFOLIO_CREATE: '/api/portfolio/create',
    PORTFOLIO_DELETE: '/api/portfolio/delete',
    ASSET_DELETE: '/api/assets/delete',
    TRANSACTION_DELETE: '/api/transactions/delete'
};

// ============================================
// TOAST NOTIFICATIONS (Powiadomienia)
// ============================================

/**
 * Wyświetla powiadomienie toast
 * @param {string} message - treść komunikatu
 * @param {string} type - 'success' | 'error' | 'warning'
 */
function showToast(message, type = 'success') {
    // Usuń istniejące toasty
    document.querySelectorAll('.toast-notification').forEach(t => t.remove());
    
    const toast = document.createElement('div');
    toast.className = `toast-notification toast-${type}`;
    toast.innerHTML = `
        <span class="toast-message">${message}</span>
        <button class="toast-close" onclick="this.parentElement.remove()">&times;</button>
    `;
    
    document.body.appendChild(toast);
    
    // Animacja wejścia
    requestAnimationFrame(() => {
        toast.classList.add('toast-visible');
    });
    
    // Auto-ukryj po 5 sekundach
    setTimeout(() => {
        toast.classList.remove('toast-visible');
        setTimeout(() => toast.remove(), 300);
    }, 5000);
}

// ============================================
// POMOCNICZE FUNKCJE FETCH
// ============================================

/**
 * Wykonuje żądanie POST z Fetch API
 * @param {string} url - endpoint API
 * @param {Object} data - dane do wysłania
 * @returns {Promise<Object>} - odpowiedź JSON
 */
async function postJSON(url, data = {}) {
    const formData = new FormData();
    
    for (const [key, value] of Object.entries(data)) {
        formData.append(key, value);
    }
    
    const response = await fetch(url, {
        method: 'POST',
        body: formData,
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        }
    });
    
    if (!response.ok) {
        throw new Error(`HTTP ${response.status}: ${response.statusText}`);
    }
    
    return response.json();
}

/**
 * Ustawia stan ładowania na przycisku
 * @param {HTMLButtonElement} button 
 * @param {boolean} loading 
 */
function setButtonLoading(button, loading) {
    if (loading) {
        button.disabled = true;
        button.dataset.originalText = button.textContent;
        button.innerHTML = '<span class="spinner"></span> Ładowanie...';
    } else {
        button.disabled = false;
        button.textContent = button.dataset.originalText || 'OK';
    }
}

// ============================================
// PORTFOLIO - TWORZENIE (Fetch API)
// ============================================

/**
 * Tworzy nowy portfel asynchronicznie
 * @param {Event} event - zdarzenie submit formularza
 */
async function createPortfolio(event) {
    event.preventDefault();
    
    const form = event.target;
    const submitBtn = form.querySelector('button[type="submit"]');
    
    // Pobierz dane
    const name = form.querySelector('input[name="name"]').value.trim();
    const description = form.querySelector('textarea[name="description"]')?.value.trim() || '';
    
    // Walidacja kliencka
    if (!name) {
        showToast('Nazwa portfela jest wymagana', 'error');
        return;
    }
    
    if (name.length > 100) {
        showToast('Nazwa portfela jest za długa (max 100 znaków)', 'error');
        return;
    }
    
    setButtonLoading(submitBtn, true);
    
    try {
        const result = await postJSON(API_ENDPOINTS.PORTFOLIO_CREATE, { name, description });
        
        if (result.success) {
            showToast(result.message || 'Portfel utworzony!', 'success');
            closeModal('createPortfolioModal');
            
            // Odśwież stronę po krótkiej chwili
            setTimeout(() => location.reload(), 1000);
        } else {
            showToast(result.error || 'Błąd podczas tworzenia portfela', 'error');
        }
    } catch (error) {
        console.error('Fetch error:', error);
        showToast('Błąd połączenia z serwerem', 'error');
    } finally {
        setButtonLoading(submitBtn, false);
    }
}

// ============================================
// PORTFOLIO - USUWANIE (Fetch API)
// ============================================

/**
 * Usuwa portfel asynchronicznie
 * @param {number} portfolioId - ID portfela
 */
async function deletePortfolio(portfolioId) {
    if (!confirm('Czy na pewno chcesz usunąć ten portfel?\nWszystkie aktywa i transakcje zostaną usunięte!')) {
        return;
    }
    
    try {
        const result = await postJSON(API_ENDPOINTS.PORTFOLIO_DELETE, { portfolio_id: portfolioId });
        
        if (result.success) {
            showToast(result.message || 'Portfel usunięty', 'success');
            setTimeout(() => location.reload(), 1000);
        } else {
            showToast(result.error || 'Błąd podczas usuwania portfela', 'error');
        }
    } catch (error) {
        console.error('Fetch error:', error);
        showToast('Błąd połączenia z serwerem', 'error');
    }
}

// ============================================
// AKTYWA - USUWANIE (Fetch API)
// ============================================

let pendingAssetDelete = null;

/**
 * Pokazuje modal potwierdzenia usunięcia aktywa
 * @param {number} assetId - ID aktywa
 * @param {string} symbol - symbol aktywa
 */
function confirmDeleteAsset(assetId, symbol) {
    pendingAssetDelete = assetId;
    
    const modal = document.getElementById('deleteModal');
    const nameEl = document.getElementById('deleteAssetName');
    
    if (modal && nameEl) {
        nameEl.textContent = symbol;
        modal.style.display = 'flex';
    } else {
        // Fallback bez modala
        if (confirm(`Czy na pewno chcesz usunąć aktywo ${symbol}?`)) {
            executeDeleteAsset(assetId);
        }
    }
}

/**
 * Wykonuje usunięcie aktywa
 * @param {number} assetId - ID aktywa (opcjonalne, użyje pendingAssetDelete)
 */
async function executeDeleteAsset(assetId = null) {
    const id = assetId || pendingAssetDelete;
    if (!id) return;
    
    closeModal('deleteModal');
    
    try {
        const result = await postJSON(API_ENDPOINTS.ASSET_DELETE, { asset_id: id });
        
        if (result.success) {
            showToast(result.message || 'Aktywo usunięte', 'success');
            
            // Animacja usunięcia wiersza
            const row = document.querySelector(`tr[data-asset-id="${id}"]`);
            if (row) {
                row.style.transition = 'all 0.3s ease';
                row.style.opacity = '0';
                row.style.transform = 'translateX(-20px)';
                setTimeout(() => {
                    row.remove();
                    // Sprawdź czy tabela jest pusta
                    const tbody = document.querySelector('.data-table tbody');
                    if (tbody && tbody.children.length === 0) {
                        location.reload();
                    }
                }, 300);
            } else {
                setTimeout(() => location.reload(), 1000);
            }
        } else {
            showToast(result.error || 'Błąd podczas usuwania', 'error');
        }
    } catch (error) {
        console.error('Fetch error:', error);
        showToast('Błąd połączenia z serwerem', 'error');
    }
    
    pendingAssetDelete = null;
}

// ============================================
// TRANSAKCJE - USUWANIE (Fetch API)
// ============================================

let pendingTransactionDelete = null;

/**
 * Pokazuje modal potwierdzenia cofnięcia transakcji
 * @param {number} transactionId - ID transakcji
 */
function confirmDeleteTransaction(transactionId) {
    pendingTransactionDelete = transactionId;
    
    const modal = document.getElementById('deleteTransactionModal');
    if (modal) {
        modal.style.display = 'flex';
    } else {
        // Fallback bez modala
        if (confirm('Czy na pewno chcesz cofnąć tę transakcję?')) {
            executeDeleteTransaction(transactionId);
        }
    }
}

/**
 * Wykonuje cofnięcie transakcji
 * @param {number} transactionId - ID transakcji (opcjonalne)
 */
async function executeDeleteTransaction(transactionId = null) {
    const id = transactionId || pendingTransactionDelete;
    if (!id) return;
    
    closeModal('deleteTransactionModal');
    
    try {
        const result = await postJSON(API_ENDPOINTS.TRANSACTION_DELETE, { transaction_id: id });
        
        if (result.success) {
            showToast(result.message || 'Transakcja cofnięta', 'success');
            
            // Animacja usunięcia wiersza
            const row = document.querySelector(`tr[data-transaction-id="${id}"]`);
            if (row) {
                row.style.transition = 'all 0.3s ease';
                row.style.opacity = '0';
                row.style.transform = 'translateX(-20px)';
                setTimeout(() => row.remove(), 300);
            } else {
                setTimeout(() => location.reload(), 1000);
            }
        } else {
            showToast(result.error || 'Błąd podczas cofania transakcji', 'error');
        }
    } catch (error) {
        console.error('Fetch error:', error);
        showToast('Błąd połączenia z serwerem', 'error');
    }
    
    pendingTransactionDelete = null;
}

// ============================================
// POMOCNICZE - MODALNE
// ============================================

/**
 * Zamyka modal o podanym ID
 * @param {string} modalId 
 */
function closeModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.style.display = 'none';
    }
}

/**
 * Otwiera modal o podanym ID
 * @param {string} modalId 
 */
function openModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.style.display = 'flex';
    }
}

// ============================================
// INICJALIZACJA
// ============================================

document.addEventListener('DOMContentLoaded', function() {
    // Podpięcie formularza tworzenia portfela
    const portfolioForm = document.querySelector('#createPortfolioModal form');
    if (portfolioForm) {
        portfolioForm.addEventListener('submit', createPortfolio);
    }
    
    // Zamykanie modali po kliknięciu w tło
    document.querySelectorAll('.modal').forEach(modal => {
        modal.addEventListener('click', function(e) {
            if (e.target === this) {
                this.style.display = 'none';
            }
        });
    });
});

// ============================================
// EKSPORT GLOBALNY (dla onclick w HTML)
// ============================================

window.showToast = showToast;
window.createPortfolio = createPortfolio;
window.deletePortfolio = deletePortfolio;
window.confirmDeleteAsset = confirmDeleteAsset;
window.executeDeleteAsset = executeDeleteAsset;
window.confirmDeleteTransaction = confirmDeleteTransaction;
window.executeDeleteTransaction = executeDeleteTransaction;
window.closeModal = closeModal;
window.openModal = openModal;

// Aliasy dla kompatybilności wstecznej
window.confirmDelete = function(id, symbol) {
    if (symbol !== undefined) {
        confirmDeleteAsset(id, symbol);
    } else {
        confirmDeleteTransaction(id);
    }
};
