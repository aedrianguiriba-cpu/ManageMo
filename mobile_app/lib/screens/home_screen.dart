import 'package:flutter/material.dart';
import '../services/api_client.dart';
import '../theme.dart';
import '../widgets/app_header.dart';
import 'dashboard_screen.dart';
import 'requests_tab.dart';
import 'profile_screen.dart';
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

  static const _titles = ['Dashboard', 'My Requests', 'Profile'];
  static const _subtitles = ['Your delivery status at a glance', 'Track and confirm your deliveries', 'Your account details'];

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
      // No bottomNavigationBar — Scaffold's own version is docked, opaque,
      // and squares off the bottom edge. The nav bar here floats over the
      // content instead (see _FloatingNavBar below), so the body just fills
      // the whole screen underneath it.
      extendBody: true,
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
                ProfileScreen(user: widget.user, onLogout: _logout),
              ],
            ),
          ),
        ],
      ),
      bottomNavigationBar: _FloatingNavBar(
        currentIndex: _navIndex,
        onTap: (i) => setState(() => _navIndex = i),
        items: const [
          _FloatingNavItem(icon: Icons.dashboard_outlined, activeIcon: Icons.dashboard, label: 'Dashboard'),
          _FloatingNavItem(icon: Icons.qr_code_scanner, label: 'Requests'),
          _FloatingNavItem(icon: Icons.person_outline, activeIcon: Icons.person, label: 'Profile'),
        ],
      ),
    );
  }
}

class _FloatingNavItem {
  final IconData icon;
  final IconData? activeIcon;
  final String label;
  const _FloatingNavItem({required this.icon, this.activeIcon, required this.label});
}

/// A pill-shaped, semi-transparent nav bar that floats above the content
/// (frosted-glass blur behind it) instead of Scaffold's docked, opaque
/// [BottomNavigationBar].
class _FloatingNavBar extends StatelessWidget {
  final int currentIndex;
  final ValueChanged<int> onTap;
  final List<_FloatingNavItem> items;

  const _FloatingNavBar({required this.currentIndex, required this.onTap, required this.items});

  @override
  Widget build(BuildContext context) {
    return SafeArea(
      minimum: const EdgeInsets.fromLTRB(20, 0, 20, 14),
      child: ClipRRect(
        borderRadius: BorderRadius.circular(32),
        child: Container(
          height: 64,
          decoration: BoxDecoration(
            // Solid maroon — no blur/translucency.
            color: AppColors.primary,
            borderRadius: BorderRadius.circular(32),
            boxShadow: [
              BoxShadow(color: AppColors.primaryDark.withValues(alpha: 0.35), blurRadius: 20, offset: const Offset(0, 8)),
            ],
          ),
          child: Padding(
            // Without this, the first/last tab's icon sits flush against the
            // bar's own heavily-rounded ends (radius 32) — this keeps every
            // item clear of the curve.
            padding: const EdgeInsets.symmetric(horizontal: 10),
            child: Row(
              children: List.generate(items.length, (i) {
                final item = items[i];
                final selected = i == currentIndex;
                return Expanded(
                  child: InkWell(
                    onTap: () => onTap(i),
                    borderRadius: BorderRadius.circular(20),
                    // Center lets the pill shrink-wrap its content below,
                    // while InkWell above still spans the full tab-width tap
                    // target — otherwise the active pill stretched to fill
                    // the whole tab instead of hugging just the icon+label.
                    // The LayoutBuilder+ConstrainedBox caps the pill at its
                    // own slot's width so it can never overflow into a
                    // neighboring tab now that there are 3 of them instead
                    // of 2 (each slot is narrower) — the label itself still
                    // has a hard ellipsis fallback as a last resort.
                    child: LayoutBuilder(
                      builder: (context, slotConstraints) {
                        return Center(
                          child: ConstrainedBox(
                            // A little breathing room off the slot's true edge
                            // so the pill never looks like it's touching the
                            // next tab, even at its widest (selected) size.
                            constraints: BoxConstraints(maxWidth: slotConstraints.maxWidth - 4),
                            child: AnimatedContainer(
                              duration: const Duration(milliseconds: 220),
                              curve: Curves.easeOut,
                              height: 44,
                              padding: EdgeInsets.symmetric(horizontal: selected ? 16 : 12),
                              decoration: BoxDecoration(
                                // Bar is solid maroon now, so the active pill inverts
                                // to white instead of a same-color maroon (which
                                // would barely show up) — unselected stays plain
                                // white icons/labels against the solid background.
                                color: selected ? Colors.white : Colors.transparent,
                                borderRadius: BorderRadius.circular(22),
                                boxShadow: selected
                                    ? [BoxShadow(color: AppColors.primaryDark.withValues(alpha: 0.25), blurRadius: 8, offset: const Offset(0, 2))]
                                    : null,
                              ),
                              child: Row(
                                mainAxisSize: MainAxisSize.min,
                                mainAxisAlignment: MainAxisAlignment.center,
                                children: [
                                  Icon(
                                    selected ? (item.activeIcon ?? item.icon) : item.icon,
                                    size: 22,
                                    color: selected ? AppColors.primary : Colors.white.withValues(alpha: 0.75),
                                  ),
                                  // Flexible must be a direct Row child (not nested inside
                                  // Padding/AnimatedSize) — that's what "Incorrect use of
                                  // ParentDataWidget" was complaining about — so it wraps
                                  // AnimatedSize itself here, and the label's ellipsis is
                                  // just a plain Text overflow fallback underneath it.
                                  Flexible(
                                    child: AnimatedSize(
                                      duration: const Duration(milliseconds: 220),
                                      curve: Curves.easeOut,
                                      alignment: Alignment.centerLeft,
                                      child: selected
                                          ? Padding(
                                              padding: const EdgeInsets.only(left: 8),
                                              child: Text(
                                                item.label,
                                                maxLines: 1,
                                                overflow: TextOverflow.ellipsis,
                                                style: const TextStyle(fontSize: 13, fontWeight: FontWeight.w700, color: AppColors.primary),
                                              ),
                                            )
                                          : const SizedBox(width: 0, height: 0),
                                    ),
                                  ),
                                ],
                              ),
                            ),
                          ),
                        );
                      },
                    ),
                  ),
                );
              }),
            ),
          ),
        ),
      ),
    );
  }
}
