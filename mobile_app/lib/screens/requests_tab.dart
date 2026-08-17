import 'package:flutter/material.dart';
import '../services/api_client.dart';
import '../theme.dart';

/// The "Pending Scan" / "Completed" sub-tabs, extracted from what used to be
/// the whole home screen — now just one tab within the bottom navigation.
class RequestsTab extends StatelessWidget {
  final Future<List<DeliveryItem>> pendingFuture;
  final Future<List<DeliveryItem>> completedFuture;
  final Future<void> Function() onRefresh;
  final void Function(DeliveryItem item) onScanItem;

  const RequestsTab({
    super.key,
    required this.pendingFuture,
    required this.completedFuture,
    required this.onRefresh,
    required this.onScanItem,
  });

  @override
  Widget build(BuildContext context) {
    return DefaultTabController(
      length: 2,
      child: Column(
        children: [
          Container(
            decoration: const BoxDecoration(
              color: Colors.white,
              border: Border(bottom: BorderSide(color: AppColors.border)),
            ),
            child: const TabBar(
              indicatorColor: AppColors.primary,
              indicatorWeight: 3,
              labelColor: AppColors.primary,
              unselectedLabelColor: AppColors.inkMuted,
              tabs: [
                Tab(icon: Icon(Icons.qr_code_scanner), text: 'Pending Scan'),
                Tab(icon: Icon(Icons.check_circle_outline), text: 'Completed'),
              ],
            ),
          ),
          Expanded(
            child: TabBarView(
              children: [
                _DeliveryList(
                  future: pendingFuture,
                  onRefresh: onRefresh,
                  completed: false,
                  onTap: onScanItem,
                  emptyIcon: Icons.inventory_2_outlined,
                  emptyTitle: 'No deliveries waiting for confirmation',
                  emptySubtitle: 'Pull down to refresh — items you can scan will show up here once out for delivery.',
                ),
                _DeliveryList(
                  future: completedFuture,
                  onRefresh: onRefresh,
                  completed: true,
                  onTap: null,
                  emptyIcon: Icons.history,
                  emptyTitle: 'No confirmed deliveries yet',
                  emptySubtitle: 'Items you\'ve scanned and confirmed will show up here.',
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

class _DeliveryList extends StatelessWidget {
  final Future<List<DeliveryItem>> future;
  final Future<void> Function() onRefresh;
  final bool completed;
  final void Function(DeliveryItem item)? onTap;
  final IconData emptyIcon;
  final String emptyTitle;
  final String emptySubtitle;

  const _DeliveryList({
    required this.future,
    required this.onRefresh,
    required this.completed,
    required this.onTap,
    required this.emptyIcon,
    required this.emptyTitle,
    required this.emptySubtitle,
  });

  @override
  Widget build(BuildContext context) {
    return RefreshIndicator(
      onRefresh: onRefresh,
      child: FutureBuilder<List<DeliveryItem>>(
        future: future,
        builder: (context, snapshot) {
          if (snapshot.connectionState == ConnectionState.waiting) {
            return const Center(child: CircularProgressIndicator());
          }
          if (snapshot.hasError) {
            return ListView(
              children: [
                const SizedBox(height: 80),
                Icon(Icons.error_outline, size: 48, color: Colors.red.withValues(alpha: 0.6)),
                const SizedBox(height: 12),
                Padding(
                  padding: const EdgeInsets.symmetric(horizontal: 24),
                  child: Text(
                    snapshot.error.toString().replaceFirst('ApiException: ', ''),
                    textAlign: TextAlign.center,
                  ),
                ),
              ],
            );
          }
          final deliveries = snapshot.data ?? [];
          if (deliveries.isEmpty) {
            return ListView(
              children: [
                const SizedBox(height: 100),
                Icon(emptyIcon, size: 56, color: Colors.black.withValues(alpha: 0.25)),
                const SizedBox(height: 12),
                Text(
                  emptyTitle,
                  textAlign: TextAlign.center,
                  style: const TextStyle(fontWeight: FontWeight.w600, color: Colors.black54),
                ),
                const SizedBox(height: 4),
                Padding(
                  padding: const EdgeInsets.symmetric(horizontal: 32),
                  child: Text(
                    emptySubtitle,
                    textAlign: TextAlign.center,
                    style: const TextStyle(fontSize: 12, color: Colors.black38),
                  ),
                ),
              ],
            );
          }
          final accent = completed ? AppColors.success : AppColors.primary;
          final icon = completed ? Icons.check_circle_outline : Icons.local_shipping_outlined;
          return ListView.separated(
            padding: const EdgeInsets.all(16),
            itemCount: deliveries.length,
            separatorBuilder: (context, index) => const SizedBox(height: 10),
            itemBuilder: (context, i) {
              final d = deliveries[i];
              final tappable = onTap != null;
              return Material(
                color: Colors.white,
                borderRadius: BorderRadius.circular(12),
                child: InkWell(
                  borderRadius: BorderRadius.circular(12),
                  onTap: tappable ? () => onTap!(d) : null,
                  child: Container(
                    padding: const EdgeInsets.all(14),
                    decoration: BoxDecoration(
                      borderRadius: BorderRadius.circular(12),
                      border: Border.all(color: AppColors.border),
                    ),
                    child: Row(
                      children: [
                        Container(
                          width: 40, height: 40,
                          alignment: Alignment.center,
                          decoration: BoxDecoration(
                            color: accent.withValues(alpha: 0.1),
                            borderRadius: BorderRadius.circular(9),
                          ),
                          child: Icon(icon, color: accent),
                        ),
                        const SizedBox(width: 12),
                        Expanded(
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Text(d.itemLabel, style: const TextStyle(fontWeight: FontWeight.w700)),
                              const SizedBox(height: 2),
                              Text(
                                '${d.requestNumber} · ${d.unitCount} unit(s)',
                                style: const TextStyle(fontSize: 12, color: Colors.black45),
                              ),
                            ],
                          ),
                        ),
                        if (completed)
                          const Icon(Icons.check_circle, color: AppColors.success, size: 20)
                        else if (tappable)
                          Icon(Icons.qr_code_scanner, color: Colors.black.withValues(alpha: 0.3), size: 20),
                      ],
                    ),
                  ),
                ),
              );
            },
          );
        },
      ),
    );
  }
}
