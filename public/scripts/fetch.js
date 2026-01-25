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
    PORTFOLIO_DATA: '/api/portfolio/data',
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

// ============================================
// LAZY LOADING CEN - Szybkie ładowanie strony
// ============================================

/**
 * Formatuje liczbę jako walutę PLN
 */
function formatCurrency(value) {
    return new Intl.NumberFormat('pl-PL', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
    }).format(value);
}

/**
 * Formatuje procent
 */
function formatPercent(value) {
    const sign = value >= 0 ? '+' : '';
    return sign + value.toFixed(1) + ' %';
}

/**
 * Aktualizuje kartę portfela danymi ze świeżych cen
 */
function updatePortfolioCard(portfolioId, data) {
    const card = document.querySelector(`[data-portfolio-id="${portfolioId}"]`);
    if (!card) return;
    
    // Aktualizuj wartość
    const valueEl = card.querySelector('.portfolio-value');
    if (valueEl) {
        valueEl.textContent = formatCurrency(data.total_value) + ' PLN';
    }
    
    // Aktualizuj badge zysku
    const profitBadge = card.querySelector('.profit-badge');
    if (profitBadge) {
        const isPositive = data.total_profit_percent >= 0;
        profitBadge.className = `stat-badge-sm ${isPositive ? 'positive' : 'negative'} profit-badge`;
        profitBadge.textContent = formatPercent(data.total_profit_percent);
    }
    
    // Aktualizuj kwotę zysku
    const profitAmount = card.querySelector('.profit-amount');
    if (profitAmount) {
        const isPositive = data.total_profit >= 0;
        profitAmount.className = `${isPositive ? 'positive' : 'negative'} profit-amount`;
        profitAmount.textContent = (data.total_profit >= 0 ? '+' : '') + formatCurrency(data.total_profit) + ' PLN';
    }
    
    // Aktualizuj statystyki w kartach
    const dailyChangePercent = card.querySelector('.daily-change-percent');
    if (dailyChangePercent) {
        const isPositive = data.daily_change_percent >= 0;
        dailyChangePercent.className = `portfolio-stat-value stat-badge-sm ${isPositive ? 'positive' : 'negative'} daily-change-percent`;
        dailyChangePercent.textContent = (data.daily_change_percent >= 0 ? '+' : '') + data.daily_change_percent.toFixed(2) + ' %';
    }
    
    const dailyChange = card.querySelector('.daily-change');
    if (dailyChange) {
        const isPositive = data.daily_change >= 0;
        dailyChange.className = `portfolio-stat-value stat-badge-sm ${isPositive ? 'positive' : 'negative'} daily-change`;
        dailyChange.textContent = (data.daily_change >= 0 ? '+' : '') + formatCurrency(data.daily_change) + ' PLN';
    }
    
    // Usuń klasę loading
    card.classList.remove('loading');
}

/**
 * Aktualizuje podsumowanie globalne
 */
function updateGlobalStats(data) {
    // Total value
    const totalValueEl = document.querySelector('[data-stat="total-value"]');
    if (totalValueEl) {
        totalValueEl.textContent = formatCurrency(data.total_value) + ' PLN';
    }
    
    // Total profit
    const totalProfitEl = document.querySelector('[data-stat="total-profit"]');
    if (totalProfitEl) {
        const isPositive = data.total_profit >= 0;
        totalProfitEl.className = `stat-value ${isPositive ? 'positive' : 'negative'}`;
        totalProfitEl.textContent = (data.total_profit >= 0 ? '+' : '') + formatCurrency(data.total_profit) + ' PLN';
    }
    
    // Daily change
    const dailyChangeEl = document.querySelector('[data-stat="daily-change"]');
    if (dailyChangeEl) {
        const isPositive = data.daily_change >= 0;
        dailyChangeEl.className = `stat-value ${isPositive ? 'positive' : 'negative'}`;
        dailyChangeEl.textContent = (data.daily_change >= 0 ? '+' : '') + formatCurrency(data.daily_change) + ' PLN';
    }
}

