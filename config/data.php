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

// ── Custom departments (campuses/colleges/offices added via admin UI) ──────────

function _loadCustomDepartments(): array {
    $rows = _dbCache('custom_departments', fn() => supabase()->select('custom_departments'));
    $out  = ['colleges' => [], 'offices' => [], 'campuses' => []];
    foreach ($rows as $r) {
        if ($r['type'] === 'college') {
            $out['colleges'][$r['abbreviation']] = $r['full_name'];
        } elseif ($r['type'] === 'office') {
            $out['offices'][$r['abbreviation']] = $r['full_name'];
        } elseif ($r['type'] === 'campus') {
            $out['campuses'][] = [
                'id'          => (int)$r['id'],
                'name'        => $r['name'] ?? '',
                'location'    => $r['location'] ?? '',
                'description' => $r['description'] ?? '',
                'colleges'    => [],
            ];
        }
    }
    return $out;
}

/** @deprecated Use dbAddCustomDepartment() / dbDeleteCustomDepartment() instead */
function saveCustomDepartments(array $data): void {
    // no-op — direct DB mutations are used in admin/inventory-campus.php
}

// ── Main Campus Colleges ──────────────────────────────────────────────────────

function getMainCampusColleges(): array {
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
    return array_merge($defaults, _loadCustomDepartments()['colleges'] ?? []);
}

// ── Main Campus Offices ───────────────────────────────────────────────────────

function getMainCampusOffices(): array {
    $defaults = [
        'OUP'    => 'Office of the University President (OUP)',
        'OVPAA'  => 'Office of the VP for Academic Affairs (OVPAA)',
        'OVPAF'  => 'Office of the VP for Administration & Finance (OVPAF)',
        'OVPRDE' => 'Office of the VP for Research, Development & Extension (OVPRDE)',
        'OUR'    => 'Office of the University Registrar (OUR)',
        'OSAS'   => 'Office of Student Affairs and Services (OSAS)',
        'HRMO'   => 'Human Resource Management Office (HRMO)',
        'ICTO'   => 'Information and Communications Technology Office (ICTO)',
        'FBO'    => 'Finance and Budget Office (FBO)',
        'PMO'    => 'Procurement Management Office (PMO)',
        'PPMO'   => 'Physical Plant and Maintenance Office (PPMO)',
        'ULib'   => 'University Library (ULib)',
        'GCC'    => 'Guidance and Counseling Center (GCC)',
        'PDO'    => 'Planning and Development Office (PDO)',
    ];
    return array_merge($defaults, _loadCustomDepartments()['offices'] ?? []);
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
    $defaults = [
        ['id'=>1,'name'=>'Main Campus',              'location'=>'Brgy. Cabambangan, Bacolor, Pampanga',  'description'=>'Central campus of Pampanga State University hosting 8 colleges and the university administration.', 'colleges'=>['College of Engineering and Architecture (CEA)','College of Education (COE)','College of Computing Studies (CCS)','College of Business Studies (CBS)','College of Arts and Sciences (CAS)','College of Industrial Technology (CIT)','College of Hospitality and Tourism Management (CHTM)','College of Social Sciences and Philosophy (CSSP)']],
        ['id'=>2,'name'=>'Mexico Campus',            'location'=>'Mexico, Pampanga',                      'description'=>'PSU extension campus serving the Mexico municipality and surrounding areas.','colleges'=>[]],
        ['id'=>3,'name'=>'Porac Campus',             'location'=>'Porac, Pampanga',                       'description'=>'PSU extension campus serving the Porac municipality and surrounding areas.','colleges'=>[]],
        ['id'=>4,'name'=>'Santo Tomas Campus',       'location'=>'Santo Tomas, Pampanga',                 'description'=>'PSU satellite campus providing quality education in the Santo Tomas area.','colleges'=>[]],
        ['id'=>5,'name'=>'Lubao Campus',             'location'=>'Sta. Catalina, Lubao, Pampanga',        'description'=>'PSU extension campus offering specialized courses in the Lubao area.','colleges'=>[]],
        ['id'=>6,'name'=>'Candaba Campus',           'location'=>'Candaba, Pampanga',                     'description'=>'PSU extension campus serving the educational needs of the Candaba community.','colleges'=>[]],
        ['id'=>7,'name'=>'Apalit Campus',            'location'=>'Apalit, Pampanga',                      'description'=>'PSU dedicated campus serving the Apalit municipality.','colleges'=>[]],
        ['id'=>8,'name'=>'City of San Fernando Campus','location'=>'City of San Fernando, Pampanga',      'description'=>'PSU satellite campus in the provincial capital, City of San Fernando.','colleges'=>[]],
    ];
    foreach (_loadCustomDepartments()['campuses'] ?? [] as $c) {
        $defaults[] = $c;
    }
    return $defaults;
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
