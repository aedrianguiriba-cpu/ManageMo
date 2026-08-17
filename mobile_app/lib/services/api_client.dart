import 'dart:convert';
import 'package:bcrypt/bcrypt.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'supabase_rest.dart';

/// Thrown for any login/lookup/confirmation failure surfaced to the UI.
class ApiException implements Exception {
  final String message;
  ApiException(this.message);
  @override
  String toString() => message;
}

class DeliveryItem {
  final String requestNumber;
  final String? groupId;
  final String? qrCodeId;
  final List<String> itemNames;
  final int unitCount;
  final String createdAt;
  /// Stable key identifying this request/group — either `group_id` or
  /// `id:<request id>` for ungrouped requests. Used to make sure the QR the
  /// user scans actually belongs to the item they selected beforehand.
  final String groupKey;

  DeliveryItem({
    required this.requestNumber,
    required this.groupId,
    required this.qrCodeId,
    required this.itemNames,
    required this.unitCount,
    required this.createdAt,
    required this.groupKey,
  });

  String get itemLabel => itemNames.isEmpty ? 'Item' : itemNames.join(', ');
}

class AppUser {
  final int id;
  final String fullName;
  final String email;
  final String role;
  final int? campusId;

  AppUser({
    required this.id,
    required this.fullName,
    required this.email,
    required this.role,
    required this.campusId,
  });

  Map<String, dynamic> toJson() => {
        'id': id,
        'full_name': fullName,
        'email': email,
        'role': role,
        'campus_id': campusId,
      };

  factory AppUser.fromJson(Map<String, dynamic> json) => AppUser(
        id: json['id'] is int ? json['id'] as int : int.parse(json['id'].toString()),
        fullName: json['full_name'] as String,
        email: json['email'] as String,
        role: json['role'] as String,
        campusId: json['campus_id'] == null ? null : int.tryParse(json['campus_id'].toString()),
      );
}

/// Talks directly to Supabase (PostgREST) — see supabase_config.dart for the
/// security tradeoffs of embedding the service_role key in this app.
class ApiClient {
  static const _kUserKey = 'saved_user';

  static Future<AppUser?> getSavedUser() async {
    final prefs = await SharedPreferences.getInstance();
    final raw = prefs.getString(_kUserKey);
    if (raw == null) return null;
    try {
      return AppUser.fromJson(jsonDecode(raw) as Map<String, dynamic>);
    } catch (_) {
      return null;
    }
  }

  static Future<void> _saveUser(AppUser user) async {
    final prefs = await SharedPreferences.getInstance();
    await prefs.setString(_kUserKey, jsonEncode(user.toJson()));
  }

  static Future<void> logout() async {
    final prefs = await SharedPreferences.getInstance();
    await prefs.remove(_kUserKey);
  }

  Future<AppUser> login(String email, String password) async {
    List<Map<String, dynamic>> rows;
    try {
      rows = await SupabaseRest.select('users', "email=eq.${Uri.encodeComponent(email)}");
    } catch (e) {
      throw ApiException('Could not reach the database. Please check your connection.');
    }

    final row = rows.where((u) => (u['is_active'] == true || u['is_active'] == 1)).firstOrNull;
    if (row == null) {
      throw ApiException('Invalid email or password.');
    }

    final hash = row['password']?.toString() ?? '';
    if (hash.isEmpty || !BCrypt.checkpw(password, hash)) {
      throw ApiException('Invalid email or password.');
    }

    final user = AppUser(
      id: row['id'] is int ? row['id'] as int : int.parse(row['id'].toString()),
      fullName: row['full_name'] as String,
      email: row['email'] as String,
      role: row['role'] as String,
      campusId: row['campus_id'] == null ? null : int.tryParse(row['campus_id'].toString()),
    );
    await _saveUser(user);
    return user;
  }

  /// Requests still waiting to be scanned (out for delivery, not yet confirmed).
  /// Note: with per-unit confirmation, a group only disappears from this list
  /// once every unit in it has been scanned individually.
  Future<List<DeliveryItem>> pendingDeliveries(AppUser user) async {
    final requests = await SupabaseRest.select(
      'requests',
      'user_id=eq.${user.id}&delivery_status=eq.out_for_delivery',
    );
    return _groupRequests(requests);
  }

  /// Requests whose delivery has already been confirmed.
  Future<List<DeliveryItem>> completedDeliveries(AppUser user) async {
    final requests = await SupabaseRest.select(
      'requests',
      'user_id=eq.${user.id}&delivery_status=eq.delivered&order=updated_at.desc',
    );
    return _groupRequests(requests);
  }

