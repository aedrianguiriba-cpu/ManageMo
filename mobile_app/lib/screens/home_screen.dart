import 'package:flutter/material.dart';
import '../services/api_client.dart';
import 'login_screen.dart';
import 'scanner_screen.dart';

class HomeScreen extends StatefulWidget {
  final AppUser user;
  const HomeScreen({super.key, required this.user});

  @override
  State<HomeScreen> createState() => _HomeScreenState();
}

class _HomeScreenState extends State<HomeScreen> {
  final _api = ApiClient();
  late Future<List<DeliveryItem>> _pendingFuture;
  late Future<List<DeliveryItem>> _completedFuture;

  @override
  void initState() {
    super.initState();
    _pendingFuture = _api.pendingDeliveries(widget.user);
    _completedFuture = _api.completedDeliveries(widget.user);
  }

  Future<void> _refresh() async {
    setState(() {
      _pendingFuture = _api.pendingDeliveries(widget.user);
      _completedFuture = _api.completedDeliveries(widget.user);
    });
    await Future.wait([_pendingFuture, _completedFuture]);
  }

  Future<void> _scanItem(DeliveryItem item) async {
    final message = await Navigator.of(context).push<String>(
      MaterialPageRoute(builder: (_) => ScannerScreen(user: widget.user, expectedItem: item)),
    );
    if (!mounted) return;
    if (message != null) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(message), backgroundColor: const Color(0xFF15803D)),
      );
      _refresh();
    }
  }

  Future<void> _logout() async {
    await ApiClient.logout();
    if (!mounted) return;
    Navigator.of(context).pushReplacement(MaterialPageRoute(builder: (_) => const LoginScreen()));
  }

  @override
  Widget build(BuildContext context) {
    return DefaultTabController(
      length: 2,
      child: Scaffold(
        backgroundColor: const Color(0xFFF7F7F7),
        appBar: AppBar(
          backgroundColor: const Color(0xFF8B0000),
          foregroundColor: Colors.white,
          title: Text('Hi, ${widget.user.fullName.split(' ').first}'),
          actions: [
            IconButton(onPressed: _logout, icon: const Icon(Icons.logout), tooltip: 'Log out'),
          ],
          bottom: const TabBar(
            indicatorColor: Colors.white,
            indicatorWeight: 3,
            labelColor: Colors.white,
            unselectedLabelColor: Colors.white70,
            tabs: [
              Tab(icon: Icon(Icons.qr_code_scanner), text: 'Pending Scan'),
              Tab(icon: Icon(Icons.check_circle_outline), text: 'Completed'),
            ],
          ),
        ),
        body: TabBarView(
          children: [
            _DeliveryList(
              future: _pendingFuture,
              onRefresh: _refresh,
              completed: false,
              onTap: _scanItem,
              emptyIcon: Icons.inventory_2_outlined,
              emptyTitle: 'No deliveries waiting for confirmation',
              emptySubtitle: 'Pull down to refresh — items you can scan will show up here once out for delivery.',
            ),
            _DeliveryList(
              future: _completedFuture,
              onRefresh: _refresh,
              completed: true,
              onTap: null,
              emptyIcon: Icons.history,
              emptyTitle: 'No confirmed deliveries yet',
              emptySubtitle: 'Items you\'ve scanned and confirmed will show up here.',
            ),
          ],
        ),
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
          final accent = completed ? const Color(0xFF15803D) : const Color(0xFF8B0000);
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
                borderRadius: BorderRadius.circular(10),
                child: InkWell(
                  borderRadius: BorderRadius.circular(10),
                  onTap: tappable ? () => onTap!(d) : null,
                  child: Container(
                    padding: const EdgeInsets.all(14),
                    decoration: BoxDecoration(
                      borderRadius: BorderRadius.circular(10),
                      border: Border.all(color: Colors.black.withValues(alpha: 0.08)),
                    ),
                    child: Row(
                      children: [
                        Container(
                          width: 40, height: 40,
                          alignment: Alignment.center,
                          decoration: BoxDecoration(
                            color: accent.withValues(alpha: 0.1),
                            borderRadius: BorderRadius.circular(8),
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
                          const Icon(Icons.check_circle, color: Color(0xFF15803D), size: 20)
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
