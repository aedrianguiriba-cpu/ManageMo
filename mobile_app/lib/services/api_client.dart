import 'dart:async';
import 'dart:convert';
import 'package:bcrypt/bcrypt.dart';
import 'package:http/http.dart' as http;
import 'package:shared_preferences/shared_preferences.dart';
import 'app_config.dart';
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
  final String? collegeId;
  final String? phone;

  AppUser({
    required this.id,
    required this.fullName,
    required this.email,
    required this.role,
    required this.campusId,
    this.collegeId,
    this.phone,
  });

  Map<String, dynamic> toJson() => {
        'id': id,
        'full_name': fullName,
        'email': email,
        'role': role,
        'campus_id': campusId,
        'college_id': collegeId,
        'phone': phone,
      };

  factory AppUser.fromJson(Map<String, dynamic> json) => AppUser(
        id: json['id'] is int ? json['id'] as int : int.parse(json['id'].toString()),
        fullName: json['full_name'] as String,
        email: json['email'] as String,
        role: json['role'] as String,
        campusId: json['campus_id'] == null ? null : int.tryParse(json['campus_id'].toString()),
        collegeId: json['college_id'] as String?,
        phone: json['phone'] as String?,
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
    AppUser user;
    try {
      user = AppUser.fromJson(jsonDecode(raw) as Map<String, dynamic>);
    } catch (_) {
      return null;
    }
    // A session saved before this admin-block existed (or restored on a
    // device that shares storage) shouldn't stay signed in — same rule as
    // login() now enforces up front.
    if (user.role != 'user') {
      await prefs.remove(_kUserKey);
      return null;
    }
    // Re-fetch the live row so fields added to AppUser after this session
    // was first cached (college_id, phone) — or any edit an admin made since
    // — actually show up, instead of the account looking stuck on whatever
    // was true (or simply absent) at the last login. Falls back to the
    // cached copy if offline rather than failing the whole restore.
    try {
      final rows = await SupabaseRest.select('users', 'id=eq.${user.id}');
      final row = rows.firstOrNull;
      if (row != null) {
        final refreshed = AppUser(
          id: user.id,
          fullName: row['full_name'] as String? ?? user.fullName,
          email: row['email'] as String? ?? user.email,
          role: row['role'] as String? ?? user.role,
          campusId: row['campus_id'] == null ? null : int.tryParse(row['campus_id'].toString()),
          collegeId: row['college_id'] as String?,
          phone: row['phone'] as String?,
        );
        await _saveUser(refreshed);
        return refreshed;
      }
    } catch (_) {
      // Offline or request failed — keep going with the cached copy below.
    }
    return user;
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

    // Admin accounts belong on the web dashboard, not the mobile scanner app —
    // checked after the password so a wrong password on an admin account still
    // reports "Invalid email or password," not "you're an admin" (no account
    // enumeration via role).
    if (row['role'] != 'user') {
      throw ApiException('Admin accounts can\'t log in here — please use the web dashboard instead.');
    }

    final user = AppUser(
      id: row['id'] is int ? row['id'] as int : int.parse(row['id'].toString()),
      fullName: row['full_name'] as String,
      email: row['email'] as String,
      role: row['role'] as String,
      campusId: row['campus_id'] == null ? null : int.tryParse(row['campus_id'].toString()),
      collegeId: row['college_id'] as String?,
      phone: row['phone'] as String?,
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
    // A QR sticker is tied to the physical inventory unit, not to one request —
    // the same unit gets the exact same code again the next time it's
    // borrowed. A plain "qr_code_id=eq.X" lookup with no ordering can match
    // ANY request row that ever used this code, including an old, already-
    // completed one from a previous borrow cycle — which wrongly reports
    // "already confirmed" for a unit that was never actually scanned this
    // time around. Only ever match the request that's actually still out for
    // delivery right now; there can be at most one, since a unit can't be
    // dispatched on two deliveries at once.
    final activeMatches = await SupabaseRest.select(
      'requests',
      'qr_code_id=eq.${Uri.encodeComponent(qrCodeId)}&delivery_status=eq.out_for_delivery',
    );

    final Map<String, dynamic> match;
    if (activeMatches.isNotEmpty) {
      match = activeMatches.first;
    } else {
      // Nothing currently out for delivery has this code — look up its most
      // recent request (any status) just to give an accurate reason why.
      final anyMatches = await SupabaseRest.select(
        'requests',
        'qr_code_id=eq.${Uri.encodeComponent(qrCodeId)}&order=updated_at.desc&limit=1',
      );
      if (anyMatches.isEmpty) {
        throw ApiException('This QR code does not match any request.');
      }
      final latest = anyMatches.first;
      if ((latest['user_id'] as num).toInt() != user.id) {
        throw ApiException('This item was not requested by you.');
      }
      if (latest['delivery_status'] == 'delivered') {
        throw ApiException('This item has already been confirmed.');
      }
      throw ApiException('This item is not out for delivery yet.');
    }

    if ((match['user_id'] as num).toInt() != user.id) {
      throw ApiException('This item was not requested by you.');
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

    // Bell notification — same table/shape the web app writes to directly.
    final reqNumberForNotif = (match['group_id'] as String?)?.isNotEmpty == true
        ? match['group_id'] as String
        : match['request_number'] as String;
    try {
      await SupabaseRest.insert('notifications', {
        'user_id': user.id,
        'title': 'Item delivered',
        'message': 'Request ($reqNumberForNotif) has been marked as delivered.',
        'type': 'success',
        'link': 'user/my-requests.php',
      });
    } catch (_) {
      // Non-fatal — the delivery itself already succeeded above.
    }

    // Ask the web backend to send the "delivered" email — the only step here
    // that still needs the PHP/SMTP side, since Dart can't reliably send
    // real email on its own. Fire-and-forget: never blocks or fails the
    // delivery confirmation itself if the server is unreachable.
    unawaited(_notifyServerOfDelivery(id));

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

  Future<void> _notifyServerOfDelivery(int requestId) async {
    try {
      await http
          .post(
            Uri.parse('$serverBaseUrl/api/notify_delivered.php'),
            headers: {'Content-Type': 'application/json'},
            body: jsonEncode({'request_id': requestId}),
          )
          .timeout(const Duration(seconds: 8));
    } catch (_) {
      // Best-effort only — the delivery confirmation itself already succeeded.
    }
  }

  /// Looks up full details for a physical item by its QR code — a read-only
  /// lookup ("what is this, and what's its status") for the item scanner's
  /// check-details mode, never a delivery confirmation and never a write.
  ///
  /// Scoped to things actually belonging to [user]: an item they permanently
  /// own, a unit they currently have borrowed, or (failing those) an active
  /// request of theirs still moving through approval/delivery for it. A QR
  /// that isn't tied to this user in any of those ways is refused outright —
  /// otherwise anyone could scan a sticker lying around and see whichever
  /// other user's borrow/ownership details it carries.
  Future<ItemLookupResult> lookupItemDetails(AppUser user, String qrCodeId) async {
    final qr = qrCodeId.trim();
    if (qr.isEmpty) throw ApiException('That QR code could not be read.');

    // 1) Permanently owned by this user (acquired/custom items).
    final ownedRows = await SupabaseRest.select(
      'user_owned_items',
      'qr_code_id=eq.${Uri.encodeComponent(qr)}&user_id=eq.${user.id}',
    );
    final owned = ownedRows.firstOrNull;
    if (owned != null) {
      return ItemLookupResult(
        qrCodeId: qr,
        itemName: owned['item_name'] as String? ?? 'Item',
        category: owned['category'] as String?,
        condition: owned['condition'] as String?,
        description: owned['description'] as String?,
        ownershipLabel: 'Owned by you',
        statusLabel: 'Owned',
        statusColorKey: 'owned',
        details: {
          if (owned['year_owned'] != null) 'Year Owned': '${owned['year_owned']}',
          if (_notBlank(owned['notes'])) 'Notes': owned['notes'] as String,
        },
      );
    }

    // Everything else is keyed off the physical inventory unit itself.
    final invRows = await SupabaseRest.select('inventory', 'qr_code_id=eq.${Uri.encodeComponent(qr)}');
    final inv = invRows.firstOrNull;
    if (inv == null) {
      throw ApiException('This QR code does not match any item.');
    }
    final invId = (inv['id'] as num).toInt();

    // 2) A unit currently borrowed by this user.
    final borrowRows = await SupabaseRest.select(
      'borrow_records',
      'inventory_id=eq.$invId&user_id=eq.${user.id}&order=id.desc&limit=1',
    );
    final borrow = borrowRows.firstOrNull;
    if (borrow != null && (borrow['status'] == 'active' || borrow['status'] == 'overdue')) {
      final overdue = borrow['status'] == 'overdue';
      return ItemLookupResult(
        qrCodeId: qr,
        itemName: inv['item_name'] as String? ?? 'Item',
        category: inv['category'] as String?,
        condition: inv['condition'] as String?,
        description: inv['description'] as String?,
        ownershipLabel: 'Borrowed by you',
        statusLabel: overdue ? 'Overdue' : 'Borrowed',
        statusColorKey: overdue ? 'overdue' : 'borrowed',
        details: {
          'Location': (inv['location'] as String?)?.isNotEmpty == true ? inv['location'] as String : '—',
          if (_notBlank(inv['model'])) 'Model': inv['model'] as String,
          if (_notBlank(inv['serial_number'])) 'Serial No.': inv['serial_number'] as String,
          if (borrow['borrow_date'] != null) 'Borrowed On': _fmtDate(borrow['borrow_date'] as String?),
          if (borrow['expected_return_date'] != null) 'Expected Return': _fmtDate(borrow['expected_return_date'] as String?),
        },
      );
    }

    // 3) Not currently borrowed by this user, but maybe they have a request
    // for it still working its way through approval/delivery.
    final reqRows = await SupabaseRest.select(
      'requests',
      'inventory_id=eq.$invId&user_id=eq.${user.id}&order=id.desc&limit=1',
    );
    final req = reqRows.firstOrNull;
    if (req != null && !['disapproved', 'completed'].contains(req['status'])) {
      return ItemLookupResult(
        qrCodeId: qr,
        itemName: inv['item_name'] as String? ?? 'Item',
        category: inv['category'] as String?,
        condition: inv['condition'] as String?,
        description: inv['description'] as String?,
        ownershipLabel: 'Your request',
        statusLabel: _requestStatusLabel(req['status'] as String?, req['delivery_status'] as String?),
        statusColorKey: 'pending',
        details: {
          'Request Number': req['request_number'] as String? ?? '—',
          'Location': (inv['location'] as String?)?.isNotEmpty == true ? inv['location'] as String : '—',
        },
      );
    }

    throw ApiException("This item isn't linked to your account — nothing to show.");
  }

  bool _notBlank(dynamic v) => v is String && v.trim().isNotEmpty;

  String _requestStatusLabel(String? status, String? deliveryStatus) {
    if (status == 'pending') return 'Pending Approval';
    if (deliveryStatus == 'out_for_delivery') return 'Out for Delivery';
    if (deliveryStatus == 'delivered' || status == 'delivered') return 'Delivered';
    if (status == 'approved') return 'Approved';
    return status == null ? 'Pending' : status[0].toUpperCase() + status.substring(1);
  }

  static const _months = [
    'Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec',
  ];

  String _fmtDate(String? isoDate) {
    if (isoDate == null || isoDate.isEmpty) return '—';
    try {
      final d = DateTime.parse(isoDate);
      return '${_months[d.month - 1]} ${d.day}, ${d.year}';
    } catch (_) {
      return isoDate;
    }
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

/// Result of a read-only "check item details" scan — see
/// ApiClient.lookupItemDetails(). [statusColorKey] is one of 'owned',
/// 'borrowed', 'overdue', or 'pending', for the details screen to color.
class ItemLookupResult {
  final String qrCodeId;
  final String itemName;
  final String? category;
  final String? condition;
  final String? description;
  final String ownershipLabel;
  final String statusLabel;
  final String statusColorKey;
  /// Extra label/value rows shown below the main details, in order.
  final Map<String, String> details;

  ItemLookupResult({
    required this.qrCodeId,
    required this.itemName,
    required this.category,
    required this.condition,
    required this.description,
    required this.ownershipLabel,
    required this.statusLabel,
    required this.statusColorKey,
    this.details = const {},
  });
}

extension _FirstOrNullExt<T> on Iterable<T> {
  T? get firstOrNull => isEmpty ? null : first;
}
