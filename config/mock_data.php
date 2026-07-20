<?php
/**
 * Mock Data Provider
 * Provides all application data without requiring a database
 * This allows the application to run with hardcoded/mock data only
 */

class MockData
{
    public static function getCampuses()
    {
        return [
            ['id' => 1, 'name' => 'Main Campus', 'location' => 'Downtown - Central Location', 'description' => 'Primary campus'],
            ['id' => 2, 'name' => 'Northern Campus', 'location' => 'North District', 'description' => 'Science Building'],
            ['id' => 3, 'name' => 'Southern Campus', 'location' => 'South District', 'description' => 'Engineering Building'],
        ];
    }

    public static function getUsers()
    {
        return [
            [
                'id' => 1,
                'email' => 'admin@university.edu',
                'password' => '$2y$10$nLrah9DuGOziCM/BlWJFheD7ECyeITABU6Lnb5dei5IIrC3nXdPCG', // password: admin123
                'full_name' => 'John Administrator',
                'role' => 'admin',
                'campus_id' => NULL,
                'phone' => '09171234567',
                'is_active' => 1,
                'created_at' => '2026-01-15 08:00:00',
                'updated_at' => '2026-04-11 10:00:00',
            ],
            [
                'id' => 2,
                'email' => 'user@university.edu',
                'password' => '$2y$10$ujcshmXy9T9ncnJxOE7oNueB16kTlTiWH9QY0ggUHrXSZUClfXpVa', // password: user123
                'full_name' => 'Maria Garcia',
                'role' => 'user',
                'campus_id' => 1,
                'phone' => '09171234568',
                'is_active' => 1,
                'created_at' => '2026-01-20 09:00:00',
                'updated_at' => '2026-04-11 10:00:00',
            ],
            [
                'id' => 3,
                'email' => 'custodian1@university.edu',
                'password' => '$2y$10$6aKE8TKp4PxeX/jg3Y5TE.fITuRur4vsK1MSGBaE8pWUhPQFyi8Ea', // password: custodian123
                'full_name' => 'Carlos Santos',
                'role' => 'user',
                'campus_id' => 2,
                'phone' => '09171234569',
                'is_active' => 1,
                'created_at' => '2026-02-01 08:30:00',
                'updated_at' => '2026-04-11 10:00:00',
            ],
            [
                'id' => 4,
                'email' => 'custodian2@university.edu',
                'password' => '$2y$10$6aKE8TKp4PxeX/jg3Y5TE.fITuRur4vsK1MSGBaE8pWUhPQFyi8Ea', // password: custodian123
                'full_name' => 'Anna Rodriguez',
                'role' => 'user',
                'campus_id' => 3,
                'phone' => '09171234570',
                'is_active' => 1,
                'created_at' => '2026-02-05 09:15:00',
                'updated_at' => '2026-04-11 10:00:00',
            ],
        ];
    }

