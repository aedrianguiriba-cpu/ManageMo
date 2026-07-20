<?php
/**
 * Data Provider — reads from Supabase via REST API.
 * All public function signatures are preserved from the original hardcoded version.
 */

require_once __DIR__ . '/supabase.php';

// ── In-request cache ──────────────────────────────────────────────────────────
function _dbCache(string $key, callable $loader): array {
    static $c = [];
    if (!array_key_exists($key, $c)) $c[$key] = $loader();
    return $c[$key];
}

// ── Main Campus Colleges ──────────────────────────────────────────────────────

function getMainCampusColleges(): array {
    return _dbCache('departments_colleges', function () {
        $rows = supabase()->select('departments', 'type=eq.college&order=abbreviation.asc');
        $out = [];
        foreach ($rows as $r) $out[$r['abbreviation']] = $r['full_name'];
        return $out;
    });
}

// ── Main Campus Offices ───────────────────────────────────────────────────────

function getMainCampusOffices(): array {
    return _dbCache('departments_offices', function () {
        $rows = supabase()->select('departments', 'type=eq.office&order=abbreviation.asc');
        $out = [];
        foreach ($rows as $r) $out[$r['abbreviation']] = $r['full_name'];
        return $out;
    });
}

// ── Combined departments ──────────────────────────────────────────────────────

function getMainCampusDepartments(): array {
    return array_merge(getMainCampusColleges(), getMainCampusOffices());
}

// ── Users ─────────────────────────────────────────────────────────────────────

function getUsers(): array {
    return _dbCache('users', function () {
        $rows = supabase()->select('users', 'order=id.asc');
        return array_map(fn($r) => array_merge($r, ['id' => (int)$r['id'], 'campus_id' => (int)$r['campus_id'], 'is_active' => (int)$r['is_active']]), $rows);
    });
}

// ── Campuses ──────────────────────────────────────────────────────────────────

function getCampuses(): array {
    return _dbCache('campuses', function () {
        $rows = supabase()->select('campuses', 'order=id.asc');
        return array_map(fn($r) => [
            'id'          => (int)$r['id'],
            'name'        => $r['name'],
            'location'    => $r['location'] ?? '',
            'description' => $r['description'] ?? '',
            'is_default'  => (bool)$r['is_default'],
            'colleges'    => (int)$r['id'] === 1 ? array_values(getMainCampusColleges()) : [],
        ], $rows);
    });
}

// ── Borrow Catalog (static — not stored in DB) ────────────────────────────────

function getBorrowCatalog(): array {
    return [
        ['id'=>1, 'name'=>'Laptop Computer',        'category'=>'Electronics', 'description'=>'Portable laptop for academic or office use'],
        ['id'=>2, 'name'=>'Desktop Computer',        'category'=>'Electronics', 'description'=>'Desktop PC unit'],
        ['id'=>3, 'name'=>'Projector',               'category'=>'Electronics', 'description'=>'Multimedia projector for presentations'],
        ['id'=>4, 'name'=>'Monitor',                 'category'=>'Electronics', 'description'=>'External display monitor'],
        ['id'=>5, 'name'=>'Printer',                 'category'=>'Electronics', 'description'=>'Laser or inkjet printer'],
        ['id'=>6, 'name'=>'Extension Cord',          'category'=>'Electronics', 'description'=>'Multi-outlet power extension'],
        ['id'=>7, 'name'=>'Monobloc Chair',          'category'=>'Furniture',   'description'=>'Plastic stackable chair'],
        ['id'=>8, 'name'=>'Folding Table',           'category'=>'Furniture',   'description'=>'Portable folding table'],
        ['id'=>9, 'name'=>'Office Chair',            'category'=>'Furniture',   'description'=>'Ergonomic swivel office chair'],
        ['id'=>10,'name'=>'Podium/Lectern',          'category'=>'Furniture',   'description'=>'Standing podium for presentations'],
        ['id'=>11,'name'=>'Scientific Calculator',   'category'=>'Equipment',   'description'=>'Advanced scientific calculator'],
        ['id'=>12,'name'=>'Whiteboard',              'category'=>'Equipment',   'description'=>'Portable whiteboard'],
        ['id'=>13,'name'=>'Megaphone / Bullhorn',    'category'=>'Equipment',   'description'=>'Battery-powered megaphone for announcements'],
        ['id'=>14,'name'=>'Microphone & Speaker Set','category'=>'Equipment',   'description'=>'Wireless microphone with portable speaker'],
        ['id'=>15,'name'=>'HDMI / VGA Cable',        'category'=>'Equipment',   'description'=>'Display cable for projector or monitor connection'],
        ['id'=>17,'name'=>'Tarpaulin Stand',         'category'=>'Supplies',    'description'=>'Adjustable stand for tarpaulin display'],
        ['id'=>18,'name'=>'Electric Fan',            'category'=>'Appliances',  'description'=>'Portable electric stand fan'],
        ['id'=>19,'name'=>'Water Dispenser',         'category'=>'Appliances',  'description'=>'Hot and cold water dispenser'],
        ['id'=>20,'name'=>'First Aid Kit',           'category'=>'Safety',      'description'=>'Standard first aid kit for events'],
    ];
}

