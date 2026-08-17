import 'package:flutter/material.dart';
import '../services/api_client.dart';
import '../theme.dart';

/// The app's branded top header — replaces the stock Material AppBar with
/// something that actually carries the ManageMo identity: the logo mark,
/// a two-line contextual title, and a tappable user avatar (with a proper
/// account menu) instead of a bare logout icon.
class AppHeader extends StatelessWidget {
  final AppUser user;
  final String title;
  final String subtitle;
  final VoidCallback onLogout;

  const AppHeader({
    super.key,
    required this.user,
    required this.title,
    required this.subtitle,
    required this.onLogout,
  });

  @override
  Widget build(BuildContext context) {
    final initials = user.fullName.isNotEmpty ? user.fullName[0].toUpperCase() : '?';

    return Container(
      decoration: const BoxDecoration(
        color: AppColors.primary,
        borderRadius: BorderRadius.only(bottomLeft: Radius.circular(22), bottomRight: Radius.circular(22)),
        boxShadow: [BoxShadow(color: Color(0x33000000), blurRadius: 12, offset: Offset(0, 4))],
      ),
      child: SafeArea(
        bottom: false,
        child: Padding(
          padding: const EdgeInsets.fromLTRB(18, 14, 14, 18),
          child: Row(
            children: [
              Container(
                width: 42, height: 42,
                padding: const EdgeInsets.all(6),
                decoration: BoxDecoration(
                  color: Colors.white,
                  borderRadius: BorderRadius.circular(12),
                ),
                child: Image.asset('assets/images/logo.png', fit: BoxFit.contain),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    Text(
                      title,
                      style: const TextStyle(color: Colors.white, fontWeight: FontWeight.w800, fontSize: 18, letterSpacing: -0.2),
                      overflow: TextOverflow.ellipsis,
                    ),
                    Text(
                      subtitle,
                      style: TextStyle(color: Colors.white.withValues(alpha: 0.75), fontSize: 12.5, fontWeight: FontWeight.w500),
                      overflow: TextOverflow.ellipsis,
                    ),
                  ],
                ),
              ),
              const SizedBox(width: 10),
              PopupMenuButton<String>(
                offset: const Offset(0, 46),
                shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
                onSelected: (value) {
                  if (value == 'logout') onLogout();
                },
                itemBuilder: (context) => [
                  PopupMenuItem<String>(
                    enabled: false,
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      mainAxisSize: MainAxisSize.min,
                      children: [
                        Text(user.fullName, style: const TextStyle(fontWeight: FontWeight.w800, fontSize: 13.5, color: AppColors.ink)),
                        const SizedBox(height: 2),
                        Text(user.email, style: TextStyle(fontSize: 11.5, color: Colors.black.withValues(alpha: 0.5))),
                      ],
                    ),
                  ),
                  const PopupMenuDivider(),
                  const PopupMenuItem<String>(
                    value: 'logout',
                    child: Row(
                      children: [
                        Icon(Icons.logout, size: 18, color: AppColors.danger),
                        SizedBox(width: 10),
                        Text('Log out', style: TextStyle(color: AppColors.danger, fontWeight: FontWeight.w700)),
                      ],
                    ),
                  ),
                ],
                child: CircleAvatar(
                  radius: 19,
                  backgroundColor: Colors.white,
                  child: Text(initials, style: const TextStyle(color: AppColors.primary, fontWeight: FontWeight.w800, fontSize: 15)),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}
