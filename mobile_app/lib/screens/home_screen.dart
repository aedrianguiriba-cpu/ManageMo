import 'package:flutter/material.dart';
import '../services/api_client.dart';
import '../theme.dart';
import '../widgets/app_header.dart';
import 'dashboard_screen.dart';
import 'requests_tab.dart';
import 'login_screen.dart';
import 'scanner_screen.dart';

/// App shell: brand app bar + bottom navigation between the Dashboard and
/// the Pending/Completed request lists. Owns the data futures so both tabs
/// share the same in-flight request instead of fetching twice.
class HomeScreen extends StatefulWidget {
  final AppUser user;
  const HomeScreen({super.key, required this.user});

  @override
  State<HomeScreen> createState() => _HomeScreenState();
}

class _HomeScreenState extends State<HomeScreen> {
  final _api = ApiClient();
  int _navIndex = 0;
  late Future<List<DeliveryItem>> _pendingFuture;
  late Future<List<DeliveryItem>> _completedFuture;

  static const _titles = ['Dashboard', 'My Requests'];
  static const _subtitles = ['Your delivery status at a glance', 'Track and confirm your deliveries'];

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
        SnackBar(content: Text(message), backgroundColor: AppColors.success),
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
    return Scaffold(
      backgroundColor: AppColors.surface,
      body: Column(
        children: [
          AppHeader(
            user: widget.user,
            title: _titles[_navIndex],
            subtitle: _subtitles[_navIndex],
            onLogout: _logout,
          ),
          Expanded(
            child: IndexedStack(
              index: _navIndex,
              children: [
                DashboardScreen(
                  user: widget.user,
                  pendingFuture: _pendingFuture,
                  completedFuture: _completedFuture,
                  onRefresh: _refresh,
                  onScanItem: _scanItem,
                  onViewAllPending: () => setState(() => _navIndex = 1),
                ),
                RequestsTab(
                  pendingFuture: _pendingFuture,
                  completedFuture: _completedFuture,
                  onRefresh: _refresh,
                  onScanItem: _scanItem,
                ),
              ],
            ),
          ),
        ],
      ),
      bottomNavigationBar: BottomNavigationBar(
        currentIndex: _navIndex,
        onTap: (i) => setState(() => _navIndex = i),
        items: const [
          BottomNavigationBarItem(icon: Icon(Icons.dashboard_outlined), activeIcon: Icon(Icons.dashboard), label: 'Dashboard'),
          BottomNavigationBarItem(icon: Icon(Icons.qr_code_scanner), label: 'Requests'),
        ],
      ),
    );
  }
}
