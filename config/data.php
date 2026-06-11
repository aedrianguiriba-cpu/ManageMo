<?php
/**
 * Hardcoded Data Provider
 * All application data is stored directly as PHP arrays
 * No database connection required
 */

// ===== DEPARTMENTS CUSTOM JSON HELPER =====
function _loadCustomDepartments() {
    $file = dirname(__FILE__) . '/departments_custom.json';
    if (!file_exists($file)) return ['colleges' => [], 'offices' => []];
    $data = json_decode(file_get_contents($file), true);
    return $data ?: ['colleges' => [], 'offices' => []];
}

function saveCustomDepartments($data) {
    $file = dirname(__FILE__) . '/departments_custom.json';
    file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT));
}

// ===== MAIN CAMPUS COLLEGES =====
function getMainCampusColleges() {
    $defaults = [
        'CEA'  => 'College of Engineering and Architecture (CEA)',
        'COE'  => 'College of Education (COE)',
        'CCS'  => 'College of Computing Studies (CCS)',
        'CBS'  => 'College of Business Studies (CBS)',
        'CAS'  => 'College of Arts and Sciences (CAS)',
        'CIT'  => 'College of Industrial Technology (CIT)',
        'CHTM' => 'College of Hospitality and Tourism Management (CHTM)',
        'CSSP' => 'College of Social Sciences and Philosophy (CSSP)',
    ];
    $custom = _loadCustomDepartments();
    return array_merge($defaults, $custom['colleges'] ?? []);
}

// ===== MAIN CAMPUS OFFICES =====
function getMainCampusOffices() {
    $defaults = [
        'OUP'   => 'Office of the University President (OUP)',
        'OVPAA' => 'Office of the VP for Academic Affairs (OVPAA)',
        'OVPAF' => 'Office of the VP for Administration & Finance (OVPAF)',
        'OVPRDE'=> 'Office of the VP for Research, Development & Extension (OVPRDE)',
        'OUR'   => 'Office of the University Registrar (OUR)',
        'OSAS'  => 'Office of Student Affairs and Services (OSAS)',
        'HRMO'  => 'Human Resource Management Office (HRMO)',
        'ICTO'  => 'Information and Communications Technology Office (ICTO)',
        'FBO'   => 'Finance and Budget Office (FBO)',
        'PMO'   => 'Procurement Management Office (PMO)',
        'PPMO'  => 'Physical Plant and Maintenance Office (PPMO)',
        'ULib'  => 'University Library (ULib)',
        'GCC'   => 'Guidance and Counseling Center (GCC)',
        'PDO'   => 'Planning and Development Office (PDO)',
    ];
    $custom = _loadCustomDepartments();
    return array_merge($defaults, $custom['offices'] ?? []);
}

// ===== COMBINED MAIN CAMPUS DEPARTMENTS =====
// Returns colleges and offices merged into one array (for lookup/filtering)
function getMainCampusDepartments() {
    return array_merge(getMainCampusColleges(), getMainCampusOffices());
}

// ===== USERS DATA =====
function getUsers() {
    return [
        // college_id is a short code (e.g. 'CCS') — only relevant for campus_id = 1 (Main Campus)
        ['id' => 1, 'email' => 'admin@university.edu', 'password' => '$2y$10$nLrah9DuGOziCM/BlWJFheD7ECyeITABU6Lnb5dei5IIrC3nXdPCG', 'full_name' => 'John Administrator', 'role' => 'admin', 'campus_id' => 1, 'college_id' => NULL, 'phone' => '09171234567', 'is_active' => 1, 'created_at' => '2026-01-15 08:00:00', 'updated_at' => '2026-04-11 10:00:00'],
        ['id' => 2, 'email' => 'user@university.edu', 'password' => '$2y$10$ujcshmXy9T9ncnJxOE7oNueB16kTlTiWH9QY0ggUHrXSZUClfXpVa', 'full_name' => 'Maria Garcia', 'role' => 'user', 'campus_id' => 1, 'college_id' => 'CCS', 'phone' => '09171234568', 'is_active' => 1, 'created_at' => '2026-01-20 09:00:00', 'updated_at' => '2026-04-11 10:00:00'],
        ['id' => 3, 'email' => 'custodian1@university.edu', 'password' => '$2y$10$6aKE8TKp4PxeX/jg3Y5TE.fITuRur4vsK1MSGBaE8pWUhPQFyi8Ea', 'full_name' => 'Carlos Santos', 'role' => 'user', 'campus_id' => 2, 'college_id' => NULL, 'phone' => '09171234569', 'is_active' => 1, 'created_at' => '2026-02-01 08:30:00', 'updated_at' => '2026-04-11 10:00:00'],
        ['id' => 4, 'email' => 'custodian2@university.edu', 'password' => '$2y$10$6aKE8TKp4PxeX/jg3Y5TE.fITuRur4vsK1MSGBaE8pWUhPQFyi8Ea', 'full_name' => 'Anna Rodriguez', 'role' => 'user', 'campus_id' => 3, 'college_id' => NULL, 'phone' => '09171234570', 'is_active' => 1, 'created_at' => '2026-02-05 09:15:00', 'updated_at' => '2026-04-11 10:00:00'],
    ];
}

