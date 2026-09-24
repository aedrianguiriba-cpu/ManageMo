import 'package:flutter/material.dart';
import '../services/api_client.dart';
import '../theme.dart';

/// The "Profile" tab — account details for the signed-in property custodian,
/// plus the log-out action (also still reachable from the header's avatar
/// menu, but this is the dedicated, fuller view).
class ProfileScreen extends StatelessWidget {
  final AppUser user;
  final VoidCallback onLogout;

  const ProfileScreen({super.key, required this.user, required this.onLogout});

  @override
  Widget build(BuildContext context) {
    final initials = user.fullName.isNotEmpty ? user.fullName[0].toUpperCase() : '?';
    final roleLabel = user.role == 'admin' ? 'Administrator' : 'Property Custodian';

    return SafeArea(
      top: false,
      child: ListView(
        // Extra bottom padding so the last card isn't hidden behind the
        // floating nav bar, which overlays the content (extendBody).
        padding: const EdgeInsets.fromLTRB(16, 16, 16, 96),
        children: [
          // Avatar + name header card.
          Container(
            width: double.infinity,
            padding: const EdgeInsets.symmetric(vertical: 28, horizontal: 20),
            decoration: BoxDecoration(
              color: AppColors.primary,
              borderRadius: BorderRadius.circular(18),
              boxShadow: [BoxShadow(color: AppColors.primaryDark.withValues(alpha: 0.25), blurRadius: 14, offset: const Offset(0, 6))],
            ),
            child: Column(
              children: [
                CircleAvatar(
                  radius: 34,
                  backgroundColor: Colors.white,
                  child: Text(initials, style: const TextStyle(color: AppColors.primary, fontWeight: FontWeight.w800, fontSize: 26)),
                ),
                const SizedBox(height: 14),
                Text(
                  user.fullName,
                  textAlign: TextAlign.center,
                  style: const TextStyle(color: Colors.white, fontWeight: FontWeight.w800, fontSize: 18),
                ),
                const SizedBox(height: 6),
                Container(
                  padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 4),
                  decoration: BoxDecoration(
                    color: Colors.white.withValues(alpha: 0.16),
                    borderRadius: BorderRadius.circular(20),
                  ),
                  child: Text(roleLabel, style: const TextStyle(color: Colors.white, fontWeight: FontWeight.w700, fontSize: 12)),
                ),
              ],
            ),
          ),
          const SizedBox(height: 18),
          // Account details card.
          Container(
            decoration: BoxDecoration(
              color: Colors.white,
              borderRadius: BorderRadius.circular(16),
              border: Border.all(color: AppColors.border),
            ),
            child: Column(
              children: [
                _ProfileRow(icon: Icons.email_outlined, label: 'Email', value: user.email),
                const Divider(height: 1, color: AppColors.border),
                // Straight from the account's own college_id column — no
                // network round-trip to resolve a friendly name, so it can
                // never sit blank/"Loading…" waiting on a lookup that might
                // fail or hang.
                _ProfileRow(icon: Icons.apartment_outlined, label: 'Department', value: user.collegeId ?? 'Not assigned'),
                if ((user.phone ?? '').isNotEmpty) ...[
                  const Divider(height: 1, color: AppColors.border),
                  _ProfileRow(icon: Icons.phone_outlined, label: 'Phone', value: user.phone!),
                ],
              ],
            ),
          ),
          const SizedBox(height: 24),
          SizedBox(
            height: 50,
            child: OutlinedButton.icon(
              onPressed: onLogout,
              style: OutlinedButton.styleFrom(
                foregroundColor: AppColors.danger,
                side: const BorderSide(color: AppColors.danger),
                shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
              ),
              icon: const Icon(Icons.logout),
              label: const Text('Log Out', style: TextStyle(fontWeight: FontWeight.w700)),
            ),
          ),
        ],
      ),
    );
  }
}

class _ProfileRow extends StatelessWidget {
  final IconData icon;
  final String label;
  final String value;

  const _ProfileRow({required this.icon, required this.label, required this.value});

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 14),
      child: Row(
        children: [
          Container(
            width: 36, height: 36,
            decoration: const BoxDecoration(color: AppColors.primarySoft, shape: BoxShape.circle),
            child: Icon(icon, size: 18, color: AppColors.primary),
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(label, style: const TextStyle(fontSize: 11.5, fontWeight: FontWeight.w600, color: AppColors.inkMuted)),
                const SizedBox(height: 2),
                Text(value, style: const TextStyle(fontSize: 14, fontWeight: FontWeight.w600, color: AppColors.ink)),
              ],
            ),
          ),
        ],
      ),
    );
  }
}
