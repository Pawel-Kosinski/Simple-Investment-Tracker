# Simple Investment Tracker

Prosta aplikacja webowa do zarządzania portfelem inwestycyjnym z automatycznym pobieraniem cen akcji i przeliczaniem walut. Akcje i obligacje - wszystko w jednym miejscu.

## Technologie

| Warstwa | Technologia |
|---------|-------------|
| Backend | PHP 8.1+ |
| Baza danych | PostgreSQL 15 |
| Frontend | HTML5, CSS3, JavaScript |
| Konteneryzacja | Docker (PHP-FPM, Nginx, PostgreSQL) |
| API zewnętrzne | Yahoo Finance, NBP API |

## Architektura MVC

```
lab05/
├── src/
│   ├── controllers/     # Logika aplikacji
│   ├── models/          # Encje (User, Portfolio, Asset, Transaction, Bond)
│   ├── repository/      # Warstwa dostępu do danych
│   ├── services/        # Serwisy (PriceService, CurrencyService, XtbImportService)
│   └── middleware/      # Middleware (AllowedMethods)
├── public/
│   ├── views/           # Szablony HTML
│   ├── styles/          # CSS (app.css, login.css)
│   └── scripts/         # JavaScript (fetch.js, validation.js)
├── docker/              # Konfiguracja Docker
├── Routing.php          # Router aplikacji
└── Database.php         # Singleton połączenia z bazą
```

## Funkcjonalności

- **Zarządzanie portfelami** - tworzenie, edycja, usuwanie wielu portfeli
- **Import transakcji** - import z plików CSV (format XTB)
- **Aktualizacja cen** - automatyczne pobieranie z Yahoo Finance API
- **Przeliczanie walut** - kursy z NBP API
- **Obligacje skarbowe** - obsługa DOS, COI, EDO, TOS z naliczaniem odsetek
- **Dashboard** - podsumowanie wartości portfela, zyski/straty, zmiana dzienna
- **System użytkowników** - rejestracja, logowanie, sesje, role

## Bezpieczeństwo

| Zagrożenie | Zabezpieczenie |
|------------|----------------|
| SQL Injection | PDO Prepared Statements |
| XSS | `htmlspecialchars()` |
| Hasła | bcrypt (`password_hash`) |
| Sesje | `session_regenerate_id()` |
| Uprawnienia | Weryfikacja właściciela zasobów |

## Uruchomienie

```bash
# Klonowanie repozytorium
git clone https://github.com/user/investment-tracker.git
cd investment-tracker

# Uruchomienie kontenerów Docker
docker-compose up -d

# Aplikacja dostępna pod adresem
http://localhost:8080

# Dane testowe
Email: test@example.com
Hasło: test123
```

## API Endpoints

| Metoda | Endpoint | Opis |
|--------|----------|------|
| GET/POST | `/login` | Logowanie |
| GET/POST | `/register` | Rejestracja |
| GET | `/logout` | Wylogowanie |
| GET | `/dashboard` | Panel główny |
| GET | `/assets` | Lista aktywów |
| GET | `/transactions` | Lista transakcji |
| POST | `/api/portfolio/create` | Tworzenie portfela (AJAX) |
| POST | `/api/portfolio/delete` | Usuwanie portfela (AJAX) |
| GET | `/api/portfolio/data` | Dane portfela (lazy loading) |

## Wzorce projektowe

- **MVC** - separacja logiki, widoków i danych
- **Repository Pattern** - abstrakcja dostępu do bazy danych
- **Singleton** - pojedyncza instancja połączenia z bazą
- **Factory Method** - `fromArray()` w modelach

