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

-- Indeksy dla wydajności
CREATE INDEX idx_portfolios_user ON portfolios(user_id);
CREATE INDEX idx_transactions_portfolio ON transactions(portfolio_id);
CREATE INDEX idx_transactions_asset ON transactions(asset_id);
CREATE INDEX idx_transactions_date ON transactions(transaction_date DESC);
CREATE INDEX idx_price_cache_asset ON price_cache(asset_id);
CREATE INDEX idx_price_cache_fetched ON price_cache(fetched_at DESC);

-- Testowy użytkownik (hasło: test123)
INSERT INTO users (email, password, firstname, lastname) VALUES 
('test@example.com', '$2y$10$iJxJgdeW9xx1uJDXV388eeilDdJDXxTKBWswsn0vrYvsry4CBnDwK', 'Jan', 'Kowalski');

-- Portfele testowego użytkownika
INSERT INTO portfolios (user_id, name, description, is_default) VALUES
(1, 'Główny', 'Mój główny portfel inwestycyjny', TRUE),
(1, 'Emerytura', 'Długoterminowe inwestycje na emeryturę', FALSE),
(1, 'Spekulacje', 'Krótkoterminowe pozycje', FALSE);

-- Przykładowe aktywa
INSERT INTO assets (symbol, name, asset_type, currency, yahoo_symbol) VALUES
('CDR', 'CD Projekt RED', 'stock', 'PLN', 'CDR.WA'),
('PKO', 'PKO Bank Polski', 'stock', 'PLN', 'PKO.WA'),
('PZU', 'PZU SA', 'stock', 'PLN', 'PZU.WA'),
('KGH', 'KGHM Polska Miedź', 'stock', 'PLN', 'KGH.WA'),
('PKN', 'PKN Orlen', 'stock', 'PLN', 'PKN.WA'),
('AAPL.US', 'Apple Inc.', 'stock', 'USD', 'AAPL'),
('MSFT.US', 'Microsoft Corporation', 'stock', 'USD', 'MSFT'),
('NVDA.US', 'NVIDIA Corporation', 'stock', 'USD', 'NVDA'),
('VWRA.UK', 'Vanguard FTSE All-World ETF', 'etf', 'USD', 'VWRA.L'),
('CSPX.UK', 'iShares Core S&P 500 ETF', 'etf', 'USD', 'CSPX.L');

-- Przykładowe transakcje (portfolio_id: 1=Główny, 2=Emerytura, 3=Spekulacje)
INSERT INTO transactions (portfolio_id, asset_id, transaction_type, quantity, price, commission, transaction_date, notes) VALUES
(1, 1, 'buy', 10, 285.50, 5.00, '2024-01-15 10:30:00', 'Pierwsza inwestycja w CDR'),
(1, 1, 'buy', 5, 270.00, 3.50, '2024-03-20 14:15:00', 'Dokupienie na spadku'),
(1, 2, 'buy', 50, 45.20, 8.00, '2024-02-10 09:45:00', 'PKO - dywidenda'),
(2, 9, 'buy', 20, 95.30, 15.00, '2024-01-20 11:00:00', 'ETF globalny - emerytura'),
(2, 10, 'buy', 15, 480.00, 12.00, '2024-02-15 10:00:00', 'S&P 500 ETF'),
(3, 6, 'buy', 3, 178.50, 12.00, '2024-04-05 16:00:00', 'Apple - spekulacja'),
(1, 1, 'sell', 5, 310.00, 4.00, '2024-06-10 13:30:00', 'Realizacja zysku');