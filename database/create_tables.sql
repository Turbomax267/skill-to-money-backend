-- ============================================================
-- SCRIPT PARA CREAR TABLAS FALTANTES (DataGrip / MySQL)
-- skill-to-money-backend
-- ============================================================

-- 1. TABLAS DE SUSCRIPCIONES
-- ============================================================

CREATE TABLE IF NOT EXISTS subscriptions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    plan VARCHAR(30) NOT NULL DEFAULT 'pro',
    status VARCHAR(30) NOT NULL DEFAULT 'active' INDEX,
    billing_cycle VARCHAR(30) NOT NULL DEFAULT 'monthly',
    amount DECIMAL(10,2) NOT NULL DEFAULT 29.00,
    currency VARCHAR(10) NOT NULL DEFAULT 'PEN',
    source VARCHAR(50) NOT NULL DEFAULT 'skillpay_demo',
    starts_at TIMESTAMP NULL,
    ends_at TIMESTAMP NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    INDEX idx_user_status (user_id, status),
    CONSTRAINT fk_subscriptions_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS subscription_payments (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    subscription_id BIGINT UNSIGNED NULL,
    plan VARCHAR(30) NOT NULL DEFAULT 'pro',
    amount DECIMAL(10,2) NOT NULL DEFAULT 29.00,
    currency VARCHAR(10) NOT NULL DEFAULT 'PEN',
    payment_method VARCHAR(30) NOT NULL,
    provider VARCHAR(50) NOT NULL DEFAULT 'skillpay_demo',
    provider_reference VARCHAR(80) NOT NULL UNIQUE,
    card_brand VARCHAR(30) NULL,
    card_last_four VARCHAR(4) NULL,
    status VARCHAR(30) NOT NULL DEFAULT 'succeeded' INDEX,
    metadata JSON NULL,
    paid_at TIMESTAMP NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    INDEX idx_user_status (user_id, status),
    CONSTRAINT fk_subpayments_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_subpayments_subscription FOREIGN KEY (subscription_id) REFERENCES subscriptions(id) ON DELETE SET NULL
);

-- 2. TABLAS DE CONTRATOS, WALLET, ESCRÓ, ENTREGAS, DISPUTAS
-- ============================================================