// ===== CAMPUSES DATA =====
function getCampuses() {
    $defaults = [
        [
            'id' => 1,
            'name' => 'Main Campus',
            'location' => 'Brgy. Cabambangan, Bacolor, Pampanga',
            'description' => 'Central campus of Pampanga State University hosting 8 colleges and the university administration.',
            'colleges' => [
                'College of Engineering and Architecture (CEA)',
                'College of Education (COE)',
                'College of Computing Studies (CCS)',
                'College of Business Studies (CBS)',
                'College of Arts and Sciences (CAS)',
                'College of Industrial Technology (CIT)',
                'College of Hospitality and Tourism Management (CHTM)',
                'College of Social Sciences and Philosophy (CSSP)',
            ],
        ],
        [
            'id' => 2,
            'name' => 'Mexico Campus',
            'location' => 'Mexico, Pampanga',
            'description' => 'PSU extension campus serving the Mexico municipality and surrounding areas.',
            'colleges' => [],
        ],
        [
            'id' => 3,
            'name' => 'Porac Campus',
            'location' => 'Porac, Pampanga',
            'description' => 'PSU extension campus serving the Porac municipality and surrounding areas.',
            'colleges' => [],
        ],
        [
            'id' => 4,
            'name' => 'Santo Tomas Campus',
            'location' => 'Santo Tomas, Pampanga',
            'description' => 'PSU satellite campus providing quality education in the Santo Tomas area.',
            'colleges' => [],
        ],
        [
            'id' => 5,
            'name' => 'Lubao Campus',
            'location' => 'Sta. Catalina, Lubao, Pampanga',
            'description' => 'PSU extension campus offering specialized courses in the Lubao area.',
            'colleges' => [],
        ],
        [
            'id' => 6,
            'name' => 'Candaba Campus',
            'location' => 'Candaba, Pampanga',
            'description' => 'PSU extension campus serving the educational needs of the Candaba community.',
            'colleges' => [],
        ],
        [
            'id' => 7,
            'name' => 'Apalit Campus',
            'location' => 'Apalit, Pampanga',
            'description' => 'PSU dedicated campus serving the Apalit municipality.',
            'colleges' => [],
        ],
        [
            'id' => 8,
            'name' => 'City of San Fernando Campus',
            'location' => 'City of San Fernando, Pampanga',
            'description' => 'PSU satellite campus in the provincial capital, City of San Fernando.',
            'colleges' => [],
        ],
    ];

    $custom = _loadCustomDepartments();
    foreach ($custom['campuses'] ?? [] as $c) {
        $defaults[] = $c;
    }
    return $defaults;
}

