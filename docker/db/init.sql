CREATE TABLE users (
    id SERIAL PRIMARY KEY,
    email VARCHAR(150) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    firstname VARCHAR(100) NOT NULL,
    lastname VARCHAR(100) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    enabled BOOLEAN DEFAULT TRUE
);

-- Aktywa
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

-- Transakcje
CREATE TABLE transactions (
    id SERIAL PRIMARY KEY,
    user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
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
CREATE INDEX idx_transactions_user ON transactions(user_id);
CREATE INDEX idx_transactions_asset ON transactions(asset_id);
CREATE INDEX idx_transactions_date ON transactions(transaction_date DESC);
CREATE INDEX idx_price_cache_asset ON price_cache(asset_id);
CREATE INDEX idx_price_cache_fetched ON price_cache(fetched_at DESC);