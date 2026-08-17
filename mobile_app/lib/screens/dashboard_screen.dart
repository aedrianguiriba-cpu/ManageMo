import 'package:flutter/material.dart';
import '../services/api_client.dart';
import '../theme.dart';

/// Landing tab — a snapshot of the user's delivery status plus quick access
/// to whatever's waiting on their scan, instead of dropping them straight
/// into a bare list.
class DashboardScreen extends StatelessWidget {
  final AppUser user;
  final Future<List<DeliveryItem>> pendingFuture;
  final Future<List<DeliveryItem>> completedFuture;
  final Future<void> Function() onRefresh;
  final void Function(DeliveryItem item) onScanItem;
  final VoidCallback onViewAllPending;

  const DashboardScreen({
    super.key,
    required this.user,
    required this.pendingFuture,
    required this.completedFuture,
    required this.onRefresh,
    required this.onScanItem,
    required this.onViewAllPending,
  });

  @override
  Widget build(BuildContext context) {
    return RefreshIndicator(
      onRefresh: onRefresh,
      child: FutureBuilder<List<List<DeliveryItem>>>(
        future: Future.wait([pendingFuture, completedFuture]),
        builder: (context, snapshot) {
          final loading = snapshot.connectionState == ConnectionState.waiting;
          final pending = snapshot.data?[0] ?? const <DeliveryItem>[];
          final completed = snapshot.data?[1] ?? const <DeliveryItem>[];

          return ListView(
            padding: const EdgeInsets.all(16),
            children: [
              _WelcomeCard(user: user),
              const SizedBox(height: 16),
              Row(
                children: [
                  Expanded(
                    child: _StatTile(
                      icon: Icons.qr_code_scanner,
                      label: 'Awaiting Scan',
                      value: loading ? null : pending.length,
                      color: AppColors.primary,
                      bg: AppColors.primarySoft,
                    ),
                  ),
                  const SizedBox(width: 12),
                  Expanded(
                    child: _StatTile(
                      icon: Icons.check_circle_outline,
                      label: 'Completed',
                      value: loading ? null : completed.length,
                      color: AppColors.success,
                      bg: AppColors.successSoft,
                    ),
                  ),
                ],
              ),
              const SizedBox(height: 24),
              Row(
                mainAxisAlignment: MainAxisAlignment.spaceBetween,
                children: [
                  const Text('Awaiting Your Scan', style: TextStyle(fontWeight: FontWeight.w800, fontSize: 15, color: AppColors.ink)),
                  if (pending.isNotEmpty)
                    TextButton(
                      onPressed: onViewAllPending,
                      child: const Text('View all', style: TextStyle(fontWeight: FontWeight.w700)),
                    ),
                ],
              ),
              const SizedBox(height: 4),
              if (loading)
                const Padding(padding: EdgeInsets.symmetric(vertical: 32), child: Center(child: CircularProgressIndicator()))
              else if (pending.isEmpty)
                Container(
                  padding: const EdgeInsets.symmetric(vertical: 28),
                  decoration: BoxDecoration(
                    color: Colors.white,
                    borderRadius: BorderRadius.circular(14),
                    border: Border.all(color: AppColors.border),
                  ),
                  child: Column(
                    children: [
                      Icon(Icons.task_alt, size: 40, color: Colors.black.withValues(alpha: 0.2)),
                      const SizedBox(height: 8),
                      const Text("You're all caught up", style: TextStyle(fontWeight: FontWeight.w700, color: Colors.black54)),
                      const SizedBox(height: 2),
                      Text('New deliveries will show up here.', style: TextStyle(fontSize: 12, color: Colors.black.withValues(alpha: 0.4))),
                    ],
                  ),
                )
              else
                ...pending.take(3).map((d) => Padding(
                      padding: const EdgeInsets.only(bottom: 10),
                      child: _PendingCard(item: d, onTap: () => onScanItem(d)),
                    )),
            ],
          );
        },
      ),
    );
  }
}

class _WelcomeCard extends StatelessWidget {
  final AppUser user;
  const _WelcomeCard({required this.user});

  @override
  Widget build(BuildContext context) {
    final firstName = user.fullName.split(' ').first;
    final initials = user.fullName.isNotEmpty ? user.fullName[0].toUpperCase() : '?';
    return Container(
      padding: const EdgeInsets.all(18),
      decoration: BoxDecoration(
        color: AppColors.primary,
        borderRadius: BorderRadius.circular(16),
      ),
      child: Row(
        children: [
          CircleAvatar(
            radius: 24,
            backgroundColor: Colors.white,
            child: Text(initials, style: const TextStyle(color: AppColors.primary, fontWeight: FontWeight.w800, fontSize: 18)),
          ),
          const SizedBox(width: 14),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text('Hi, $firstName', style: const TextStyle(color: Colors.white, fontWeight: FontWeight.w800, fontSize: 18)),
                const SizedBox(height: 2),
                Text(user.email, style: TextStyle(color: Colors.white.withValues(alpha: 0.85), fontSize: 12.5)),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

class _StatTile extends StatelessWidget {
  final IconData icon;
  final String label;
  final int? value;
  final Color color;
  final Color bg;

  const _StatTile({required this.icon, required this.label, required this.value, required this.color, required this.bg});

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(14),
        border: Border.all(color: AppColors.border),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Container(
            width: 34, height: 34,
            alignment: Alignment.center,
            decoration: BoxDecoration(color: bg, borderRadius: BorderRadius.circular(9)),
            child: Icon(icon, color: color, size: 18),
          ),
          const SizedBox(height: 10),
          value == null
              ? const SizedBox(height: 26, width: 26, child: CircularProgressIndicator(strokeWidth: 2))
              : Text('$value', style: const TextStyle(fontSize: 24, fontWeight: FontWeight.w900, color: AppColors.ink)),
          const SizedBox(height: 2),
          Text(label, style: TextStyle(fontSize: 12, fontWeight: FontWeight.w600, color: Colors.black.withValues(alpha: 0.45))),
        ],
      ),
    );
  }
}

class _PendingCard extends StatelessWidget {
  final DeliveryItem item;
  final VoidCallback onTap;
  const _PendingCard({required this.item, required this.onTap});

  @override
  Widget build(BuildContext context) {
    return Material(
      color: Colors.white,
      borderRadius: BorderRadius.circular(12),
      child: InkWell(
        borderRadius: BorderRadius.circular(12),
        onTap: onTap,
        child: Container(
          padding: const EdgeInsets.all(14),
          decoration: BoxDecoration(borderRadius: BorderRadius.circular(12), border: Border.all(color: AppColors.border)),
          child: Row(
            children: [
              Container(
                width: 38, height: 38,
                alignment: Alignment.center,
                decoration: BoxDecoration(color: AppColors.primarySoft, borderRadius: BorderRadius.circular(9)),
                child: const Icon(Icons.local_shipping_outlined, color: AppColors.primary, size: 18),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(item.itemLabel, style: const TextStyle(fontWeight: FontWeight.w700, fontSize: 13.5)),
                    const SizedBox(height: 2),
                    Text('${item.requestNumber} · ${item.unitCount} unit(s)', style: const TextStyle(fontSize: 11.5, color: Colors.black45)),
                  ],
                ),
              ),
              const Icon(Icons.qr_code_scanner, color: Colors.black26, size: 18),
            ],
          ),
        ),
      ),
    );
  }
}