    public static function getInventory()
    {
        return [
            [
                'id' => 1,
                'qr_code_id' => 'QR-A1B2C3D4E5',
                'item_name' => 'Wooden Chair',
                'category' => 'Furniture',
                'description' => 'Brown wooden chair with back support',
                'campus_id' => 1,
                'quantity' => 1,
                'status' => 'available',
                'location' => 'Building A - Room 101',
                'purchase_date' => '2024-01-15',
                'cost' => 1500.00,
                'condition' => 'excellent',
                'created_at' => '2026-01-20 10:00:00',
            ],
            [
                'id' => 2,
                'qr_code_id' => 'QR-F6G7H8I9J0',
                'item_name' => 'Office Desk',
                'category' => 'Furniture',
                'description' => 'Large wooden office desk',
                'campus_id' => 1,
                'quantity' => 1,
                'status' => 'available',
                'location' => 'Building A - Room 102',
                'purchase_date' => '2024-02-10',
                'cost' => 3500.00,
                'condition' => 'good',
                'created_at' => '2026-01-20 10:15:00',
            ],
            [
                'id' => 3,
                'qr_code_id' => 'QR-K1L2M3N4O5',
                'item_name' => 'Air Conditioning Unit',
                'category' => 'Appliances',
                'description' => '2 HP window-type AC',
                'campus_id' => 1,
                'quantity' => 1,
                'status' => 'available',
                'location' => 'Building B - Room 201',
                'purchase_date' => '2023-06-05',
                'cost' => 15000.00,
                'condition' => 'excellent',
                'created_at' => '2026-01-20 10:30:00',
            ],
            [
                'id' => 4,
                'qr_code_id' => 'QR-P6Q7R8S9T0',
                'item_name' => 'Ceiling Fan',
                'category' => 'Appliances',
                'description' => 'White ceiling fan 60 inches',
                'campus_id' => 2,
                'quantity' => 1,
                'status' => 'available',
                'location' => 'Building C - Room 301',
                'purchase_date' => '2024-03-20',
                'cost' => 2500.00,
                'condition' => 'good',
                'created_at' => '2026-01-20 10:45:00',
            ],
            [
                'id' => 5,
                'qr_code_id' => 'QR-U1V2W3X4Y5',
                'item_name' => 'Computer Desktop',
                'category' => 'Electronics',
                'description' => 'Intel i7 Desktop with Monitor',
                'campus_id' => 1,
                'quantity' => 1,
                'status' => 'available',
                'location' => 'Building A - Lab 105',
                'purchase_date' => '2024-04-01',
                'cost' => 35000.00,
                'condition' => 'excellent',
                'created_at' => '2026-01-20 11:00:00',
            ],
            [
                'id' => 6,
                'qr_code_id' => 'QR-Z6A7B8C9D0',
                'item_name' => 'Laptop Computer',
                'category' => 'Electronics',
                'description' => 'HP Laptop 15 inch',
                'campus_id' => 2,
                'quantity' => 1,
                'status' => 'borrowed',
                'location' => 'Building C - Office 302',
                'purchase_date' => '2024-03-15',
                'cost' => 28000.00,
                'condition' => 'good',
                'created_at' => '2026-01-20 11:15:00',
            ],
            [
                'id' => 7,
                'qr_code_id' => 'QR-E1F2G3H4I5',
                'item_name' => 'Whiteboard',
                'category' => 'Office Equipment',
                'description' => 'Large whiteboard 6x4 feet',
                'campus_id' => 3,
                'quantity' => 1,
                'status' => 'available',
                'location' => 'Building D - Classroom 401',
                'purchase_date' => '2024-05-10',
                'cost' => 2000.00,
                'condition' => 'fair',
                'created_at' => '2026-01-20 11:30:00',
            ],
            [
                'id' => 8,
                'qr_code_id' => 'QR-J6K7L8M9N0',
                'item_name' => 'Projector',
                'category' => 'Electronics',
                'description' => 'HD Projector 3000 lumens',
                'campus_id' => 1,
                'quantity' => 1,
                'status' => 'available',
                'location' => 'Building B - Auditorium 205',
                'purchase_date' => '2023-12-01',
                'cost' => 22000.00,
                'condition' => 'excellent',
                'created_at' => '2026-01-20 11:45:00',
            ],
            [
                'id' => 9,
                'qr_code_id' => 'QR-O1P2Q3R4S5',
                'item_name' => 'Printer',
                'category' => 'Electronics',
                'description' => 'Canon Laser Printer',
                'campus_id' => 2,
                'quantity' => 1,
                'status' => 'maintenance',
                'location' => 'Building C - IT Office 303',
                'purchase_date' => '2024-02-20',
                'cost' => 12000.00,
                'condition' => 'good',
                'created_at' => '2026-01-20 12:00:00',
            ],
            [
                'id' => 10,
                'qr_code_id' => 'QR-T1U2V3W4X5',
                'item_name' => 'Smart TV 55 inch',
                'category' => 'Electronics',
                'description' => '4K Smart Television with stand',
                'campus_id' => 1,
                'quantity' => 1,
                'status' => 'requested',
                'location' => 'Building A - Conference Room 103',
                'purchase_date' => '2024-06-10',
                'cost' => 25000.00,
                'condition' => 'excellent',
                'created_at' => '2026-01-20 12:15:00',
            ],
            [
                'id' => 11,
                'qr_code_id' => 'QR-Y6Z7A8B9C0',
                'item_name' => 'Bookcase Cabinet',
                'category' => 'Furniture',
                'description' => '5-shelf wooden bookcase',
                'campus_id' => 2,
                'quantity' => 1,
                'status' => 'requested',
                'location' => 'Building C - Library 304',
                'purchase_date' => '2024-04-10',
                'cost' => 2800.00,
                'condition' => 'good',
                'created_at' => '2026-01-20 12:30:00',
            ],
            [
                'id' => 12,
                'qr_code_id' => 'QR-D1E2F3G4H5',
                'item_name' => 'Document Scanner',
                'category' => 'Office Equipment',
                'description' => 'High-speed document scanner',
                'campus_id' => 3,
                'quantity' => 1,
                'status' => 'requested',
                'location' => 'Building D - Admin Office 402',
                'purchase_date' => '2024-05-20',
                'cost' => 8500.00,
                'condition' => 'excellent',
                'created_at' => '2026-01-20 12:45:00',
            ],
        ];
    }

