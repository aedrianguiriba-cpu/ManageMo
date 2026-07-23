<?php
/**
 * Data Provider — reads from Supabase via REST API.
 * All public function signatures are preserved from the original hardcoded version.
 */

require_once __DIR__ . '/supabase.php';

// ── Cache TTL in seconds ──────────────────────────────────────────────────────
const DB_CACHE_TTL = 60;

// Shared invalidation flag file — written by any admin write; checked by all sessions.
define('DB_CACHE_FLAG_FILE', sys_get_temp_dir() . '/managemo_cache_invalidated.txt');

function _globalCacheInvalidatedAt(): int {
    static $t = null;
    if ($t === null) {
        $t = file_exists(DB_CACHE_FLAG_FILE) ? (int)file_get_contents(DB_CACHE_FLAG_FILE) : 0;
    }
    return $t;
}

// ── In-request + session cache ────────────────────────────────────────────────
function _dbCache(string $key, callable $loader): array {
    static $c = [];

    // Layer 1: in-request memory (free)
    if (array_key_exists($key, $c)) return $c[$key];

    // Layer 2: session cache with TTL, invalidated by global flag
    if (session_status() === PHP_SESSION_ACTIVE) {
        $entry = $_SESSION['_db_cache'][$key] ?? null;
        if ($entry
            && (time() - $entry['ts']) < DB_CACHE_TTL
            && $entry['ts'] >= _globalCacheInvalidatedAt()) {
            $c[$key] = $entry['data'];
            return $c[$key];
        }
    }

    // Layer 3: live Supabase API call
    $data = $loader();
    $c[$key] = $data;

    if (session_status() === PHP_SESSION_ACTIVE) {
        $_SESSION['_db_cache'][$key] = ['data' => $data, 'ts' => time()];
    }

    return $data;
}

// Call this after any write/update/delete so all sessions re-fetch fresh data.
function clearDataCache(string ...$keys): void {
    // Write global invalidation timestamp so other users' session caches also expire.
    @file_put_contents(DB_CACHE_FLAG_FILE, time());

    if (session_status() !== PHP_SESSION_ACTIVE) return;
    if (empty($keys)) {
        unset($_SESSION['_db_cache']);
    } else {
        foreach ($keys as $k) unset($_SESSION['_db_cache'][$k]);
    }
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

// ── Request Items ─────────────────────────────────────────────────────────────

function getRequestItems(int $request_id = 0): array {
    if ($request_id > 0) {
        $rows = supabase()->select('request_items', 'request_id=eq.' . $request_id . '&order=id.asc');
    } else {
        $rows = supabase()->select('request_items', 'order=id.asc');
    }
    if (!is_array($rows)) return [];
    return array_map(fn($r) => array_merge($r, [
        'id'           => (int)$r['id'],
        'request_id'   => (int)$r['request_id'],
        'inventory_id' => $r['inventory_id'] !== null ? (int)$r['inventory_id'] : null,
        'quantity'     => (int)$r['quantity'],
    ]), $rows);
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
