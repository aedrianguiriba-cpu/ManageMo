-- ManageMo PostgreSQL Schema for Supabase
-- Run this in the Supabase SQL Editor (Dashboard → SQL Editor → New Query)

-- ─────────────────────────────────────────────
-- 1. TABLES
-- ─────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS users (
    id          BIGSERIAL PRIMARY KEY,
    email       TEXT UNIQUE NOT NULL,
    password    TEXT NOT NULL,
    full_name   TEXT NOT NULL,
    role        TEXT NOT NULL DEFAULT 'user' CHECK (role IN ('admin','user')),
    campus_id   INT  NOT NULL DEFAULT 1,
    college_id  TEXT,
    phone       TEXT,
    is_active   INT  NOT NULL DEFAULT 1,
    created_at  TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at  TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE TABLE IF NOT EXISTS inventory (
    id                   BIGSERIAL PRIMARY KEY,
    qr_code_id           TEXT UNIQUE NOT NULL,
    item_name            TEXT NOT NULL,
    category             TEXT NOT NULL,
    description          TEXT,
    campus_id            INT  NOT NULL DEFAULT 1,
    college_id           TEXT,
    quantity             INT  NOT NULL DEFAULT 1,
    status               TEXT NOT NULL DEFAULT 'available'
                             CHECK (status IN ('available','borrowed','requested','maintenance','damaged','condemned','disposed')),
    location             TEXT,
    purchase_date        DATE,
    cost                 NUMERIC(12,2),
    condition            TEXT CHECK (condition IN ('excellent','good','fair','poor')),
    condemnation_reason  TEXT,
    condemned_at         TIMESTAMPTZ,
    disposal_notes       TEXT,
    disposed_at          TIMESTAMPTZ,
    group_id             TEXT,
    created_at           TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE TABLE IF NOT EXISTS requests (
    id                   BIGSERIAL PRIMARY KEY,
    request_number       TEXT UNIQUE NOT NULL,
    user_id              INT  NOT NULL,
    inventory_id         INT,
    request_type         TEXT NOT NULL CHECK (request_type IN ('borrow','item','service')),
    urgency              TEXT NOT NULL DEFAULT 'medium'
                             CHECK (urgency IN ('low','medium','high','critical')),
    receiving_method     TEXT CHECK (receiving_method IN ('delivery','pickup')),
    reason_for_request   TEXT,
    service_description  TEXT,
    expected_return_date DATE,
    quantity_requested   INT  NOT NULL DEFAULT 1,
    status               TEXT NOT NULL DEFAULT 'pending'
                             CHECK (status IN ('pending','approved','disapproved','delivered','completed')),
    delivery_status      TEXT CHECK (delivery_status IN ('out_for_delivery','delivered')),
    approved_by          INT,
    approved_at          TIMESTAMPTZ,
    created_at           TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at           TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE TABLE IF NOT EXISTS borrow_records (
    id                   BIGSERIAL PRIMARY KEY,
    user_id              INT  NOT NULL,
    inventory_id         INT  NOT NULL,
    request_id           INT,
    borrow_date          DATE NOT NULL,
    expected_return_date DATE,
    actual_return_date   DATE,
    status               TEXT NOT NULL DEFAULT 'active'
                             CHECK (status IN ('active','returned','overdue')),
    notes                TEXT,
    created_at           TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE TABLE IF NOT EXISTS user_owned_items (
    id            BIGSERIAL PRIMARY KEY,
    qr_code_id    TEXT UNIQUE,
    user_id       INT  NOT NULL,
    item_name     TEXT NOT NULL,
    category      TEXT NOT NULL,
    description   TEXT,
    year_owned    INT,
    campus_id     INT  NOT NULL DEFAULT 1,
    quantity      INT  NOT NULL DEFAULT 1,
    condition     TEXT,
    notes         TEXT,
    purchase_date DATE,
    group_id      TEXT,
    created_at    TIMESTAMPTZ NOT NULL DEFAULT NOW()
);
-- Run in Supabase SQL Editor if table already exists:
-- ALTER TABLE user_owned_items ADD COLUMN IF NOT EXISTS qr_code_id TEXT UNIQUE;

CREATE TABLE IF NOT EXISTS campuses (
    id          BIGSERIAL PRIMARY KEY,
    name        TEXT NOT NULL,
    location    TEXT,
    description TEXT,
    is_default  BOOLEAN NOT NULL DEFAULT FALSE,
    created_at  TIMESTAMPTZ DEFAULT NOW()
);

CREATE TABLE IF NOT EXISTS departments (
    id           BIGSERIAL PRIMARY KEY,
    type         TEXT NOT NULL CHECK (type IN ('college','office')),
    abbreviation TEXT NOT NULL,
    full_name    TEXT NOT NULL,
    is_default   BOOLEAN NOT NULL DEFAULT FALSE,
    created_at   TIMESTAMPTZ DEFAULT NOW()
);

-- ─────────────────────────────────────────────
-- 2. DISABLE ROW LEVEL SECURITY
--    (auth is handled by PHP session; anon key needs full access)
-- ─────────────────────────────────────────────

ALTER TABLE users          DISABLE ROW LEVEL SECURITY;
ALTER TABLE inventory      DISABLE ROW LEVEL SECURITY;
ALTER TABLE requests       DISABLE ROW LEVEL SECURITY;
ALTER TABLE borrow_records DISABLE ROW LEVEL SECURITY;
ALTER TABLE user_owned_items DISABLE ROW LEVEL SECURITY;
ALTER TABLE campuses       DISABLE ROW LEVEL SECURITY;
ALTER TABLE departments    DISABLE ROW LEVEL SECURITY;

-- ─────────────────────────────────────────────
-- 3. SEED DATA
-- ─────────────────────────────────────────────

-- Users (passwords are BCrypt hashes)
INSERT INTO users (id, email, password, full_name, role, campus_id, college_id, phone, is_active, created_at, updated_at) VALUES
(1, 'admin@university.edu',       '$2y$10$nLrah9DuGOziCM/BlWJFheD7ECyeITABU6Lnb5dei5IIrC3nXdPCG', 'John Administrator', 'admin', 1, NULL,  '09171234567', 1, '2026-01-15 08:00:00', '2026-04-11 10:00:00'),
(2, 'user@university.edu',        '$2y$10$ujcshmXy9T9ncnJxOE7oNueB16kTlTiWH9QY0ggUHrXSZUClfXpVa', 'Maria Garcia',       'user',  1, 'CCS', '09171234568', 1, '2026-01-20 09:00:00', '2026-04-11 10:00:00'),
(3, 'custodian1@university.edu',  '$2y$10$6aKE8TKp4PxeX/jg3Y5TE.fITuRur4vsK1MSGBaE8pWUhPQFyi8Ea', 'Carlos Santos',      'user',  2, NULL,  '09171234569', 1, '2026-02-01 08:30:00', '2026-04-11 10:00:00'),
(4, 'custodian2@university.edu',  '$2y$10$6aKE8TKp4PxeX/jg3Y5TE.fITuRur4vsK1MSGBaE8pWUhPQFyi8Ea', 'Anna Rodriguez',     'user',  3, NULL,  '09171234570', 1, '2026-02-05 09:15:00', '2026-04-11 10:00:00');
SELECT setval('users_id_seq', (SELECT MAX(id) FROM users));

-- Inventory
INSERT INTO inventory (id, qr_code_id, item_name, category, description, campus_id, college_id, quantity, status, location, purchase_date, cost, condition, created_at) VALUES
(1,  'QR-A1B2C3D4E5', 'Wooden Chair',           'Furniture',        'Brown wooden chair with back support',                         1, 'COE',  5,  'borrowed',    'Admin Building - Room 101',  '2024-01-15', 1500.00,  'excellent', '2026-01-20 10:00:00'),
(2,  'QR-F6G7H8I9J0', 'Office Desk',            'Furniture',        'Large wooden office desk',                                     1, 'CAS',  3,  'borrowed',    'Admin Building - Room 102',  '2024-02-10', 5000.00,  'excellent', '2026-01-25 10:00:00'),
(3,  'QR-K1L2M3N4O5', 'Laptop Computer',        'Electronics',      'Dell Inspiron 15 Laptop',                                      2, NULL,   1,  'borrowed',    'Computer Lab 201',           '2024-03-05', 35000.00, 'good',      '2026-02-01 10:00:00'),
(4,  'QR-P6Q7R8S9T0', 'Projector',              'Electronics',      '4K Multimedia Projector',                                      2, NULL,   1,  'available',   'Auditorium',                 '2024-01-20', 25000.00, 'excellent', '2026-02-05 10:00:00'),
(5,  'QR-U1V2W3X4Y5', 'Whiteboard Marker Set',  'Supplies',         '12-pack assorted whiteboard markers',                          3, NULL,   10, 'available',   'Supply Room',                '2024-04-01', 500.00,   'good',      '2026-02-10 10:00:00'),
(6,  'QR-Z1A2B3C4D5', 'Scientific Calculator',  'Equipment',        'Casio Scientific Calculator FX-991EX',                         3, NULL,   10, 'available',   'Science Room 305',           '2024-03-15', 2000.00,  'excellent', '2026-02-15 10:00:00'),
(7,  'QR-E6F7G8H9I0', 'Office Chair',           'Furniture',        'Ergonomic office chair with wheels',                           4, NULL,   4,  'available',   'Faculty Office',             '2024-02-20', 3000.00,  'good',      '2026-02-20 10:00:00'),
(8,  'QR-J1K2L3M4N5', 'Monitor',                'Electronics',      '27-inch LED Monitor',                                          4, NULL,   2,  'available',   'Computer Lab 202',           '2024-01-30', 8000.00,  'excellent', '2026-02-25 10:00:00'),
(9,  'QR-O6P7Q8R9S0', 'Bookshelf',              'Furniture',        '5-tier wooden bookshelf',                                      5, NULL,   1,  'damaged',     'Library',                    '2024-02-01', 4000.00,  'fair',      '2026-03-01 10:00:00'),
(10, 'QR-T1U2V3W4X5', 'Air Conditioning Unit',  'Appliances',       '1.5 HP split-type air conditioner',                            5, NULL,   1,  'maintenance', 'Faculty Room',               '2023-06-10', 28000.00, 'fair',      '2026-03-05 10:00:00'),
(11, 'QR-Y6Z7A8B9C0', 'Ceiling Fan',            'Appliances',       '60-inch white ceiling fan',                                    6, NULL,   3,  'available',   'Classrooms',                 '2024-03-20', 2500.00,  'good',      '2026-03-10 10:00:00'),
(12, 'QR-D1E2F3G4H5', 'Printer',                'Electronics',      'Canon Laser Printer LBP6030',                                  6, NULL,   1,  'available',   'Admin Office',               '2024-02-28', 12000.00, 'good',      '2026-03-15 10:00:00'),
(13, 'QR-I6J7K8L9M0', 'Whiteboard',             'Equipment',        'Magnetic whiteboard 8x4 feet',                                 7, NULL,   2,  'available',   'Classroom 301',              '2024-05-10', 3500.00,  'excellent', '2026-03-20 10:00:00'),
(14, 'QR-N1O2P3Q4R5', 'Desktop Computer',       'Electronics',      'Intel Core i5 Desktop with 24" monitor',                       7, NULL,   5,  'available',   'Computer Lab',               '2024-04-15', 32000.00, 'excellent', '2026-03-25 10:00:00'),
(15, 'QR-S6T7U8V9W0', 'Steel Filing Cabinet',   'Furniture',        '4-drawer steel filing cabinet with lock',                      8, NULL,   2,  'available',   'Records Office',             '2024-01-05', 6500.00,  'good',      '2026-03-30 10:00:00'),
(16, 'QR-X1Y2Z3A4B5', 'CCTV Camera Set',        'Security',         '4-camera CCTV system with DVR',                                8, NULL,   1,  'available',   'Security Office',            '2024-06-01', 15000.00, 'excellent', '2026-04-01 10:00:00'),
(17, 'QR-C1D2E3F4G5', 'CAD Workstation',        'Electronics',      'High-performance CAD workstation PC',                          1, 'CEA',  2,  'borrowed',    'Engineering Lab',            '2024-07-10', 45000.00, 'excellent', '2026-04-05 10:00:00'),
(18, 'QR-H6I7J8K9L0', 'Accounting Calculator',  'Equipment',        'Casio HR-200RC Printing Calculator set of 10',                 1, 'CBS',  10, 'borrowed',    'Business Lab',               '2024-06-15', 3500.00,  'excellent', '2026-04-06 10:00:00'),
(19, 'QR-M1N2O3P4Q5', 'Network Server',         'Electronics',      'Dell PowerEdge T40 Tower Server',                              1, 'CCS',  1,  'borrowed',    'Server Room 101',            '2024-08-01', 85000.00, 'excellent', '2026-04-07 10:00:00'),
(20, 'QR-R6S7T8U9V0', 'Industrial Drill Press', 'Equipment',        'JET JDP-17MF Floor Drill Press',                               1, 'CIT',  1,  'borrowed',    'Industrial Workshop',        '2024-05-20', 22000.00, 'good',      '2026-04-08 10:00:00'),
(21, 'QR-W1X2Y3Z4A5', 'Food Service Cart',      'Equipment',        'Stainless steel hotel service trolley',                        1, 'CHTM', 3,  'borrowed',    'Hospitality Training Room',  '2024-04-12', 8500.00,  'good',      '2026-04-09 10:00:00'),
(22, 'QR-B6C7D8E9F0', 'Reference Book Set',     'Supplies',         'Social Sciences reference library collection',                 1, 'CSSP', 50, 'borrowed',    'CSSP Library',               '2024-03-10', 12000.00, 'good',      '2026-04-10 10:00:00'),
(23, 'QR-G1H2I3J4K5', 'Smart TV 55 inch',       'Electronics',      '4K Smart Television with stand',                               1, NULL,   1,  'requested',   'Conference Room 103',        '2024-06-10', 25000.00, 'excellent', '2026-04-11 10:00:00'),
(24, 'QR-L1M2N3O4P5', 'Bookcase Cabinet',       'Furniture',        '5-shelf wooden bookcase',                                      2, NULL,   0,  'requested',   'Library 304',                '2024-04-10', 2800.00,  'good',      '2026-04-11 10:15:00'),
(25, 'QR-Q1R2S3T4U5', 'Document Scanner',       'Office Equipment', 'High-speed document scanner',                                  3, NULL,   1,  'requested',   'Admin Office 402',           '2024-05-20', 8500.00,  'excellent', '2026-04-11 10:30:00'),
(26, 'QR-V2W3X4Y5Z6', 'Electric Water Heater',  'Appliances',       '20L storage-type water heater, heating element burnt out',     1, NULL,   1,  'damaged',     'Faculty Lounge 105',         '2022-08-12', 6500.00,  'poor',      '2026-05-10 09:00:00'),
(27, 'QR-A7B8C9D0E1', 'Portable Generator',     'Equipment',        '3.5 kVA portable gasoline generator, engine seized',           2, NULL,   1,  'damaged',     'Utility Room 001',           '2022-11-05', 18000.00, 'poor',      '2026-05-12 10:30:00'),
(28, 'QR-F2G3H4I5J6', 'Industrial Floor Buffer','Equipment',        '175 RPM floor polisher/buffer, motor damaged',                 1, NULL,   1,  'maintenance', 'Custodial Office',           '2023-03-18', 12000.00, 'fair',      '2026-05-15 08:00:00'),
(29, 'QR-K7L8M9N0O1', 'Overhead Projector',     'Electronics',      'Old-model overhead projector, lamp cracked and flickering',    3, NULL,   1,  'damaged',     'Lecture Hall 202',           '2022-06-20', 9500.00,  'poor',      '2026-05-18 11:00:00'),
(30, 'QR-P2Q3R4S5T6', 'UPS Battery Backup',     'Electronics',      '1200VA UPS unit, battery no longer holds charge',              1, NULL,   2,  'maintenance', 'Server Room 101',            '2023-01-30', 7800.00,  'fair',      '2026-05-20 13:00:00');
SELECT setval('inventory_id_seq', (SELECT MAX(id) FROM inventory));

-- Requests
INSERT INTO requests (id, request_number, user_id, inventory_id, request_type, urgency, receiving_method, reason_for_request, service_description, expected_return_date, quantity_requested, status, delivery_status, approved_by, approved_at, created_at, updated_at) VALUES
(1,  'REQ-00001', 2, 5,    'borrow',  'medium',   'delivery', 'Need whiteboard markers for classroom session',                           NULL,                                                                                     '2026-04-20', 3,  'pending',     NULL,              NULL, NULL,                     '2026-04-08 14:30:00', '2026-04-08 14:30:00'),
(2,  'REQ-00002', 2, 6,    'borrow',  'low',      'delivery', 'Calculators needed for upcoming exam',                                    NULL,                                                                                     '2026-04-18', 5,  'approved',    'out_for_delivery', 1,    '2026-04-09 09:00:00', '2026-04-07 10:15:00', '2026-04-09 09:00:00'),
(3,  'REQ-00003', 3, 3,    'borrow',  'high',     'pickup',   'Laptop required for off-campus research project',                         NULL,                                                                                     '2026-04-15', 1,  'disapproved', NULL,               1,    '2026-04-06 16:00:00', '2026-04-06 11:45:00', '2026-04-06 16:00:00'),
(4,  'REQ-00004', 3, 4,    'service', 'critical', NULL,       NULL,                                                                      'Projector bulb is flickering and needs replacement',                                     NULL,         1,  'pending',     NULL,              NULL, NULL,                     '2026-04-10 08:00:00', '2026-04-10 08:00:00'),
(5,  'REQ-00005', 4, NULL, 'item',    'medium',   'delivery', 'Classroom needs additional seating for new students',                     'Plastic Armchairs - Qty: 10',                                                            NULL,         10, 'approved',    'delivered',        1,    '2026-04-11 10:00:00', '2026-04-09 13:00:00', '2026-04-11 10:00:00'),
(6,  'REQ-00006', 2, 3,    'borrow',  'high',     'delivery', 'Need laptop for online seminar presentation this Friday',                 NULL,                                                                                     '2026-04-18', 1,  'pending',     NULL,              NULL, NULL,                     '2026-04-14 08:20:00', '2026-04-14 08:20:00'),
(7,  'REQ-00007', 3, 4,    'borrow',  'medium',   'pickup',   'Projector needed for department orientation event',                       NULL,                                                                                     '2026-04-17', 1,  'pending',     NULL,              NULL, NULL,                     '2026-04-13 11:05:00', '2026-04-13 11:05:00'),
(8,  'REQ-00008', 4, NULL, 'item',    'low',      'delivery', 'Replace broken folding tables in the conference room',                    'Folding Tables - Qty: 4',                                                                NULL,         4,  'pending',     NULL,              NULL, NULL,                     '2026-04-13 13:45:00', '2026-04-13 13:45:00'),
(9,  'REQ-00009', 2, NULL, 'service', 'high',     NULL,       NULL,                                                                      'Air conditioning unit in Room 204 is not cooling properly. Needs cleaning and refrigerant refill.', NULL, 1, 'pending', NULL,             NULL, NULL,                     '2026-04-14 09:30:00', '2026-04-14 09:30:00'),
(10, 'REQ-00010', 3, NULL, 'service', 'medium',   NULL,       NULL,                                                                      'Several electrical outlets in the faculty lounge are loose and need to be replaced to prevent hazards.', NULL, 1, 'pending', NULL,          NULL, NULL,                     '2026-04-14 10:15:00', '2026-04-14 10:15:00'),
(11, 'REQ-00011', 4, 6,    'borrow',  'low',      'pickup',   'Students need calculators for engineering board exam review',              NULL,                                                                                     '2026-04-25', 8,  'pending',     NULL,              NULL, NULL,                     '2026-04-14 14:00:00', '2026-04-14 14:00:00'),
(12, 'REQ-00012', 2, NULL, 'item',    'critical', 'delivery', 'Emergency: whiteboard in main lecture hall is cracked and unusable',      'Portable Whiteboard - Qty: 1',                                                           NULL,         1,  'pending',     NULL,              NULL, NULL,                     '2026-04-15 07:55:00', '2026-04-15 07:55:00');
SELECT setval('requests_id_seq', (SELECT MAX(id) FROM requests));

-- Borrow records
INSERT INTO borrow_records (id, user_id, inventory_id, request_id, borrow_date, expected_return_date, actual_return_date, status, notes, created_at) VALUES
(1,  2, 3,  NULL, '2026-03-25', '2026-04-10', NULL,         'overdue',  'For research project use',                    '2026-03-25 10:00:00'),
(2,  2, 6,  NULL, '2026-04-09', '2026-04-18', NULL,         'active',   'Borrowed for exam week',                      '2026-04-09 10:00:00'),
(3,  3, 4,  NULL, '2026-03-15', '2026-03-22', '2026-03-21', 'returned', 'Used for department meeting',                 '2026-03-15 09:00:00'),
(4,  4, 8,  NULL, '2026-04-01', '2026-04-08', '2026-04-07', 'returned', NULL,                                          '2026-04-01 11:00:00'),
(5,  2, 1,  NULL, '2026-06-18', '2026-06-24', NULL,         'active',   'Borrowed for faculty meeting setup',          '2026-06-18 09:00:00'),
(6,  2, 2,  NULL, '2026-06-19', '2026-06-24', NULL,         'active',   'Department reorganization',                   '2026-06-19 10:00:00'),
(7,  2, 17, NULL, '2026-06-20', '2026-06-26', NULL,         'active',   'CEA design project deadline',                 '2026-06-20 08:30:00'),
(8,  3, 18, NULL, '2026-06-21', '2026-06-28', NULL,         'active',   'Accounting exam preparation',                 '2026-06-21 11:00:00'),
(9,  2, 22, NULL, '2026-06-16', '2026-06-28', NULL,         'active',   'Research reference materials for thesis',     '2026-06-16 14:00:00'),
(10, 2, 21, NULL, '2026-06-22', '2026-06-30', NULL,         'active',   'CHTM culinary event service',                 '2026-06-22 07:00:00'),
(11, 4, 19, NULL, '2026-06-18', '2026-07-02', NULL,         'active',   'Server maintenance and system testing',       '2026-06-18 09:00:00'),
(12, 3, 20, NULL, '2026-06-20', '2026-07-07', NULL,         'active',   'Engineering workshop project',                '2026-06-20 10:00:00'),
(13, 3, 1,  NULL, '2026-06-20', '2026-06-27', NULL,         'active',   'Extra chairs for department seminar',         '2026-06-20 09:30:00'),
(14, 4, 1,  NULL, '2026-06-21', '2026-07-03', NULL,         'active',   'Conference room setup for board meeting',     '2026-06-21 08:00:00'),
(15, 4, 18, NULL, '2026-06-22', '2026-07-01', NULL,         'active',   'Accounting finals week — extra units',        '2026-06-22 10:00:00'),
(16, 3, 18, NULL, '2026-06-22', '2026-07-05', NULL,         'active',   'CPA review class practice set',               '2026-06-22 11:00:00'),
(17, 2, 21, NULL, '2026-06-22', '2026-07-04', NULL,         'active',   'Second cart for catering event overflow',     '2026-06-22 07:30:00'),
(18, 4, 17, NULL, '2026-06-22', '2026-07-10', NULL,         'active',   'CEA capstone project — second workstation',   '2026-06-22 09:00:00');
SELECT setval('borrow_records_id_seq', (SELECT MAX(id) FROM borrow_records));

-- User owned items
INSERT INTO user_owned_items (id, user_id, item_name, category, description, year_owned, campus_id, quantity, condition, notes, purchase_date, created_at) VALUES
(1, 2, 'Desktop Computer', 'Electronics', 'Dell Desktop PC with 24" monitor',    2023, 1, 1, 'excellent', 'Returned in excellent condition', '2023-05-10', '2024-06-15 10:00:00'),
(2, 2, 'Office Chair',     'Furniture',   'Ergonomic swivel office chair',        2023, 1, 3, 'good',      'Minor wear on armrests',          '2023-03-20', '2024-07-01 14:30:00'),
(3, 3, 'Projector',        'Electronics', '4K Multimedia Projector',              2024, 2, 1, 'excellent', 'Used for semester presentations', '2024-01-15', '2024-08-10 09:15:00'),
(4, 4, 'Whiteboard Set',   'Equipment',   'Portable whiteboard with markers',     2022, 3, 2, 'fair',      'Surface has some stains but functional', '2022-09-12', '2024-05-22 11:45:00'),
(5, 2, 'Printer',          'Electronics', 'Canon Laser Printer',                  2024, 1, 1, 'excellent', 'Department use',                  '2024-02-28', '2024-09-05 16:20:00');
SELECT setval('user_owned_items_id_seq', (SELECT MAX(id) FROM user_owned_items));

-- Campuses
INSERT INTO campuses (id, name, location, description, is_default) VALUES
(1, 'Main Campus',                 'Brgy. Cabambangan, Bacolor, Pampanga', 'Central campus of Pampanga State University hosting 8 colleges and the university administration.', true),
(2, 'Mexico Campus',               'Mexico, Pampanga',                     'PSU extension campus serving the Mexico municipality and surrounding areas.',                          true),
(3, 'Porac Campus',                'Porac, Pampanga',                      'PSU extension campus serving the Porac municipality and surrounding areas.',                           true),
(4, 'Santo Tomas Campus',          'Santo Tomas, Pampanga',                'PSU satellite campus providing quality education in the Santo Tomas area.',                           true),
(5, 'Lubao Campus',                'Sta. Catalina, Lubao, Pampanga',       'PSU extension campus offering specialized courses in the Lubao area.',                               true),
(6, 'Candaba Campus',              'Candaba, Pampanga',                    'PSU extension campus serving the educational needs of the Candaba community.',                        true),
(7, 'Apalit Campus',               'Apalit, Pampanga',                     'PSU dedicated campus serving the Apalit municipality.',                                               true),
(8, 'City of San Fernando Campus', 'City of San Fernando, Pampanga',       'PSU satellite campus in the provincial capital, City of San Fernando.',                              true);
SELECT setval('campuses_id_seq', (SELECT MAX(id) FROM campuses));

-- Departments (colleges and offices)
INSERT INTO departments (type, abbreviation, full_name, is_default) VALUES
('college', 'CEA',    'College of Engineering and Architecture (CEA)',                true),
('college', 'COE',    'College of Education (COE)',                                   true),
('college', 'CCS',    'College of Computing Studies (CCS)',                           true),
('college', 'CBS',    'College of Business Studies (CBS)',                            true),
('college', 'CAS',    'College of Arts and Sciences (CAS)',                           true),
('college', 'CIT',    'College of Industrial Technology (CIT)',                       true),
('college', 'CHTM',   'College of Hospitality and Tourism Management (CHTM)',         true),
('college', 'CSSP',   'College of Social Sciences and Philosophy (CSSP)',             true),
('office',  'OUP',    'Office of the University President (OUP)',                     true),
('office',  'OVPAA',  'Office of the VP for Academic Affairs (OVPAA)',                true),
('office',  'OVPAF',  'Office of the VP for Administration & Finance (OVPAF)',        true),
('office',  'OVPRDE', 'Office of the VP for Research, Development & Extension (OVPRDE)', true),
('office',  'OUR',    'Office of the University Registrar (OUR)',                     true),
('office',  'OSAS',   'Office of Student Affairs and Services (OSAS)',                true),
('office',  'HRMO',   'Human Resource Management Office (HRMO)',                      true),
('office',  'ICTO',   'Information and Communications Technology Office (ICTO)',      true),
('office',  'FBO',    'Finance and Budget Office (FBO)',                              true),
('office',  'PMO',    'Procurement Management Office (PMO)',                          true),
('office',  'PPMO',   'Physical Plant and Maintenance Office (PPMO)',                 true),
('office',  'ULib',   'University Library (ULib)',                                    true),
('office',  'GCC',    'Guidance and Counseling Center (GCC)',                         true),
('office',  'PDO',    'Planning and Development Office (PDO)',                        true);