// ── Inventory ─────────────────────────────────────────────────────────────────

function getInventory(): array {
    return _dbCache('inventory', function () {
        $rows = supabase()->select('inventory', 'order=id.asc');
        return array_map(fn($r) => array_merge($r, ['id' => (int)$r['id'], 'campus_id' => (int)$r['campus_id'], 'quantity' => (int)$r['quantity'], 'cost' => $r['cost'] !== null ? (float)$r['cost'] : null]), $rows);
    });
}

// ── Requests ──────────────────────────────────────────────────────────────────

function getRequests(): array {
    return _dbCache('requests', function () {
        $rows = supabase()->select('requests', 'order=id.asc');
        return array_map(fn($r) => array_merge($r, ['id' => (int)$r['id'], 'user_id' => (int)$r['user_id'], 'inventory_id' => $r['inventory_id'] !== null ? (int)$r['inventory_id'] : null, 'quantity_requested' => (int)$r['quantity_requested'], 'approved_by' => $r['approved_by'] !== null ? (int)$r['approved_by'] : null]), $rows);
    });
}

// ── Borrow Records ────────────────────────────────────────────────────────────

function getBorrowRecords(): array {
    return _dbCache('borrow_records', function () {
        $rows = supabase()->select('borrow_records', 'order=id.asc');
        return array_map(fn($r) => array_merge($r, ['id' => (int)$r['id'], 'user_id' => (int)$r['user_id'], 'inventory_id' => (int)$r['inventory_id'], 'request_id' => $r['request_id'] !== null ? (int)$r['request_id'] : null]), $rows);
    });
}

// ── User Owned Items ──────────────────────────────────────────────────────────

function getUserOwnedItems(): array {
    return _dbCache('user_owned_items', function () {
        $rows = supabase()->select('user_owned_items', 'order=id.asc');
        return array_map(fn($r) => array_merge($r, ['id' => (int)$r['id'], 'user_id' => (int)$r['user_id'], 'campus_id' => (int)$r['campus_id'], 'quantity' => (int)$r['quantity'], 'year_owned' => $r['year_owned'] !== null ? (int)$r['year_owned'] : null]), $rows);
    });
}

// ── Generic helper functions (unchanged) ──────────────────────────────────────

function findById(array $data_array, $id): ?array {
    foreach ($data_array as $item) {
        if ($item['id'] == $id) return $item;
    }
    return null;
}

function filterByColumn(array $data_array, string $column, $value): array {
    $results = [];
    foreach ($data_array as $item) {
        if (isset($item[$column]) && $item[$column] == $value) $results[] = $item;
    }
    return $results;
}

function filterByColumns(array $data_array, array $filters): array {
    $results = [];
    foreach ($data_array as $item) {
        $matches = true;
        foreach ($filters as $column => $value) {
            if (!isset($item[$column]) || $item[$column] != $value) { $matches = false; break; }
        }
        if ($matches) $results[] = $item;
    }
    return $results;
}

function countByStatus(array $data_array, string $status_column = 'status'): array {
    $counts = [];
    foreach ($data_array as $item) {
        if (isset($item[$status_column])) {
            $counts[$item[$status_column]] = ($counts[$item[$status_column]] ?? 0) + 1;
        }
    }
    return $counts;
}

function totalItems(array $data_array): int {
    return count($data_array);
}

function sortByColumn(array &$data_array, string $column, string $order = 'DESC'): array {
    usort($data_array, function($a, $b) use ($column, $order) {
        return $order === 'DESC'
            ? strcmp($b[$column] ?? '', $a[$column] ?? '')
            : strcmp($a[$column] ?? '', $b[$column] ?? '');
    });
    return $data_array;
}
