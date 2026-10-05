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

// Directory for the shared cross-user cache — one file per table, shared by
// every visitor instead of duplicated per-session. This is what makes the
// Supabase round-trip happen once per TTL window for the whole site instead
// of once per TTL window per logged-in user.
define('DB_CACHE_DIR', sys_get_temp_dir() . '/managemo_cache');

function _globalCacheInvalidatedAt(): int {
    static $t = null;
    if ($t === null) {
        $t = file_exists(DB_CACHE_FLAG_FILE) ? (int)file_get_contents(DB_CACHE_FLAG_FILE) : 0;
    }
    return $t;
}

function _dbCacheFile(string $key): string {
    return DB_CACHE_DIR . '/' . preg_replace('/[^a-zA-Z0-9_]/', '_', $key) . '.json';
}

// In-request memory store, shared by _dbCache() and warmSharedCache() (returned
// by reference so both can populate the same underlying array).
function &_dbCacheMem(): array {
    static $c = [];
    return $c;
}

// Reads a key's still-fresh shared file cache entry into memory, if any.
// Returns true on a hit (and populates $mem[$key]), false otherwise.
function _dbCacheReadFresh(string $key, array &$mem): bool {
    $raw = @file_get_contents(_dbCacheFile($key));
    if ($raw === false) return false;
    $entry = json_decode($raw, true);
    if (!is_array($entry)
        || (time() - $entry['ts']) >= DB_CACHE_TTL
        || $entry['ts'] < _globalCacheInvalidatedAt()) {
        return false;
    }
    $mem[$key] = $entry['data'];
    return true;
}

function _dbCacheSet(string $key, array $data): void {
    $mem =& _dbCacheMem();
    $mem[$key] = $data;
    if (!is_dir(DB_CACHE_DIR)) @mkdir(DB_CACHE_DIR, 0777, true);
    @file_put_contents(_dbCacheFile($key), json_encode(['data' => $data, 'ts' => time()]), LOCK_EX);
}

// ── In-request + shared file cache ────────────────────────────────────────────
function _dbCache(string $key, callable $loader): array {
    $mem =& _dbCacheMem();

    // Layer 1: in-request memory (free) — also what warmSharedCache() primes.
    if (array_key_exists($key, $mem)) return $mem[$key];

    // Layer 2: shared file cache with TTL, invalidated by global flag.
    // Shared across ALL users/sessions so only one visitor per TTL window
    // pays the Supabase round-trip; everyone else reads the cached file.
    if (_dbCacheReadFresh($key, $mem)) return $mem[$key];

    // Layer 3: live Supabase API call
    $data = $loader();
    _dbCacheSet($key, $data);
    return $data;
}

// Call this after any write/update/delete so all users re-fetch fresh data.
function clearDataCache(string ...$keys): void {
    // Write global invalidation timestamp so every cached file is treated as stale.
    @file_put_contents(DB_CACHE_FLAG_FILE, time());

    if (empty($keys)) {
        foreach (glob(DB_CACHE_DIR . '/*.json') ?: [] as $f) @unlink($f);
    } else {
        foreach ($keys as $k) @unlink(_dbCacheFile($k));
    }
}

// ── Colleges / Offices ────────────────────────────────────────────────────────
// Colleges and offices are global lists, independent of any campus — not scoped
// or nested under a "Main Campus". A $campus_id argument is still accepted so
// existing call sites keep working, but it no longer affects the result.

function _mapDepartmentsByType(array $rows): array {
    $out = [];
    foreach ($rows as $r) $out[$r['abbreviation']] = $r['full_name'];
    return $out;
}