// ===== BORROW CATALOG DATA =====
// Predefined list of items users can request to borrow
function getBorrowCatalog() {
    return [
        // Electronics
        ['id' => 1,  'name' => 'Laptop Computer',         'category' => 'Electronics',       'description' => 'Portable laptop for academic or office use'],
        ['id' => 2,  'name' => 'Desktop Computer',         'category' => 'Electronics',       'description' => 'Desktop PC unit'],
        ['id' => 3,  'name' => 'Projector',                'category' => 'Electronics',       'description' => 'Multimedia projector for presentations'],
        ['id' => 4,  'name' => 'Monitor',                  'category' => 'Electronics',       'description' => 'External display monitor'],
        ['id' => 5,  'name' => 'Printer',                  'category' => 'Electronics',       'description' => 'Laser or inkjet printer'],
        ['id' => 6,  'name' => 'Extension Cord',           'category' => 'Electronics',       'description' => 'Multi-outlet power extension'],
        // Furniture
        ['id' => 7,  'name' => 'Monobloc Chair',           'category' => 'Furniture',         'description' => 'Plastic stackable chair'],
        ['id' => 8,  'name' => 'Folding Table',            'category' => 'Furniture',         'description' => 'Portable folding table'],
        ['id' => 9,  'name' => 'Office Chair',             'category' => 'Furniture',         'description' => 'Ergonomic swivel office chair'],
        ['id' => 10, 'name' => 'Podium/Lectern',           'category' => 'Furniture',         'description' => 'Standing podium for presentations'],
        // Equipment
        ['id' => 11, 'name' => 'Scientific Calculator',    'category' => 'Equipment',         'description' => 'Advanced scientific calculator'],
        ['id' => 12, 'name' => 'Whiteboard',               'category' => 'Equipment',         'description' => 'Portable whiteboard'],
        ['id' => 13, 'name' => 'Megaphone / Bullhorn',     'category' => 'Equipment',         'description' => 'Battery-powered megaphone for announcements'],
        ['id' => 14, 'name' => 'Microphone & Speaker Set', 'category' => 'Equipment',         'description' => 'Wireless microphone with portable speaker'],
        ['id' => 15, 'name' => 'HDMI / VGA Cable',         'category' => 'Equipment',         'description' => 'Display cable for projector or monitor connection'],
        // Supplies (durable/returnable only — consumables like markers and paper are excluded)
        ['id' => 17, 'name' => 'Tarpaulin Stand',          'category' => 'Supplies',          'description' => 'Adjustable stand for tarpaulin display'],
        // Appliances
        ['id' => 18, 'name' => 'Electric Fan',             'category' => 'Appliances',        'description' => 'Portable electric stand fan'],
        ['id' => 19, 'name' => 'Water Dispenser',          'category' => 'Appliances',        'description' => 'Hot and cold water dispenser'],
        // Others
        ['id' => 20, 'name' => 'First Aid Kit',            'category' => 'Safety',            'description' => 'Standard first aid kit for events'],
    ];
}