    public static function getRequests()
    {
        return [
            [
                'id' => 1,
                'request_number' => 'REQ-20260401120000-AB123',
                'user_id' => 2,
                'request_type' => 'borrow',
                'inventory_id' => 1,
                'service_description' => NULL,
                'urgency' => 'medium',
                'status' => 'pending',
                'reason_for_request' => 'Need for classroom use',
                'approved_by' => NULL,
                'approved_at' => NULL,
                'expected_return_date' => '2026-04-20',
                'created_at' => '2026-04-01 12:00:00',
                'updated_at' => '2026-04-01 12:00:00',
                'full_name' => 'Maria Garcia',
                'email' => 'user@university.edu',
                'item_name' => 'Wooden Chair',
                'qr_code_id' => 'QR-A1B2C3D4E5',
            ],
            [
                'id' => 2,
                'request_number' => 'REQ-20260402130000-CD456',
                'user_id' => 2,
                'request_type' => 'service',
                'inventory_id' => 3,
                'service_description' => 'Air conditioning unit is making noise and not cooling properly',
                'urgency' => 'critical',
                'status' => 'pending',
                'reason_for_request' => NULL,
                'approved_by' => NULL,
                'approved_at' => NULL,
                'expected_return_date' => NULL,
                'created_at' => '2026-04-02 13:00:00',
                'updated_at' => '2026-04-02 13:00:00',
                'full_name' => 'Maria Garcia',
                'email' => 'user@university.edu',
                'item_name' => 'Air Conditioning Unit',
                'qr_code_id' => 'QR-K1L2M3N4O5',
            ],
            [
                'id' => 3,
                'request_number' => 'REQ-20260403140000-EF789',
                'user_id' => 3,
                'request_type' => 'item',
                'inventory_id' => NULL,
                'service_description' => 'Canon Projector 4K - Qty: 2',
                'urgency' => 'medium',
                'status' => 'approved',
                'reason_for_request' => 'Need for classroom equipment',
                'approved_by' => 1,
                'approved_at' => '2026-04-04 10:00:00',
                'expected_return_date' => NULL,
                'created_at' => '2026-04-03 14:00:00',
                'updated_at' => '2026-04-04 10:00:00',
                'full_name' => 'Carlos Santos',
                'email' => 'custodian1@university.edu',
                'item_name' => NULL,
                'qr_code_id' => NULL,
            ],
        ];
    }

    public static function getBorrowRecords()
    {
        return [
            [
                'id' => 1,
                'inventory_id' => 6,
                'user_id' => 2,
                'request_id' => 1,
                'borrow_date' => '2026-03-25',
                'expected_return_date' => '2026-04-10',
                'actual_return_date' => NULL,
                'status' => 'active',
                'notes' => 'For research project',
                'created_at' => '2026-03-25 10:00:00',
                'item_name' => 'Laptop Computer',
                'qr_code_id' => 'QR-Z6A7B8C9D0',
            ],
        ];
    }

    public static function getActivityLogs()
    {
        return [
            [
                'id' => 1,
                'user_id' => 1,
                'action' => 'LOGIN',
                'description' => 'Admin login',
                'table_name' => NULL,
                'record_id' => NULL,
                'created_at' => '2026-04-11 08:00:00',
            ],
            [
                'id' => 2,
                'user_id' => 1,
                'action' => 'APPROVE',
                'description' => 'Approved request #REQ-20260403140000-EF789',
                'table_name' => 'requests',
                'record_id' => 3,
                'created_at' => '2026-04-04 10:00:00',
            ],
        ];
    }
}

// Global mock database object
global $mock_data;
$mock_data = new MockData();
?>