  Future<List<DeliveryItem>> _groupRequests(List<Map<String, dynamic>> requests) async {
    if (requests.isEmpty) return [];

    final invIds = requests.map((r) => r['inventory_id']).whereType<Object>().toSet();
    final inventoryById = <String, Map<String, dynamic>>{};
    if (invIds.isNotEmpty) {
      final idList = invIds.map((e) => e.toString()).join(',');
      final invRows = await SupabaseRest.select('inventory', 'id=in.($idList)');
      for (final inv in invRows) {
        inventoryById[inv['id'].toString()] = inv;
      }
    }

    // Group by group_id (or request id if ungrouped), same shape as the web app.
    final groups = <String, List<Map<String, dynamic>>>{};
    for (final r in requests) {
      final key = (r['group_id'] as String?)?.isNotEmpty == true ? r['group_id'] as String : 'id:${r['id']}';
      groups.putIfAbsent(key, () => []).add(r);
    }

    final out = <DeliveryItem>[];
    for (final entry in groups.entries) {
      final rows = entry.value;
      final first = rows.first;
      final names = <String>{};
      for (final r in rows) {
        final inv = r['inventory_id'] != null ? inventoryById[r['inventory_id'].toString()] : null;
        names.add((inv?['item_name'] as String?) ?? (r['service_description'] as String?) ?? 'Item');
      }
      out.add(DeliveryItem(
        requestNumber: first['request_number'] as String,
        groupId: first['group_id'] as String?,
        qrCodeId: first['qr_code_id'] as String?,
        itemNames: names.toList(),
        unitCount: rows.length,
        createdAt: first['created_at'] as String,
        groupKey: entry.key,
      ));
    }
    out.sort((a, b) => b.createdAt.compareTo(a.createdAt));
    return out;
  }

  /// Confirms delivery of the single unit matching [qrCodeId] — scoped to [user].
  ///
  /// Multi-unit requests (same group_id) are confirmed one QR at a time: each
  /// scan only marks its own unit delivered, not the whole group. The result
  /// reports how many units in the group are still outstanding so the scanner
  /// screen can prompt the user to keep going.
  ///
  /// If [expectedGroupKey] is given (the user picked a specific item before
  /// scanning), a QR belonging to a different request is rejected instead of
  /// silently confirming the wrong item.
  Future<ConfirmResult> confirmDelivery(
    AppUser user,
    String qrCodeId, {
    String? expectedGroupKey,
  }) async {
    final matches = await SupabaseRest.select('requests', 'qr_code_id=eq.${Uri.encodeComponent(qrCodeId)}');
    if (matches.isEmpty) {
      throw ApiException('This QR code does not match any request.');
    }
    final match = matches.first;

    if ((match['user_id'] as num).toInt() != user.id) {
      throw ApiException('This item was not requested by you.');
    }
    if (match['delivery_status'] == 'delivered') {
      throw ApiException('This item has already been confirmed.');
    }
    if (match['delivery_status'] != 'out_for_delivery') {
      throw ApiException('This item is not out for delivery yet.');
    }

    if (expectedGroupKey != null) {
      final matchGroupId = match['group_id'] as String?;
      final matchGroupKey =
          (matchGroupId != null && matchGroupId.isNotEmpty) ? matchGroupId : 'id:${match['id']}';
      if (matchGroupKey != expectedGroupKey) {
        throw ApiException('This QR code belongs to a different item. Scan a QR code for the item you selected.');
      }
    }

    final id = (match['id'] as num).toInt();
    final nowIso = DateTime.now().toUtc().toIso8601String();

    await SupabaseRest.updateById('requests', id, {
      'delivery_status': 'delivered',
      'status': 'delivered',
      'updated_at': nowIso,
    });

    final invId = match['inventory_id'];
    String itemName = 'Item';
    if (invId != null) {
      final invIdInt = (invId as num).toInt();
      if (match['request_type'] == 'borrow') {
        await SupabaseRest.updateById('inventory', invIdInt, {'status': 'borrowed'});
        await SupabaseRest.insert('borrow_records', {
          'user_id': user.id,
          'inventory_id': invIdInt,
          'request_id': id,
          'borrow_date': DateTime.now().toUtc().toIso8601String().split('T').first,
          'expected_return_date': match['expected_return_date'],
          'status': 'active',
          'notes': match['reason_for_request'],
        });
      }
      final invRows = await SupabaseRest.select('inventory', 'id=eq.$invIdInt');
      if (invRows.isNotEmpty) itemName = invRows.first['item_name'] as String? ?? 'Item';
    } else if (match['service_description'] != null) {
      itemName = match['service_description'] as String;
    }

    // How many units of this same group are still outstanding?
    final groupId = match['group_id'] as String?;
    int remaining = 0;
    int total = 1;
    if (groupId != null && groupId.isNotEmpty) {
      final groupReqs = await SupabaseRest.select('requests', 'group_id=eq.${Uri.encodeComponent(groupId)}');
      total = groupReqs.length;
      remaining = groupReqs.where((r) => r['delivery_status'] != 'delivered').length;
    }

    return ConfirmResult(
      requestNumber: match['request_number'] as String,
      itemName: itemName,
      remainingInGroup: remaining,
      totalInGroup: total,
    );
  }
}

class ConfirmResult {
  final String requestNumber;
  final String itemName;
  final int remainingInGroup;
  final int totalInGroup;

  ConfirmResult({
    required this.requestNumber,
    required this.itemName,
    required this.remainingInGroup,
    required this.totalInGroup,
  });

  bool get isGroupComplete => remainingInGroup == 0;

  String get message {
    if (totalInGroup <= 1) {
      return 'Delivery confirmed for $requestNumber ($itemName).';
    }
    final confirmedCount = totalInGroup - remainingInGroup;
    return isGroupComplete
        ? 'All $totalInGroup units confirmed for $requestNumber. Last item: $itemName.'
        : '$itemName confirmed ($confirmedCount of $totalInGroup). $remainingInGroup unit(s) left — scan the next QR code.';
  }
}

extension _FirstOrNullExt<T> on Iterable<T> {
  T? get firstOrNull => isEmpty ? null : first;
}
