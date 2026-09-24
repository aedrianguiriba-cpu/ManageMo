import 'dart:convert';
import 'package:http/http.dart' as http;
import 'supabase_config.dart';

/// Thin PostgREST wrapper, mirroring config/supabase.php's SupabaseClient.
class SupabaseRest {
  static final Uri _base = Uri.parse('$supabaseUrl/rest/v1');

  static Map<String, String> get _headers => {
        'apikey': supabaseServiceKey,
        'Authorization': 'Bearer $supabaseServiceKey',
        'Content-Type': 'application/json',
        'Accept': 'application/json',
      };

  static Uri _uri(String table, [String query = '']) {
    final q = query.isEmpty ? 'select=*' : (query.contains('select=') ? query : 'select=*&$query');
    return Uri.parse('$_base/$table?$q');
  }

  /// SELECT — returns rows matching the given PostgREST query string (e.g. "id=eq.5").
  static Future<List<Map<String, dynamic>>> select(String table, [String query = '']) async {
    final resp = await http.get(_uri(table, query), headers: _headers).timeout(const Duration(seconds: 15));
    _checkOk(resp);
    final decoded = jsonDecode(resp.body);
    return (decoded as List).cast<Map<String, dynamic>>();
  }

  static Future<List<Map<String, dynamic>>> insert(String table, Map<String, dynamic> data) async {
    final resp = await http
        .post(
          Uri.parse('$_base/$table'),
          headers: {..._headers, 'Prefer': 'return=representation'},
          body: jsonEncode(data),
        )
        .timeout(const Duration(seconds: 15));
    _checkOk(resp);
    final decoded = jsonDecode(resp.body);
    return (decoded as List).cast<Map<String, dynamic>>();
  }

  static Future<void> updateById(String table, int id, Map<String, dynamic> data) async {
    final resp = await http
        .patch(
          Uri.parse('$_base/$table?id=eq.$id'),
          headers: {..._headers, 'Prefer': 'return=representation'},
          body: jsonEncode(data),
        )
        .timeout(const Duration(seconds: 15));
    _checkOk(resp);
  }

  static void _checkOk(http.Response resp) {
    if (resp.statusCode >= 400) {
      String msg = 'HTTP ${resp.statusCode}';
      try {
        final decoded = jsonDecode(resp.body);
        msg = decoded['message'] ?? decoded['hint'] ?? msg;
      } catch (_) {}
      throw Exception('Supabase error: $msg');
    }
  }
}