// ===== INVENTORY DATA =====
function getInventory() {
    return [
        ['id' => 1, 'qr_code_id' => 'QR-A1B2C3D4E5', 'item_name' => 'Wooden Chair',           'category' => 'Furniture',   'description' => 'Brown wooden chair with back support',         'campus_id' => 1, 'college_id' => 'COE', 'quantity' => 5,  'status' => 'available',    'location' => 'Admin Building - Room 101',  'purchase_date' => '2024-01-15', 'cost' => 1500.00,  'condition' => 'excellent', 'created_at' => '2026-01-20 10:00:00'],
        ['id' => 2, 'qr_code_id' => 'QR-F6G7H8I9J0', 'item_name' => 'Office Desk',            'category' => 'Furniture',   'description' => 'Large wooden office desk',                     'campus_id' => 1, 'college_id' => 'CAS', 'quantity' => 3,  'status' => 'available',    'location' => 'Admin Building - Room 102',  'purchase_date' => '2024-02-10', 'cost' => 5000.00,  'condition' => 'excellent', 'created_at' => '2026-01-25 10:00:00'],
        ['id' => 3, 'qr_code_id' => 'QR-K1L2M3N4O5', 'item_name' => 'Laptop Computer',        'category' => 'Electronics', 'description' => 'Dell Inspiron 15 Laptop',                       'campus_id' => 2, 'quantity' => 1,  'status' => 'borrowed',     'location' => 'Computer Lab 201',           'purchase_date' => '2024-03-05', 'cost' => 35000.00, 'condition' => 'good',      'created_at' => '2026-02-01 10:00:00'],
        ['id' => 4, 'qr_code_id' => 'QR-P6Q7R8S9T0', 'item_name' => 'Projector',              'category' => 'Electronics', 'description' => '4K Multimedia Projector',                       'campus_id' => 2, 'quantity' => 1,  'status' => 'available',    'location' => 'Auditorium',                 'purchase_date' => '2024-01-20', 'cost' => 25000.00, 'condition' => 'excellent', 'created_at' => '2026-02-05 10:00:00'],
        ['id' => 5, 'qr_code_id' => 'QR-U1V2W3X4Y5', 'item_name' => 'Whiteboard Marker Set',  'category' => 'Supplies',    'description' => '12-pack assorted whiteboard markers',           'campus_id' => 3, 'quantity' => 10, 'status' => 'available',    'location' => 'Supply Room',                'purchase_date' => '2024-04-01', 'cost' => 500.00,   'condition' => 'good',      'created_at' => '2026-02-10 10:00:00'],
        ['id' => 6, 'qr_code_id' => 'QR-Z1A2B3C4D5', 'item_name' => 'Scientific Calculator',  'category' => 'Equipment',   'description' => 'Casio Scientific Calculator FX-991EX',          'campus_id' => 3, 'quantity' => 10, 'status' => 'available',    'location' => 'Science Room 305',           'purchase_date' => '2024-03-15', 'cost' => 2000.00,  'condition' => 'excellent', 'created_at' => '2026-02-15 10:00:00'],
        ['id' => 7, 'qr_code_id' => 'QR-E6F7G8H9I0', 'item_name' => 'Office Chair',           'category' => 'Furniture',   'description' => 'Ergonomic office chair with wheels',            'campus_id' => 4, 'quantity' => 4,  'status' => 'available',    'location' => 'Faculty Office',             'purchase_date' => '2024-02-20', 'cost' => 3000.00,  'condition' => 'good',      'created_at' => '2026-02-20 10:00:00'],
        ['id' => 8, 'qr_code_id' => 'QR-J1K2L3M4N5', 'item_name' => 'Monitor',                'category' => 'Electronics', 'description' => '27-inch LED Monitor',                           'campus_id' => 4, 'quantity' => 2,  'status' => 'available',    'location' => 'Computer Lab 202',           'purchase_date' => '2024-01-30', 'cost' => 8000.00,  'condition' => 'excellent', 'created_at' => '2026-02-25 10:00:00'],
        ['id' => 9, 'qr_code_id' => 'QR-O6P7Q8R9S0', 'item_name' => 'Bookshelf',              'category' => 'Furniture',   'description' => '5-tier wooden bookshelf',                       'campus_id' => 5, 'quantity' => 1,  'status' => 'damaged',      'location' => 'Library',                    'purchase_date' => '2024-02-01', 'cost' => 4000.00,  'condition' => 'fair',      'created_at' => '2026-03-01 10:00:00'],
        ['id' => 10,'qr_code_id' => 'QR-T1U2V3W4X5', 'item_name' => 'Air Conditioning Unit', 'category' => 'Appliances',  'description' => '1.5 HP split-type air conditioner',             'campus_id' => 5, 'quantity' => 1,  'status' => 'maintenance',  'location' => 'Faculty Room',               'purchase_date' => '2023-06-10', 'cost' => 28000.00, 'condition' => 'fair',      'created_at' => '2026-03-05 10:00:00'],
        ['id' => 11,'qr_code_id' => 'QR-Y6Z7A8B9C0', 'item_name' => 'Ceiling Fan',            'category' => 'Appliances',  'description' => '60-inch white ceiling fan',                     'campus_id' => 6, 'quantity' => 3,  'status' => 'available',    'location' => 'Classrooms',                 'purchase_date' => '2024-03-20', 'cost' => 2500.00,  'condition' => 'good',      'created_at' => '2026-03-10 10:00:00'],
        ['id' => 12,'qr_code_id' => 'QR-D1E2F3G4H5', 'item_name' => 'Printer',               'category' => 'Electronics', 'description' => 'Canon Laser Printer LBP6030',                   'campus_id' => 6, 'quantity' => 1,  'status' => 'available',    'location' => 'Admin Office',               'purchase_date' => '2024-02-28', 'cost' => 12000.00, 'condition' => 'good',      'created_at' => '2026-03-15 10:00:00'],
        ['id' => 13,'qr_code_id' => 'QR-I6J7K8L9M0', 'item_name' => 'Whiteboard',             'category' => 'Equipment',   'description' => 'Magnetic whiteboard 8x4 feet',                  'campus_id' => 7, 'quantity' => 2,  'status' => 'available',    'location' => 'Classroom 301',              'purchase_date' => '2024-05-10', 'cost' => 3500.00,  'condition' => 'excellent', 'created_at' => '2026-03-20 10:00:00'],
        ['id' => 14,'qr_code_id' => 'QR-N1O2P3Q4R5', 'item_name' => 'Desktop Computer',       'category' => 'Electronics', 'description' => 'Intel Core i5 Desktop with 24" monitor',        'campus_id' => 7, 'quantity' => 5,  'status' => 'available',    'location' => 'Computer Lab',               'purchase_date' => '2024-04-15', 'cost' => 32000.00, 'condition' => 'excellent', 'created_at' => '2026-03-25 10:00:00'],
        ['id' => 15,'qr_code_id' => 'QR-S6T7U8V9W0', 'item_name' => 'Steel Filing Cabinet',  'category' => 'Furniture',   'description' => '4-drawer steel filing cabinet with lock',        'campus_id' => 8, 'quantity' => 2,  'status' => 'available',    'location' => 'Records Office',             'purchase_date' => '2024-01-05', 'cost' => 6500.00,  'condition' => 'good',      'created_at' => '2026-03-30 10:00:00'],
        ['id' => 16,'qr_code_id' => 'QR-X1Y2Z3A4B5', 'item_name' => 'CCTV Camera Set',        'category' => 'Security',    'description' => '4-camera CCTV system with DVR',                 'campus_id' => 8, 'quantity' => 1,  'status' => 'available',    'location' => 'Security Office',            'purchase_date' => '2024-06-01', 'cost' => 15000.00, 'condition' => 'excellent', 'created_at' => '2026-04-01 10:00:00'],
        ['id' => 17,'qr_code_id' => 'QR-C1D2E3F4G5', 'item_name' => 'CAD Workstation',        'category' => 'Electronics', 'description' => 'High-performance CAD workstation PC',           'campus_id' => 1, 'college_id' => 'CEA',  'quantity' => 2,  'status' => 'available',    'location' => 'Engineering Lab',            'purchase_date' => '2024-07-10', 'cost' => 45000.00, 'condition' => 'excellent', 'created_at' => '2026-04-05 10:00:00'],
        ['id' => 18,'qr_code_id' => 'QR-H6I7J8K9L0', 'item_name' => 'Accounting Calculator', 'category' => 'Equipment',   'description' => 'Casio HR-200RC Printing Calculator set of 10',  'campus_id' => 1, 'college_id' => 'CBS',  'quantity' => 10, 'status' => 'available',    'location' => 'Business Lab',               'purchase_date' => '2024-06-15', 'cost' => 3500.00,  'condition' => 'excellent', 'created_at' => '2026-04-06 10:00:00'],
        ['id' => 19,'qr_code_id' => 'QR-M1N2O3P4Q5', 'item_name' => 'Network Server',         'category' => 'Electronics', 'description' => 'Dell PowerEdge T40 Tower Server',               'campus_id' => 1, 'college_id' => 'CCS',  'quantity' => 1,  'status' => 'available',    'location' => 'Server Room 101',            'purchase_date' => '2024-08-01', 'cost' => 85000.00, 'condition' => 'excellent', 'created_at' => '2026-04-07 10:00:00'],
        ['id' => 20,'qr_code_id' => 'QR-R6S7T8U9V0', 'item_name' => 'Industrial Drill Press', 'category' => 'Equipment',   'description' => 'JET JDP-17MF Floor Drill Press',                'campus_id' => 1, 'college_id' => 'CIT',  'quantity' => 1,  'status' => 'available',    'location' => 'Industrial Workshop',        'purchase_date' => '2024-05-20', 'cost' => 22000.00, 'condition' => 'good',      'created_at' => '2026-04-08 10:00:00'],
        ['id' => 21,'qr_code_id' => 'QR-W1X2Y3Z4A5', 'item_name' => 'Food Service Cart',      'category' => 'Equipment',   'description' => 'Stainless steel hotel service trolley',         'campus_id' => 1, 'college_id' => 'CHTM', 'quantity' => 3,  'status' => 'available',    'location' => 'Hospitality Training Room',  'purchase_date' => '2024-04-12', 'cost' => 8500.00,  'condition' => 'good',      'created_at' => '2026-04-09 10:00:00'],
        ['id' => 22,'qr_code_id' => 'QR-B6C7D8E9F0', 'item_name' => 'Reference Book Set',     'category' => 'Supplies',    'description' => 'Social Sciences reference library collection',  'campus_id' => 1, 'college_id' => 'CSSP', 'quantity' => 50, 'status' => 'available',    'location' => 'CSSP Library',               'purchase_date' => '2024-03-10', 'cost' => 12000.00, 'condition' => 'good',      'created_at' => '2026-04-10 10:00:00'],
        ['id' => 23,'qr_code_id' => 'QR-G1H2I3J4K5', 'item_name' => 'Smart TV 55 inch',        'category' => 'Electronics', 'description' => '4K Smart Television with stand',                'campus_id' => 1, 'quantity' => 1,  'status' => 'requested',    'location' => 'Conference Room 103',        'purchase_date' => '2024-06-10', 'cost' => 25000.00, 'condition' => 'excellent', 'created_at' => '2026-04-11 10:00:00'],
        ['id' => 24,'qr_code_id' => 'QR-L1M2N3O4P5', 'item_name' => 'Bookcase Cabinet',        'category' => 'Furniture',   'description' => '5-shelf wooden bookcase',                       'campus_id' => 2, 'quantity' => 0,  'status' => 'requested',    'location' => 'Library 304',                'purchase_date' => '2024-04-10', 'cost' => 2800.00,  'condition' => 'good',      'created_at' => '2026-04-11 10:15:00'],
        ['id' => 25,'qr_code_id' => 'QR-Q1R2S3T4U5', 'item_name' => 'Document Scanner',        'category' => 'Office Equipment', 'description' => 'High-speed document scanner',                'campus_id' => 3, 'quantity' => 1,  'status' => 'requested',    'location' => 'Admin Office 402',           'purchase_date' => '2024-05-20', 'cost' => 8500.00,  'condition' => 'excellent', 'created_at' => '2026-04-11 10:30:00'],
    ];
}

