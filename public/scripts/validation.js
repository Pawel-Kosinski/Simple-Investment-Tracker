/**
 * Dynamiczna walidacja formularza rejestracji
 * Investment Tracker
 */

// Pobieranie elementów formularza (DOM)
const form = document.querySelector("form");
const emailInput = form.querySelector('input[name="email"]');
const firstnameInput = form.querySelector('input[name="firstname"]');
const lastnameInput = form.querySelector('input[name="lastname"]');
const passwordInput = form.querySelector('input[name="password"]');
const confirmedPasswordInput = form.querySelector('input[name="password2"]');

// Zmienne do przechowywania timerów debounce
let emailTimer = null;
let firstnameTimer = null;
let lastnameTimer = null;
let passwordTimer = null;
let confirmedPasswordTimer = null;

// Czas opóźnienia debounce (ms)
const DEBOUNCE_DELAY = 1000;

// ============================================
// FUNKCJE WALIDUJĄCE
// ============================================

/**
 * Sprawdza czy podany ciąg znaków ma strukturę adresu email
 * @param {string} email 
 * @returns {boolean}
 */
function isEmail(email) {
    return /\S+@\S+\.\S+/.test(email);
}

/**
 * Sprawdza czy hasło ma minimum 6 znaków (silne hasło)
 * @param {string} password 
 * @returns {boolean}
 */
function isStrongPassword(password) {
    return password.length >= 6;
}

/**
 * Sprawdza czy hasła są identyczne
 * @param {string} password 
 * @param {string} confirmedPassword 
 * @returns {boolean}
 */
function arePasswordsSame(password, confirmedPassword) {
    return password === confirmedPassword;
}

/**
 * Sprawdza czy imię/nazwisko jest poprawne:
 * - minimum 2 znaki
 * - tylko litery (w tym polskie znaki)
 * - dozwolona spacja i myślnik
 * @param {string} name 
 * @returns {boolean}
 */
function isValidName(name) {
    // Minimum 2 znaki
    if (name.length < 2) {
        return false;
    }
    
    // Regex: tylko litery (w tym polskie), spacja i myślnik
    // Polskie znaki: ąćęłńóśźżĄĆĘŁŃÓŚŹŻ
    const nameRegex = /^[a-zA-ZąćęłńóśźżĄĆĘŁŃÓŚŹŻ\s\-]+$/;
    return nameRegex.test(name);
}

// ============================================
// OZNACZANIE BŁĘDÓW WIZUALNIE
// ============================================

/**
 * Oznacza element jako poprawny lub niepoprawny
 * @param {HTMLElement} element 
 * @param {boolean} condition - true = poprawny, false = błąd
 */
function markValidation(element, condition) {
    if (!condition) {
        element.classList.add('no-valid');
    } else {
        element.classList.remove('no-valid');
    }
}

// ============================================
// FUNKCJE WALIDACJI Z DEBOUNCE
// ============================================

/**
 * Walidacja email z opóźnieniem 
 */
function validateEmail() {
    // Anuluj poprzedni timer jeśli istnieje
    if (emailTimer) {
        clearTimeout(emailTimer);
    }
    
    emailTimer = setTimeout(function() {
        markValidation(emailInput, isEmail(emailInput.value));
    }, DEBOUNCE_DELAY);
}

/**
 * Walidacja imienia z opóźnieniem 
 */
function validateFirstname() {
    if (firstnameTimer) {
        clearTimeout(firstnameTimer);
    }
    
    firstnameTimer = setTimeout(function() {
        markValidation(firstnameInput, isValidName(firstnameInput.value));
    }, DEBOUNCE_DELAY);
}

/**
 * Walidacja nazwiska z opóźnieniem 
 */
function validateLastname() {
    if (lastnameTimer) {
        clearTimeout(lastnameTimer);
    }
    
    lastnameTimer = setTimeout(function() {
        markValidation(lastnameInput, isValidName(lastnameInput.value));
    }, DEBOUNCE_DELAY);
}

/**
 * Walidacja hasła (min 6 znaków) z opóźnieniem 
 */
function validatePassword() {
    if (passwordTimer) {
        clearTimeout(passwordTimer);
    }
    
    passwordTimer = setTimeout(function() {
        markValidation(passwordInput, isStrongPassword(passwordInput.value));
        
        // Jeśli hasło potwierdzające jest wypełnione, sprawdź też zgodność
        if (confirmedPasswordInput.value.length > 0) {
            validateConfirmedPasswordNow();
        }
    }, DEBOUNCE_DELAY);
}

/**
 * Walidacja potwierdzenia hasła z opóźnieniem 
 */
function validateConfirmedPassword() {
    if (confirmedPasswordTimer) {
        clearTimeout(confirmedPasswordTimer);
    }
    
    confirmedPasswordTimer = setTimeout(function() {
        validateConfirmedPasswordNow();
    }, DEBOUNCE_DELAY);
}

/**
 * Natychmiastowa walidacja zgodności haseł (bez debounce)
 */
function validateConfirmedPasswordNow() {
    const condition = arePasswordsSame(
        passwordInput.value,
        confirmedPasswordInput.value
    );
    markValidation(confirmedPasswordInput, condition);
}

// ============================================
// WALIDACJA PRZED WYSŁANIEM FORMULARZA
// ============================================

/**
 * Walidacja całego formularza przed wysłaniem
 * @returns {boolean} - true jeśli formularz jest poprawny
 */
function validateForm() {
    let isValid = true;
    
    // Walidacja email
    if (!isEmail(emailInput.value)) {
        markValidation(emailInput, false);
        isValid = false;
    } else {
        markValidation(emailInput, true);
    }
    
    // Walidacja imienia
    if (!isValidName(firstnameInput.value)) {
        markValidation(firstnameInput, false);
        isValid = false;
    } else {
        markValidation(firstnameInput, true);
    }
    
    // Walidacja nazwiska
    if (!isValidName(lastnameInput.value)) {
        markValidation(lastnameInput, false);
        isValid = false;
    } else {
        markValidation(lastnameInput, true);
    }
    
    // Walidacja hasła (min 6 znaków)
    if (!isStrongPassword(passwordInput.value)) {
        markValidation(passwordInput, false);
        isValid = false;
    } else {
        markValidation(passwordInput, true);
    }
    
    // Walidacja zgodności haseł
    if (!arePasswordsSame(passwordInput.value, confirmedPasswordInput.value)) {
        markValidation(confirmedPasswordInput, false);
        isValid = false;
    } else {
        markValidation(confirmedPasswordInput, true);
    }
    
    return isValid;
}

// ============================================
// OBSŁUGA ZDARZEŃ
// ============================================

// Nasłuchiwanie zdarzeń keyup na polach formularza
emailInput.addEventListener('keyup', validateEmail);
firstnameInput.addEventListener('keyup', validateFirstname);
lastnameInput.addEventListener('keyup', validateLastname);
passwordInput.addEventListener('keyup', validatePassword);
confirmedPasswordInput.addEventListener('keyup', validateConfirmedPassword);

// Walidacja przed wysłaniem formularza
form.addEventListener('submit', function(event) {
    if (!validateForm()) {
        event.preventDefault();
        
        // Przewiń do pierwszego błędnego pola
        const firstError = form.querySelector('.no-valid');
        if (firstError) {
            firstError.focus();
        }
    }
});