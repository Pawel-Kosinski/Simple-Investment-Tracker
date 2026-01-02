-- =============================================
-- Simple Investment Tracker - Database Schema
-- =============================================

-- Użytkownicy
CREATE TABLE users (
    id SERIAL PRIMARY KEY,
    email VARCHAR(150) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    firstname VARCHAR(100) NOT NULL,
    lastname VARCHAR(100) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    enabled BOOLEAN DEFAULT TRUE
);

-- Portfele użytkowników
CREATE TABLE portfolios (
    id SERIAL PRIMARY KEY,
    user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    is_default BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Aktywa (instrumenty finansowe - globalny słownik)
CREATE TABLE assets (
    id SERIAL PRIMARY KEY,
    symbol VARCHAR(30) NOT NULL,
    name VARCHAR(200),
    asset_type VARCHAR(50) DEFAULT 'stock',
    currency VARCHAR(10) DEFAULT 'PLN',
    yahoo_symbol VARCHAR(30),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(symbol)
);

-- Transakcje (przypisane do portfela)
CREATE TABLE transactions (
    id SERIAL PRIMARY KEY,
    portfolio_id INTEGER NOT NULL REFERENCES portfolios(id) ON DELETE CASCADE,
    asset_id INTEGER NOT NULL REFERENCES assets(id) ON DELETE CASCADE,
    transaction_type VARCHAR(20) NOT NULL,
    quantity DECIMAL(18,8) NOT NULL,
    price DECIMAL(18,4) NOT NULL,
    commission DECIMAL(18,4) DEFAULT 0,
    transaction_date TIMESTAMP NOT NULL,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Cache cen (do przechowywania pobranych kursów)
CREATE TABLE price_cache (
    id SERIAL PRIMARY KEY,
    asset_id INTEGER NOT NULL REFERENCES assets(id) ON DELETE CASCADE,
    price DECIMAL(18,4) NOT NULL,
    currency VARCHAR(10) DEFAULT 'PLN',
    fetched_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Obligacje skarbowe (dodatkowe szczegóły dla asset_type='bond')
CREATE TABLE bonds (
    id SERIAL PRIMARY KEY,
    asset_id INTEGER UNIQUE NOT NULL REFERENCES assets(id) ON DELETE CASCADE,
    bond_code VARCHAR(50) NOT NULL, -- np. 'DOS0527', 'COI0128'
    bond_type VARCHAR(50) NOT NULL, -- 'DOS', 'COI', 'EDO', 'TOS', 'ROD'
    issue_date DATE NOT NULL,
    maturity_date DATE NOT NULL,
    interest_rate DECIMAL(6,4), -- stała stopa procentowa (dla DOS, TOS)
    first_year_rate DECIMAL(6,4), -- oprocentowanie w pierwszym okresie (dla COI, EDO, ROS, ROD)
    interest_margin DECIMAL(6,4), -- marża (dla COI - relatywna do inflacji)
    interest_frequency VARCHAR(20) DEFAULT 'monthly', -- 'monthly', 'quarterly', 'annual'
    rate_base VARCHAR(20), -- 'inflation' dla COI/EDO/ROS/ROD, 'nbp_rate' dla ROR/DOR, NULL dla OTS/TOS
    nominal_value DECIMAL(15,2) DEFAULT 100.00, -- wartość nominalna (standardowo 100 PLN)
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Historia importu
CREATE TABLE import_history (
    id SERIAL PRIMARY KEY,
    user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    asset_id INTEGER REFERENCES assets(id) ON DELETE CASCADE,
    xtb_operation_id BIGINT NOT NULL,
    imported_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(user_id, xtb_operation_id)
);

-- Indeksy dla wydajności
CREATE INDEX idx_portfolios_user ON portfolios(user_id);
CREATE INDEX idx_transactions_portfolio ON transactions(portfolio_id);
CREATE INDEX idx_transactions_asset ON transactions(asset_id);
CREATE INDEX idx_transactions_date ON transactions(transaction_date DESC);
CREATE INDEX idx_price_cache_asset ON price_cache(asset_id);
CREATE INDEX idx_price_cache_fetched ON price_cache(fetched_at DESC);
CREATE INDEX idx_bonds_asset ON bonds(asset_id);

-- Testowy użytkownik (hasło: test123)
INSERT INTO users (email, password, firstname, lastname) VALUES 
('test@example.com', '$2y$10$iJxJgdeW9xx1uJDXV388eeilDdJDXxTKBWswsn0vrYvsry4CBnDwK', 'Jan', 'Kowalski');

-- Portfele testowego użytkownika
INSERT INTO portfolios (user_id, name, description, is_default) VALUES
(1, 'IMPORT', 'Krótkoterminowe pozycje', FALSE);

-- Przykładowe aktywa

-- Przykładowe transakcje (portfolio_id: 1=Główny, 2=Emerytura, 3=Spekulacje)

-- Usuń stare dane obligacji
DELETE FROM transactions WHERE asset_id IN (11, 12);
DELETE FROM bonds WHERE asset_id IN (11, 12);
DELETE FROM assets WHERE id IN (11, 12);

-- Tabela cache kursów walut
CREATE TABLE IF NOT EXISTS currency_cache (
    id SERIAL PRIMARY KEY,
    currency VARCHAR(10) NOT NULL,
    rate DECIMAL(12,6) NOT NULL,
    fetched_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX idx_currency_cache_currency ON currency_cache(currency);
CREATE INDEX idx_currency_cache_fetched_at ON currency_cache(fetched_at);