function _departmentsByType(string $type): array {
    return _dbCache("departments_{$type}", function () use ($type) {
        $rows = supabase()->select('departments', "type=eq.$type&order=abbreviation.asc");
        return _mapDepartmentsByType($rows);
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

function _mapCampusRow(array $r): array {
    return [
        'id'           => (int)$r['id'],
        'abbreviation' => $r['abbreviation'] ?? '',
        'name'         => $r['full_name'],
        'location'     => $r['location'] ?? '',
        'description'  => $r['description'] ?? '',
        'is_default'   => (bool)$r['is_default'],
    ];
}

function getDepartmentCampuses(): array {
    return _dbCache('departments_campus', function () {
        $rows = supabase()->select('departments', 'type=eq.campus&order=full_name.asc');
        return array_map('_mapCampusRow', $rows);
    });
}

// ── Users ─────────────────────────────────────────────────────────────────────

function _mapUserRow(array $r): array {
    // campus_id is no longer relied on (college_id is the source of truth for a
    // user's department) — coalesce to 1 in case the column is absent/null.
    return array_merge($r, ['id' => (int)$r['id'], 'campus_id' => (int)($r['campus_id'] ?? 1), 'is_active' => (int)$r['is_active']]);
}

function getUsers(): array {
    return _dbCache('users', function () {
        $rows = supabase()->select('users', 'order=id.asc');
        return array_map('_mapUserRow', $rows);
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

function _mapInventoryRow(array $r): array {
    // campus_id is no longer relied on for inventory (college_id is the source of
    // truth for ownership) — coalesce to 1 in case the column is absent/null.
    return array_merge($r, ['id' => (int)$r['id'], 'campus_id' => (int)($r['campus_id'] ?? 1), 'quantity' => (int)$r['quantity'], 'cost' => $r['cost'] !== null ? (float)$r['cost'] : null]);
}

function getInventory(): array {
    return _dbCache('inventory', function () {
        $rows = supabase()->select('inventory', 'order=id.asc');
        return array_map('_mapInventoryRow', $rows);
    });
}

// ── Requests ──────────────────────────────────────────────────────────────────

function _mapRequestRow(array $r): array {
    return array_merge($r, ['id' => (int)$r['id'], 'user_id' => (int)$r['user_id'], 'inventory_id' => $r['inventory_id'] !== null ? (int)$r['inventory_id'] : null, 'quantity_requested' => (int)$r['quantity_requested'], 'approved_by' => $r['approved_by'] !== null ? (int)$r['approved_by'] : null]);
}

function getRequests(): array {
    return _dbCache('requests', function () {
        $rows = supabase()->select('requests', 'order=id.asc');
        return array_map('_mapRequestRow', $rows);
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

function _mapBorrowRecordRow(array $r): array {
    return array_merge($r, ['id' => (int)$r['id'], 'user_id' => (int)$r['user_id'], 'inventory_id' => (int)$r['inventory_id'], 'request_id' => $r['request_id'] !== null ? (int)$r['request_id'] : null]);
}

function getBorrowRecords(): array {
    return _dbCache('borrow_records', function () {
        $rows = supabase()->select('borrow_records', 'order=id.asc');
        return array_map('_mapBorrowRecordRow', $rows);
    });
}

// ── User Owned Items ──────────────────────────────────────────────────────────

function _mapUserOwnedItemRow(array $r): array {
    // campus_id is no longer relied on here either (college_id is the source of
    // truth for ownership) — coalesce to 1 in case the column is absent/null.
    return array_merge($r, ['id' => (int)$r['id'], 'user_id' => (int)$r['user_id'], 'campus_id' => (int)($r['campus_id'] ?? 1), 'quantity' => (int)$r['quantity'], 'year_owned' => $r['year_owned'] !== null ? (int)$r['year_owned'] : null]);
}

function getUserOwnedItems(): array {
    return _dbCache('user_owned_items', function () {
        $rows = supabase()->select('user_owned_items', 'order=id.asc');
        return array_map('_mapUserOwnedItemRow', $rows);
    });
}

// ── Parallel cache warm-up ────────────────────────────────────────────────────
// Call once near the top of a page that's about to call several of the
// whole-table getters above (the dashboard and reports pages each call ~8).
// Fires the still-stale ones concurrently via cURL multi instead of paying
// N sequential Supabase round-trips, then primes the cache so the individual
// getX() calls that follow are in-memory hits.
function warmSharedCache(array $tables = ['users', 'inventory', 'requests', 'user_owned_items', 'borrow_records', 'departments_college', 'departments_office', 'departments_campus']): void {
    $specs = [
        'users'               => ['users', 'order=id.asc'],
        'inventory'           => ['inventory', 'order=id.asc'],
        'requests'            => ['requests', 'order=id.asc'],
        'user_owned_items'    => ['user_owned_items', 'order=id.asc'],
        'borrow_records'      => ['borrow_records', 'order=id.asc'],
        'departments_college' => ['departments', 'type=eq.college&order=abbreviation.asc'],
        'departments_office'  => ['departments', 'type=eq.office&order=abbreviation.asc'],
        'departments_campus'  => ['departments', 'type=eq.campus&order=full_name.asc'],
    ];

    $mem =& _dbCacheMem();
    $queries = [];
    foreach ($tables as $key) {
        if (!isset($specs[$key])) continue;
        if (array_key_exists($key, $mem)) continue;      // already warm this request
        if (_dbCacheReadFresh($key, $mem)) continue;      // fresh file cache — no need to refetch
        $queries[$key] = $specs[$key];
    }

    if (empty($queries)) return;

    $results = supabase()->selectBatch($queries);

    $mappers = [
        'users'            => fn($rows) => array_map('_mapUserRow', $rows),
        'inventory'        => fn($rows) => array_map('_mapInventoryRow', $rows),
        'requests'         => fn($rows) => array_map('_mapRequestRow', $rows),
        'user_owned_items' => fn($rows) => array_map('_mapUserOwnedItemRow', $rows),
        'borrow_records'   => fn($rows) => array_map('_mapBorrowRecordRow', $rows),
        'departments_college' => '_mapDepartmentsByType',
        'departments_office'  => '_mapDepartmentsByType',
        'departments_campus'  => fn($rows) => array_map('_mapCampusRow', $rows),
    ];

    foreach ($results as $key => $rows) {
        $data = isset($mappers[$key]) ? $mappers[$key]($rows) : $rows;
        _dbCacheSet($key, $data);
    }
}

// ── App Settings ──────────────────────────────────────────────────────────────
// Small global key/value store — currently just the terminology switch (see
// termLabel() in config/functions.php). Cached the same way every other list
// here is; setAppSetting() clears it immediately so the change is instant.

function getAppSettings(): array {
    return _dbCache('app_settings', function () {
        $rows = supabase()->select('app_settings');
        $out = [];
        foreach ($rows as $r) $out[$r['key']] = $r['value'];
        return $out;
    });
}

function getAppSetting(string $key, string $default = ''): string {
    return getAppSettings()[$key] ?? $default;
}

function setAppSetting(string $key, string $value): bool {
    // Upsert — PostgREST has no native "insert or update" without a unique
    // constraint conflict clause, and this table intentionally isn't exposed
    // through a Prefer:resolution=merge-duplicates insert, so just check
    // first; this is an infrequent, admin-only write, not a hot path.
    $existing = supabase()->select('app_settings', 'key=eq.' . urlencode($key));
    if (!empty($existing)) {
        supabase()->update('app_settings', 'key=eq.' . urlencode($key), ['value' => $value, 'updated_at' => date('Y-m-d H:i:s')]);
    } else {
        supabase()->insert('app_settings', ['key' => $key, 'value' => $value]);
    }
    clearDataCache('app_settings');
    return true;
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