/**
 * Pobiera świeże ceny w tle i aktualizuje UI
 */
async function refreshPricesInBackground() {
    // Pokaż indicator ładowania
    const refreshIndicator = document.getElementById('refreshIndicator');
    if (refreshIndicator) {
        refreshIndicator.style.display = 'inline-flex';
    }
    
    try {
        const response = await fetch(API_ENDPOINTS.PORTFOLIO_DATA);
        const result = await response.json();
        
        if (result.success) {
            // Aktualizuj każdą kartę portfela
            for (const [portfolioId, data] of Object.entries(result.portfolios)) {
                updatePortfolioCard(portfolioId, data);
            }
            
            // Aktualizuj globalne statystyki
            if (result.total) {
                updateGlobalStats(result.total);
            }
            
            // Pokaż czas aktualizacji
            const updateTimeEl = document.getElementById('lastUpdateTime');
            if (updateTimeEl) {
                updateTimeEl.textContent = result.updated_at;
            }
        }
    } catch (error) {
        console.error('Błąd odświeżania cen:', error);
    } finally {
        if (refreshIndicator) {
            refreshIndicator.style.display = 'none';
        }
    }
}

/**
 * Pokazuje modal potwierdzenia usunięcia portfela
 */
let pendingPortfolioDelete = null;

function confirmDeletePortfolio(portfolioId, portfolioName) {
    pendingPortfolioDelete = portfolioId;
    
    const modal = document.getElementById('deletePortfolioModal');
    const nameEl = document.getElementById('deletePortfolioName');
    
    if (modal && nameEl) {
        nameEl.textContent = portfolioName;
        modal.style.display = 'flex';
    } else {
        if (confirm(`Czy na pewno chcesz usunąć portfel "${portfolioName}"?\nWszystkie aktywa i transakcje zostaną usunięte!`)) {
            executeDeletePortfolio(portfolioId);
        }
    }
}

/**
 * Wykonuje usunięcie portfela
 */
async function executeDeletePortfolio(portfolioId = null) {
    const id = portfolioId || pendingPortfolioDelete;
    if (!id) return;
    
    closeModal('deletePortfolioModal');
    
    try {
        const result = await postJSON(API_ENDPOINTS.PORTFOLIO_DELETE, { portfolio_id: id });
        
        if (result.success) {
            showToast(result.message || 'Portfel usunięty', 'success');
            
            // Animacja usunięcia karty
            const card = document.querySelector(`[data-portfolio-id="${id}"]`);
            if (card) {
                card.style.transition = 'all 0.3s ease';
                card.style.opacity = '0';
                card.style.transform = 'scale(0.9)';
                setTimeout(() => {
                    card.remove();
                    // Jeśli nie ma już portfeli - przeładuj
                    const remaining = document.querySelectorAll('[data-portfolio-id]');
                    if (remaining.length === 0) {
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
    
    pendingPortfolioDelete = null;
}

// ============================================
// AUTO-REFRESH na Dashboard
// ============================================

// Odśwież ceny automatycznie po załadowaniu strony (tylko na dashboard)
document.addEventListener('DOMContentLoaded', function() {
    // Sprawdź czy jesteśmy na dashboardzie
    const portfolioCards = document.querySelectorAll('[data-portfolio-id]');
    
    if (portfolioCards.length > 0) {
        // Odśwież ceny po 500ms (daj stronie się załadować)
        setTimeout(refreshPricesInBackground, 500);
        
        // Auto-refresh co 60 sekund
        setInterval(refreshPricesInBackground, 60000);
    }
});

// Eksportuj nowe funkcje
window.refreshPricesInBackground = refreshPricesInBackground;
window.confirmDeletePortfolio = confirmDeletePortfolio;
window.executeDeletePortfolio = executeDeletePortfolio;
