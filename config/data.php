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

// ── Colleges / Offices ────────────────────────────────────────────────────────
// Colleges and offices are global lists, independent of any campus — not scoped
// or nested under a "Main Campus". A $campus_id argument is still accepted so
// existing call sites keep working, but it no longer affects the result.

function _departmentsByType(string $type): array {
    return _dbCache("departments_{$type}", function () use ($type) {
        $rows = supabase()->select('departments', "type=eq.$type&order=abbreviation.asc");
        $out = [];
        foreach ($rows as $r) $out[$r['abbreviation']] = $r['full_name'];
        return $out;
    });
}

function getMainCampusColleges(?int $campus_id = null): array {
    return _departmentsByType('college');
}

function getMainCampusOffices(?int $campus_id = null): array {
    return _departmentsByType('office');
}

// ── Combined departments ──────────────────────────────────────────────────────

function getMainCampusDepartments(?int $campus_id = null): array {
    return array_merge(getMainCampusColleges(), getMainCampusOffices());
}

// Flat abbreviation => full_name map spanning ALL department types (campuses
// included). Used wherever a single value needs to represent "Campus, College,
// or Office" — e.g. the merged inventory owner picker, which stores whichever
// one was picked as a plain abbreviation in inventory.college_id.
function getAllDepartmentNames(): array {
    $campusNames = [];
    foreach (getDepartmentCampuses() as $c) {
        if ($c['abbreviation'] !== '') $campusNames[$c['abbreviation']] = $c['name'];
    }
    return array_merge($campusNames, getMainCampusDepartments());
}

// ── Campuses (also a departments-table entry, type='campus') ───────────────────
// Unlike colleges/offices, a campus carries a location/description, so it needs
// its own richer shape instead of the flat abbreviation => full_name map above.

function getDepartmentCampuses(): array {
    return _dbCache('departments_campus', function () {
        $rows = supabase()->select('departments', 'type=eq.campus&order=full_name.asc');
        return array_map(fn($r) => [
            'id'           => (int)$r['id'],
            'abbreviation' => $r['abbreviation'] ?? '',
            'name'         => $r['full_name'],
            'location'     => $r['location'] ?? '',
            'description'  => $r['description'] ?? '',
            'is_default'   => (bool)$r['is_default'],
        ], $rows);
    });
}

// ── Users ─────────────────────────────────────────────────────────────────────

function getUsers(): array {
    return _dbCache('users', function () {
        $rows = supabase()->select('users', 'order=id.asc');
        // campus_id is no longer relied on (college_id is the source of truth for a
        // user's department) — coalesce to 1 in case the column is absent/null.
        return array_map(fn($r) => array_merge($r, ['id' => (int)$r['id'], 'campus_id' => (int)($r['campus_id'] ?? 1), 'is_active' => (int)$r['is_active']]), $rows);
    });
}

// ── Campuses ──────────────────────────────────────────────────────────────────
// Campuses are departments-table rows (type='campus') — same table and
// mechanism as colleges/offices, not the old standalone `campuses` table.
// `campus_id` on users/inventory/user_owned_items now means departments.id.

function getCampuses(): array {
    return getDepartmentCampuses();
}

// ── Inventory ─────────────────────────────────────────────────────────────────

function getInventory(): array {
    return _dbCache('inventory', function () {
        $rows = supabase()->select('inventory', 'order=id.asc');
        // campus_id is no longer relied on for inventory (college_id is the source of
        // truth for ownership) — coalesce to 1 in case the column is absent/null.
        return array_map(fn($r) => array_merge($r, ['id' => (int)$r['id'], 'campus_id' => (int)($r['campus_id'] ?? 1), 'quantity' => (int)$r['quantity'], 'cost' => $r['cost'] !== null ? (float)$r['cost'] : null]), $rows);
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
        // campus_id is no longer relied on here either (college_id is the source of
        // truth for ownership) — coalesce to 1 in case the column is absent/null.
        return array_map(fn($r) => array_merge($r, ['id' => (int)$r['id'], 'user_id' => (int)$r['user_id'], 'campus_id' => (int)($r['campus_id'] ?? 1), 'quantity' => (int)$r['quantity'], 'year_owned' => $r['year_owned'] !== null ? (int)$r['year_owned'] : null]), $rows);
    });
}

// ── Notifications ──────────────────────────────────────────────────────────────
// Not run through _dbCache: unread counts need to stay fresh per-user, and the
// dataset per user is small, so a direct query is simplest and correct.

function getNotifications(int $user_id, int $limit = 20): array {
    $rows = supabase()->select('notifications', "user_id=eq.$user_id&order=created_at.desc&limit=$limit");
    if (!is_array($rows)) return [];
    return array_map(fn($r) => array_merge($r, [
        'id'      => (int)$r['id'],
        'user_id' => (int)$r['user_id'],
        'is_read' => (bool)$r['is_read'],
    ]), $rows);
}

function getUnreadNotificationCount(int $user_id): int {
    $rows = supabase()->selectCols('notifications', 'id', "user_id=eq.$user_id&is_read=eq.false");
    return is_array($rows) ? count($rows) : 0;
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
