-- DATABASE SCHEMA: PROCUREMENT SYSTEM (PostgreSQL 14)
-- ===============================================

-- 1. Master Data: Departments
CREATE TABLE departments (
    id BIGSERIAL PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL
);

-- 2. Master Data: Users
CREATE TABLE users (
    id BIGSERIAL PRIMARY KEY,
    department_id BIGINT NOT NULL,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    role VARCHAR(50) NOT NULL, 
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL,
    FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE RESTRICT
);

-- 3. Master Data: Vendors
CREATE TABLE vendors (
    id BIGSERIAL PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    contact_info VARCHAR(255),
    address TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL
);

-- 4. Master Data: Stock (Inventory)
CREATE TABLE stock (
    id BIGSERIAL PRIMARY KEY,
    sku VARCHAR(50) UNIQUE NOT NULL,
    name VARCHAR(255) NOT NULL,
    category VARCHAR(100) NOT NULL,
    quantity INT NOT NULL DEFAULT 0,
    unit_price DECIMAL(15, 2) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL
);

-- 5. Transaction: Requests (Header)
-- Status: DRAFT, SUBMITTED, VERIFIED, APPROVED, REJECTED, CHECKING_STOCK, IN_PROCUREMENT, COMPLETED
CREATE TABLE requests (
    id BIGSERIAL PRIMARY KEY,
    user_id BIGINT NOT NULL,
    department_id BIGINT NOT NULL,
    status VARCHAR(50) NOT NULL DEFAULT 'DRAFT', 
    total_amount DECIMAL(15, 2) DEFAULT 0,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE RESTRICT,
    FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE RESTRICT
);

-- 6. Transaction: Request Items (Detail)
CREATE TABLE request_items (
    id BIGSERIAL PRIMARY KEY,
    request_id BIGINT NOT NULL,
    stock_id BIGINT NOT NULL,
    qty_requested INT NOT NULL,
    snapshot_price DECIMAL(15, 2) NOT NULL, 
    subtotal DECIMAL(15, 2) NOT NULL,
    FOREIGN KEY (request_id) REFERENCES requests(id) ON DELETE CASCADE,
    FOREIGN KEY (stock_id) REFERENCES stock(id) ON DELETE RESTRICT
);

-- 7. Transaction: Approvals Audit
CREATE TABLE approvals (
    id BIGSERIAL PRIMARY KEY,
    request_id BIGINT NOT NULL,
    approver_id BIGINT NOT NULL,
    action VARCHAR(50) NOT NULL, 
    comments TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (request_id) REFERENCES requests(id) ON DELETE CASCADE,
    FOREIGN KEY (approver_id) REFERENCES users(id) ON DELETE RESTRICT
);

-- 8. Transaction: Procurement Orders (PO Vendor)
CREATE TABLE procurement_orders (
    id BIGSERIAL PRIMARY KEY,
    request_id BIGINT UNIQUE NOT NULL, 
    vendor_id BIGINT NOT NULL,
    po_number VARCHAR(100) UNIQUE NOT NULL,
    status VARCHAR(50) NOT NULL DEFAULT 'ORDERED', 
    total_cost DECIMAL(15, 2) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL,
    FOREIGN KEY (request_id) REFERENCES requests(id) ON DELETE RESTRICT,
    FOREIGN KEY (vendor_id) REFERENCES vendors(id) ON DELETE RESTRICT
);

-- 9. Transaction Track: Status History (Audit Trail)
CREATE TABLE status_history (
    id BIGSERIAL PRIMARY KEY,
    request_id BIGINT NOT NULL,
    user_id BIGINT NOT NULL, 
    previous_status VARCHAR(50),
    new_status VARCHAR(50) NOT NULL,
    changed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (request_id) REFERENCES requests(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE RESTRICT
);

-- STRATEGI INDEXING
CREATE INDEX idx_requests_status ON requests(status);
CREATE INDEX idx_requests_created_at ON requests(created_at);
CREATE INDEX idx_stock_category ON stock(category);
CREATE INDEX idx_users_department ON users(department_id);

-- ===============================================
-- REPORTING QUERIES
-- ===============================================

-- 1. Top 5 department dengan permintaan terbanyak dalam 3 bulan terakhir
SELECT d.name, COUNT(r.id) AS total_requests
FROM requests r
JOIN departments d ON r.department_id = d.id
WHERE r.created_at >= CURRENT_DATE - INTERVAL '3 months'
GROUP BY d.id, d.name
ORDER BY total_requests DESC
LIMIT 5;

-- 2. Kategori barang paling banyak diminta per bulan
SELECT DISTINCT ON (bulan)
    DATE_TRUNC('month', r.created_at)::DATE AS bulan,
    s.category,
    SUM(ri.qty_requested) AS total_qty
FROM requests r
JOIN request_items ri ON r.id = ri.request_id
JOIN stock s ON ri.stock_id = s.id
GROUP BY DATE_TRUNC('month', r.created_at), s.category
ORDER BY bulan DESC, total_qty DESC;

-- 3. Average lead time dari SUBMITTED hingga COMPLETED
WITH RequestTimestamps AS (
    SELECT request_id,
        MIN(CASE WHEN new_status = 'SUBMITTED' THEN changed_at END) AS submitted_time,
        MAX(CASE WHEN new_status = 'COMPLETED' THEN changed_at END) AS completed_time
    FROM status_history
    GROUP BY request_id
)
SELECT AVG(completed_time - submitted_time) AS average_lead_time
FROM RequestTimestamps
WHERE submitted_time IS NOT NULL AND completed_time IS NOT NULL;