// ===== REQUESTS DATA =====
function getRequests() {
    return [
        [
            'id' => 1,
            'request_number' => 'REQ-00001',
            'user_id' => 2,
            'inventory_id' => 5,
            'request_type' => 'borrow',
            'urgency' => 'medium',
            'receiving_method' => 'delivery',
            'reason_for_request' => 'Need whiteboard markers for classroom session',
            'service_description' => NULL,
            'expected_return_date' => '2026-04-20',
            'quantity_requested' => 3,
            'status' => 'pending',
            'approval_notes' => NULL,
            'approved_by' => NULL,
            'approved_at' => NULL,
            'created_at' => '2026-04-08 14:30:00',
            'updated_at' => '2026-04-08 14:30:00',
        ],
        [
            'id' => 2,
            'request_number' => 'REQ-00002',
            'user_id' => 2,
            'inventory_id' => 6,
            'request_type' => 'borrow',
            'urgency' => 'low',
            'receiving_method' => 'delivery',
            'reason_for_request' => 'Calculators needed for upcoming exam',
            'service_description' => NULL,
            'expected_return_date' => '2026-04-18',
            'quantity_requested' => 5,
            'status' => 'approved',
            'approval_notes' => 'Approved. Please return by the indicated date.',
            'delivery_status' => 'out_for_delivery',
            'approved_by' => 1,
            'approved_at' => '2026-04-09 09:00:00',
            'created_at' => '2026-04-07 10:15:00',
            'updated_at' => '2026-04-09 09:00:00',
        ],
        [
            'id' => 3,
            'request_number' => 'REQ-00003',
            'user_id' => 3,
            'inventory_id' => 3,
            'request_type' => 'borrow',
            'urgency' => 'high',
            'receiving_method' => 'pickup',
            'reason_for_request' => 'Laptop required for off-campus research project',
            'service_description' => NULL,
            'expected_return_date' => '2026-04-15',
            'quantity_requested' => 1,
            'status' => 'disapproved',
            'approval_notes' => 'Item currently unavailable. Please resubmit next week.',
            'approved_by' => 1,
            'approved_at' => '2026-04-06 16:00:00',
            'created_at' => '2026-04-06 11:45:00',
            'updated_at' => '2026-04-06 16:00:00',
        ],
        [
            'id' => 4,
            'request_number' => 'REQ-00004',
            'user_id' => 3,
            'inventory_id' => 4,
            'request_type' => 'service',
            'urgency' => 'critical',
            'reason_for_request' => NULL,
            'service_description' => 'Projector bulb is flickering and needs replacement',
            'expected_return_date' => NULL,
            'quantity_requested' => 1,
            'status' => 'pending',
            'approval_notes' => NULL,
            'approved_by' => NULL,
            'approved_at' => NULL,
            'created_at' => '2026-04-10 08:00:00',
            'updated_at' => '2026-04-10 08:00:00',
        ],
        [
            'id' => 5,
            'request_number' => 'REQ-00005',
            'user_id' => 4,
            'inventory_id' => NULL,
            'request_type' => 'item',
            'urgency' => 'medium',
            'receiving_method' => 'delivery',
            'reason_for_request' => 'Classroom needs additional seating for new students',
            'service_description' => 'Plastic Armchairs - Qty: 10',
            'expected_return_date' => NULL,
            'quantity_requested' => 10,
            'status' => 'approved',
            'approval_notes' => 'Approved. Procurement will process within 2 weeks.',
            'delivery_status' => 'delivered',
            'approved_by' => 1,
            'approved_at' => '2026-04-11 10:00:00',
            'created_at' => '2026-04-09 13:00:00',
            'updated_at' => '2026-04-11 10:00:00',
        ],
        [
            'id' => 6,
            'request_number' => 'REQ-00006',
            'user_id' => 2,
            'inventory_id' => 3,
            'request_type' => 'borrow',
            'urgency' => 'high',
            'receiving_method' => 'delivery',
            'reason_for_request' => 'Need laptop for online seminar presentation this Friday',
            'service_description' => NULL,
            'expected_return_date' => '2026-04-18',
            'quantity_requested' => 1,
            'status' => 'pending',
            'approval_notes' => NULL,
            'approved_by' => NULL,
            'approved_at' => NULL,
            'created_at' => '2026-04-14 08:20:00',
            'updated_at' => '2026-04-14 08:20:00',
        ],
        [
            'id' => 7,
            'request_number' => 'REQ-00007',
            'user_id' => 3,
            'inventory_id' => 4,
            'request_type' => 'borrow',
            'urgency' => 'medium',
            'receiving_method' => 'pickup',
            'reason_for_request' => 'Projector needed for department orientation event',
            'service_description' => NULL,
            'expected_return_date' => '2026-04-17',
            'quantity_requested' => 1,
            'status' => 'pending',
            'approval_notes' => NULL,
            'approved_by' => NULL,
            'approved_at' => NULL,
            'created_at' => '2026-04-13 11:05:00',
            'updated_at' => '2026-04-13 11:05:00',
        ],
        [
            'id' => 8,
            'request_number' => 'REQ-00008',
            'user_id' => 4,
            'inventory_id' => NULL,
            'request_type' => 'item',
            'urgency' => 'low',
            'receiving_method' => 'delivery',
            'reason_for_request' => 'Replace broken folding tables in the conference room',
            'service_description' => 'Folding Tables - Qty: 4',
            'expected_return_date' => NULL,
            'quantity_requested' => 4,
            'status' => 'pending',
            'approval_notes' => NULL,
            'approved_by' => NULL,
            'approved_at' => NULL,
            'created_at' => '2026-04-13 13:45:00',
            'updated_at' => '2026-04-13 13:45:00',
        ],
        [
            'id' => 9,
            'request_number' => 'REQ-00009',
            'user_id' => 2,
            'inventory_id' => NULL,
            'request_type' => 'service',
            'urgency' => 'high',
            'reason_for_request' => NULL,
            'service_description' => 'Air conditioning unit in Room 204 is not cooling properly. Needs cleaning and refrigerant refill.',
            'expected_return_date' => NULL,
            'quantity_requested' => 1,
            'status' => 'pending',
            'approval_notes' => NULL,
            'approved_by' => NULL,
            'approved_at' => NULL,
            'created_at' => '2026-04-14 09:30:00',
            'updated_at' => '2026-04-14 09:30:00',
        ],
        [
            'id' => 10,
            'request_number' => 'REQ-00010',
            'user_id' => 3,
            'inventory_id' => NULL,
            'request_type' => 'service',
            'urgency' => 'medium',
            'reason_for_request' => NULL,
            'service_description' => 'Several electrical outlets in the faculty lounge are loose and need to be replaced to prevent hazards.',
            'expected_return_date' => NULL,
            'quantity_requested' => 1,
            'status' => 'pending',
            'approval_notes' => NULL,
            'approved_by' => NULL,
            'approved_at' => NULL,
            'created_at' => '2026-04-14 10:15:00',
            'updated_at' => '2026-04-14 10:15:00',
        ],
        [
            'id' => 11,
            'request_number' => 'REQ-00011',
            'user_id' => 4,
            'inventory_id' => 6,
            'request_type' => 'borrow',
            'urgency' => 'low',
            'receiving_method' => 'pickup',
            'reason_for_request' => 'Students need calculators for engineering board exam review',
            'service_description' => NULL,
            'expected_return_date' => '2026-04-25',
            'quantity_requested' => 8,
            'status' => 'pending',
            'approval_notes' => NULL,
            'approved_by' => NULL,
            'approved_at' => NULL,
            'created_at' => '2026-04-14 14:00:00',
            'updated_at' => '2026-04-14 14:00:00',
        ],
        [
            'id' => 12,
            'request_number' => 'REQ-00012',
            'user_id' => 2,
            'inventory_id' => NULL,
            'request_type' => 'item',
            'urgency' => 'critical',
            'receiving_method' => 'delivery',
            'reason_for_request' => 'Emergency: whiteboard in main lecture hall is cracked and unusable',
            'service_description' => 'Portable Whiteboard - Qty: 1',
            'expected_return_date' => NULL,
            'quantity_requested' => 1,
            'status' => 'pending',
            'approval_notes' => NULL,
            'approved_by' => NULL,
            'approved_at' => NULL,
            'created_at' => '2026-04-15 07:55:00',
            'updated_at' => '2026-04-15 07:55:00',
        ],
    ];
}

