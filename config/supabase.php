<?php
/**
 * Supabase REST API Client
 * Thin HTTP wrapper around Supabase PostgREST.
 */

// Load .env from project root if not already in environment
(function () {
    $envFile = dirname(__DIR__) . '/.env';
    if (!file_exists($envFile)) return;
    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        if ($line[0] === '#' || !str_contains($line, '=')) continue;
        [$k, $v] = explode('=', $line, 2);
        $k = trim($k); $v = trim($v);
        if (!isset($_ENV[$k]) && getenv($k) === false) {
            putenv("$k=$v");
            $_ENV[$k] = $v;
        }
    }
})();

define('SUPABASE_URL', getenv('SUPABASE_URL') ?: $_ENV['SUPABASE_URL'] ?? '');
define('SUPABASE_KEY', getenv('SUPABASE_KEY') ?: $_ENV['SUPABASE_KEY'] ?? '');

class SupabaseClient {

    private string $base;
    private string $key;

    public function __construct() {
        $this->base = SUPABASE_URL . '/rest/v1';
        $this->key  = SUPABASE_KEY;
    }

    // ── low-level HTTP ────────────────────────────────────────────────────────

    public string $lastError = '';

    private function req(string $method, string $table, string $qs = '', mixed $body = null, array $extra = []): array {
        $this->lastError = '';
        $url = $this->base . '/' . $table . ($qs !== '' ? '?' . $qs : '');
        $ch  = curl_init($url);

        $headers = array_merge([
            'apikey: '        . $this->key,
            'Authorization: Bearer ' . $this->key,
            'Content-Type: application/json',
            'Accept: application/json',
        ], $extra);

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_CUSTOMREQUEST  => $method,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT        => 10,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => 0,
            // Reuses the DNS/SSL-session cache across calls within this request
            // (and across requests while the PHP-FPM/CGI worker stays warm),
            // instead of each sequential Supabase call paying a full handshake.
            CURLOPT_DNS_CACHE_TIMEOUT => 120,
            CURLOPT_FORBID_REUSE      => false,
            CURLOPT_FRESH_CONNECT     => false,
        ]);

        if ($body !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
        }

        $resp = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err  = curl_error($ch);
        curl_close($ch);

        if ($err) {
            $this->lastError = "Connection error: $err";
            error_log("Supabase cURL error [$method $url]: $err");
            return [];
        }
        if ($code >= 400) {
            $decoded = json_decode($resp, true);
            $msg = $decoded['message'] ?? $decoded['hint'] ?? $resp;
            $this->lastError = "HTTP $code: $msg";
            error_log("Supabase HTTP $code [$method $url]: $resp");
            return [];
        }
        if ($resp === '' || $resp === null) return [];

        $decoded = json_decode($resp, true);
        return is_array($decoded) ? $decoded : [];
    }

    /**
     * Run several SELECTs concurrently over one cURL multi handle instead of
     * sequentially — $queries is ['key' => ['table', 'qs']]. Returns
     * ['key' => rows]. Used to warm the cache for pages that need several
     * whole tables at once (e.g. the dashboard), so the N round-trips cost
     * as much wall-clock time as the single slowest one instead of the sum.
     */
    public function selectBatch(array $queries): array {
        if (empty($queries)) return [];

        $headers = [
            'apikey: '        . $this->key,
            'Authorization: Bearer ' . $this->key,
            'Content-Type: application/json',
            'Accept: application/json',
        ];

        $mh      = curl_multi_init();
        $handles = [];

        foreach ($queries as $key => [$table, $qs]) {
            $url = $this->base . '/' . $table . '?select=*' . ($qs !== '' ? '&' . $qs : '');
            $ch  = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER     => $headers,
                CURLOPT_CUSTOMREQUEST  => 'GET',
                CURLOPT_CONNECTTIMEOUT => 5,
                CURLOPT_TIMEOUT        => 10,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_SSL_VERIFYHOST => 0,
            ]);
            curl_multi_add_handle($mh, $ch);
            $handles[$key] = $ch;
        }

        $running = null;
        do {
            $status = curl_multi_exec($mh, $running);
            if ($running) curl_multi_select($mh);
        } while ($running > 0 && $status === CURLM_OK);

        $results = [];
        foreach ($handles as $key => $ch) {
            $resp = curl_multi_getcontent($ch);
            $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $err  = curl_error($ch);

            if ($err) {
                error_log("Supabase batch cURL error [$key]: $err");
                $results[$key] = [];
            } elseif ($code >= 400) {
                error_log("Supabase batch HTTP $code [$key]: $resp");
                $results[$key] = [];
            } elseif ($resp === '' || $resp === null) {
                $results[$key] = [];
            } else {
                $decoded = json_decode($resp, true);
                $results[$key] = is_array($decoded) ? $decoded : [];
            }

            curl_multi_remove_handle($mh, $ch);
            curl_close($ch);
        }
        curl_multi_close($mh);

        return $results;
    }

    // ── public CRUD ───────────────────────────────────────────────────────────

    /** SELECT — returns array of rows */
    public function select(string $table, string $qs = ''): array {
        $q = 'select=*' . ($qs !== '' ? '&' . $qs : '');
        return $this->req('GET', $table, $q);
    }

    /** SELECT with explicit column list */
    public function selectCols(string $table, string $cols, string $qs = ''): array {
        $q = 'select=' . $cols . ($qs !== '' ? '&' . $qs : '');
        return $this->req('GET', $table, $q);
    }

    /** INSERT — returns the inserted row(s) */
    public function insert(string $table, array $data): array {
        return $this->req('POST', $table, '', $data, ['Prefer: return=representation']);
    }

    /** UPDATE matching rows — returns updated row(s) */
    public function update(string $table, string $filter, array $data): array {
        return $this->req('PATCH', $table, $filter, $data, ['Prefer: return=representation']);
    }

    /** DELETE matching rows */
    public function delete(string $table, string $filter): array {
        return $this->req('DELETE', $table, $filter, null, ['Prefer: return=representation']);
    }

    /** UPDATE by primary key */
    public function updateById(string $table, int $id, array $data): array {
        return $this->update($table, 'id=eq.' . $id, $data);
    }

    /** DELETE by primary key */
    public function deleteById(string $table, int $id): array {
        return $this->delete($table, 'id=eq.' . $id);
    }

    /** SELECT a single row by id */
    public function find(string $table, int $id): ?array {
        $rows = $this->select($table, 'id=eq.' . $id);
        return $rows[0] ?? null;
    }

    /** SELECT with eq filter on one column */
    public function where(string $table, string $col, mixed $val): array {
        return $this->select($table, $col . '=eq.' . urlencode((string)$val));
    }
}

// ── Singleton helper ──────────────────────────────────────────────────────────
function supabase(): SupabaseClient {
    static $client = null;
    if ($client === null) $client = new SupabaseClient();
    return $client;
}