CREATE TABLE IF NOT EXISTS contracts (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    contract_number VARCHAR(255) NOT NULL UNIQUE,
    mype_profile_id BIGINT UNSIGNED NOT NULL,
    freelancer_profile_id BIGINT UNSIGNED NOT NULL,
    service_id BIGINT UNSIGNED NULL,
    client_project_id BIGINT UNSIGNED NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT NULL,
    amount DECIMAL(10,2) NOT NULL,
    currency VARCHAR(3) NOT NULL DEFAULT 'PEN',
    status VARCHAR(255) NOT NULL DEFAULT 'pending_payment' INDEX,
    provider VARCHAR(255) NOT NULL DEFAULT 'mock',
    terms JSON NULL,
    started_at TIMESTAMP NULL,
    submitted_at TIMESTAMP NULL,
    approved_at TIMESTAMP NULL,
    released_at TIMESTAMP NULL,
    cancelled_at TIMESTAMP NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    deleted_at TIMESTAMP NULL,
    CONSTRAINT fk_contracts_mype FOREIGN KEY (mype_profile_id) REFERENCES mype_profiles(id) ON DELETE CASCADE,
    CONSTRAINT fk_contracts_freelancer FOREIGN KEY (freelancer_profile_id) REFERENCES freelancer_profiles(id) ON DELETE CASCADE,
    CONSTRAINT fk_contracts_service FOREIGN KEY (service_id) REFERENCES services(id) ON DELETE SET NULL,
    CONSTRAINT fk_contracts_project FOREIGN KEY (client_project_id) REFERENCES client_projects(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS payments (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    contract_id BIGINT UNSIGNED NOT NULL,
    payer_user_id BIGINT UNSIGNED NOT NULL,
    provider VARCHAR(255) NOT NULL DEFAULT 'mock',
    provider_reference VARCHAR(255) NULL UNIQUE,
    amount DECIMAL(10,2) NOT NULL,
    currency VARCHAR(3) NOT NULL DEFAULT 'PEN',
    status VARCHAR(255) NOT NULL DEFAULT 'pending' INDEX,
    paid_at TIMESTAMP NULL,
    metadata JSON NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    CONSTRAINT fk_payments_contract FOREIGN KEY (contract_id) REFERENCES contracts(id) ON DELETE CASCADE,
    CONSTRAINT fk_payments_user FOREIGN KEY (payer_user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS escrows (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    contract_id BIGINT UNSIGNED NOT NULL UNIQUE,
    payment_id BIGINT UNSIGNED NULL,
    amount DECIMAL(10,2) NOT NULL,
    currency VARCHAR(3) NOT NULL DEFAULT 'PEN',
    status VARCHAR(255) NOT NULL DEFAULT 'pending' INDEX,
    held_at TIMESTAMP NULL,
    released_at TIMESTAMP NULL,
    refunded_at TIMESTAMP NULL,
    metadata JSON NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    CONSTRAINT fk_escrows_contract FOREIGN KEY (contract_id) REFERENCES contracts(id) ON DELETE CASCADE,
    CONSTRAINT fk_escrows_payment FOREIGN KEY (payment_id) REFERENCES payments(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS wallets (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL UNIQUE,
    available_balance DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    pending_balance DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    currency VARCHAR(3) NOT NULL DEFAULT 'PEN',
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    CONSTRAINT fk_wallets_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS wallet_transactions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    wallet_id BIGINT UNSIGNED NOT NULL,
    contract_id BIGINT UNSIGNED NULL,
    type VARCHAR(255) NOT NULL INDEX,
    direction VARCHAR(255) NOT NULL INDEX,
    amount DECIMAL(10,2) NOT NULL,
    currency VARCHAR(3) NOT NULL DEFAULT 'PEN',
    available_after DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    description VARCHAR(255) NULL,
    metadata JSON NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    CONSTRAINT fk_wallettx_wallet FOREIGN KEY (wallet_id) REFERENCES wallets(id) ON DELETE CASCADE,
    CONSTRAINT fk_wallettx_contract FOREIGN KEY (contract_id) REFERENCES contracts(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS deliveries (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    contract_id BIGINT UNSIGNED NOT NULL,
    freelancer_profile_id BIGINT UNSIGNED NOT NULL,
    title VARCHAR(255) NULL,
    message TEXT NULL,
    status VARCHAR(255) NOT NULL DEFAULT 'submitted_for_review' INDEX,
    revision_round INT UNSIGNED NOT NULL DEFAULT 1,
    submitted_at TIMESTAMP NULL,
    reviewed_at TIMESTAMP NULL,
    review_comment TEXT NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    CONSTRAINT fk_deliveries_contract FOREIGN KEY (contract_id) REFERENCES contracts(id) ON DELETE CASCADE,
    CONSTRAINT fk_deliveries_freelancer FOREIGN KEY (freelancer_profile_id) REFERENCES freelancer_profiles(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS delivery_files (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    delivery_id BIGINT UNSIGNED NOT NULL,
    original_name VARCHAR(255) NOT NULL,
    file_path VARCHAR(255) NOT NULL,
    mime_type VARCHAR(255) NULL,
    size BIGINT UNSIGNED NULL,
    is_preview TINYINT(1) NOT NULL DEFAULT 1,
    is_final TINYINT(1) NOT NULL DEFAULT 0,
    downloadable TINYINT(1) NOT NULL DEFAULT 0,
    watermark_text VARCHAR(255) NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    CONSTRAINT fk_deliveryfiles_delivery FOREIGN KEY (delivery_id) REFERENCES deliveries(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS disputes (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    contract_id BIGINT UNSIGNED NOT NULL,
    opened_by_user_id BIGINT UNSIGNED NOT NULL,
    status VARCHAR(255) NOT NULL DEFAULT 'open' INDEX,
    reason TEXT NOT NULL,
    resolution VARCHAR(255) NULL,
    admin_user_id BIGINT UNSIGNED NULL,
    admin_comment TEXT NULL,
    resolved_at TIMESTAMP NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    CONSTRAINT fk_disputes_contract FOREIGN KEY (contract_id) REFERENCES contracts(id) ON DELETE CASCADE,
    CONSTRAINT fk_disputes_opener FOREIGN KEY (opened_by_user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_disputes_admin FOREIGN KEY (admin_user_id) REFERENCES users(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS withdrawal_requests (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    wallet_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    currency VARCHAR(3) NOT NULL DEFAULT 'PEN',
    provider VARCHAR(255) NOT NULL DEFAULT 'mock',
    status VARCHAR(255) NOT NULL DEFAULT 'pending' INDEX,
    provider_reference VARCHAR(255) NULL,
    requested_at TIMESTAMP NULL,
    processed_at TIMESTAMP NULL,
    metadata JSON NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    CONSTRAINT fk_withdrawals_wallet FOREIGN KEY (wallet_id) REFERENCES wallets(id) ON DELETE CASCADE,
    CONSTRAINT fk_withdrawals_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);