// ===== BORROW RECORDS DATA =====
function getBorrowRecords() {
    return [
        [
            'id' => 1,
            'user_id' => 2,
            'inventory_id' => 3,
            'borrow_date' => '2026-03-25',
            'expected_return_date' => '2026-04-10',
            'actual_return_date' => NULL,
            'status' => 'overdue',
            'notes' => 'For research project use',
            'created_at' => '2026-03-25 10:00:00',
        ],
        [
            'id' => 2,
            'user_id' => 2,
            'inventory_id' => 6,
            'borrow_date' => '2026-04-09',
            'expected_return_date' => '2026-04-18',
            'actual_return_date' => NULL,
            'status' => 'active',
            'notes' => 'Borrowed for exam week',
            'created_at' => '2026-04-09 10:00:00',
        ],
        [
            'id' => 3,
            'user_id' => 3,
            'inventory_id' => 4,
            'borrow_date' => '2026-03-15',
            'expected_return_date' => '2026-03-22',
            'actual_return_date' => '2026-03-21',
            'status' => 'returned',
            'notes' => 'Used for department meeting',
            'created_at' => '2026-03-15 09:00:00',
        ],
        [
            'id' => 4,
            'user_id' => 4,
            'inventory_id' => 8,
            'borrow_date' => '2026-04-01',
            'expected_return_date' => '2026-04-08',
            'actual_return_date' => '2026-04-07',
            'status' => 'returned',
            'notes' => NULL,
            'created_at' => '2026-04-01 11:00:00',
        ],
    ];
}

// ===== HELPER FUNCTIONS FOR DATA MANIPULATION =====

function findById($data_array, $id) {
    foreach ($data_array as $item) {
        if ($item['id'] == $id) {
            return $item;
        }
    }
    return null;
}

function filterByColumn($data_array, $column, $value) {
    $results = [];
    foreach ($data_array as $item) {
        if (isset($item[$column]) && $item[$column] == $value) {
            $results[] = $item;
        }
    }
    return $results;
}

function filterByColumns($data_array, $filters) {
    $results = [];
    foreach ($data_array as $item) {
        $matches = true;
        foreach ($filters as $column => $value) {
            if (!isset($item[$column]) || $item[$column] != $value) {
                $matches = false;
                break;
            }
        }
        if ($matches) {
            $results[] = $item;
        }
    }
    return $results;
}

function countByStatus($data_array, $status_column = 'status') {
    $counts = [];
    foreach ($data_array as $item) {
        if (isset($item[$status_column])) {
            $status = $item[$status_column];
            $counts[$status] = ($counts[$status] ?? 0) + 1;
        }
    }
    return $counts;
}

function totalItems($data_array) {
    return count($data_array);
}

function sortByColumn(&$data_array, $column, $order = 'DESC') {
    usort($data_array, function($a, $b) use ($column, $order) {
        if ($order === 'DESC') {
            return strcmp($b[$column] ?? '', $a[$column] ?? '');
        } else {
            return strcmp($a[$column] ?? '', $b[$column] ?? '');
        }
    });
    return $data_array;
}

// ===== USER OWNED ITEMS DATA =====
// Tracks items owned by users in past years for historical tracking
function getUserOwnedItems() {
    return [
        ['id' => 1, 'user_id' => 2, 'item_name' => 'Desktop Computer', 'category' => 'Electronics', 'description' => 'Dell Desktop PC with 24" monitor', 'year_owned' => 2023, 'campus_id' => 1, 'quantity' => 1, 'condition' => 'excellent', 'notes' => 'Returned in excellent condition', 'purchase_date' => '2023-05-10', 'created_at' => '2024-06-15 10:00:00'],
        ['id' => 2, 'user_id' => 2, 'item_name' => 'Office Chair', 'category' => 'Furniture', 'description' => 'Ergonomic swivel office chair', 'year_owned' => 2023, 'campus_id' => 1, 'quantity' => 3, 'condition' => 'good', 'notes' => 'Minor wear on armrests', 'purchase_date' => '2023-03-20', 'created_at' => '2024-07-01 14:30:00'],
        ['id' => 3, 'user_id' => 3, 'item_name' => 'Projector', 'category' => 'Electronics', 'description' => '4K Multimedia Projector', 'year_owned' => 2024, 'campus_id' => 2, 'quantity' => 1, 'condition' => 'excellent', 'notes' => 'Used for semester presentations', 'purchase_date' => '2024-01-15', 'created_at' => '2024-08-10 09:15:00'],
        ['id' => 4, 'user_id' => 4, 'item_name' => 'Whiteboard Set', 'category' => 'Equipment', 'description' => 'Portable whiteboard with markers', 'year_owned' => 2022, 'campus_id' => 3, 'quantity' => 2, 'condition' => 'fair', 'notes' => 'Surface has some stains but functional', 'purchase_date' => '2022-09-12', 'created_at' => '2024-05-22 11:45:00'],
        ['id' => 5, 'user_id' => 2, 'item_name' => 'Printer', 'category' => 'Electronics', 'description' => 'Canon Laser Printer', 'year_owned' => 2024, 'campus_id' => 1, 'quantity' => 1, 'condition' => 'excellent', 'notes' => 'Department use', 'purchase_date' => '2024-02-28', 'created_at' => '2024-09-05 16:20:00'],
    ];
}
?>
