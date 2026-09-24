import 'package:flutter/material.dart';

/// Brand tokens shared across the app — mirrors the web app's palette
/// (maroon primary, green for success/available, amber for pending/warning).
class AppColors {
  AppColors._();

  static const primary = Color(0xFF8B0000);
  static const primaryDark = Color(0xFF6B0000);
  static const primarySoft = Color(0x148B0000); // ~8% opacity

  static const success = Color(0xFF15803D);
  static const successSoft = Color(0x1415803D);

  static const info = Color(0xFF1D4ED8);
  static const infoSoft = Color(0x141D4ED8);

  static const warning = Color(0xFFB45309);
  static const warningSoft = Color(0x14B45309);

  static const danger = Color(0xFFB91C1C);
  static const dangerSoft = Color(0x14B91C1C);

  static const ink = Color(0xFF1A1D23);
  static const inkMuted = Color(0xFF6B7280);
  static const surface = Color(0xFFF7F7F8);
  static const border = Color(0xFFE5E7EB);
}

ThemeData buildAppTheme() {
  final base = ThemeData(
    colorScheme: ColorScheme.fromSeed(seedColor: AppColors.primary),
    useMaterial3: true,
    scaffoldBackgroundColor: AppColors.surface,
    fontFamily: 'Roboto',
  );
  return base.copyWith(
    appBarTheme: const AppBarTheme(
      backgroundColor: AppColors.primary,
      foregroundColor: Colors.white,
      elevation: 0,
      centerTitle: false,
    ),
    inputDecorationTheme: InputDecorationTheme(
      filled: true,
      fillColor: Colors.white,
      border: OutlineInputBorder(borderRadius: BorderRadius.circular(10)),
      contentPadding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
    ),
    elevatedButtonTheme: ElevatedButtonThemeData(
      style: ElevatedButton.styleFrom(
        backgroundColor: AppColors.primary,
        foregroundColor: Colors.white,
        padding: const EdgeInsets.symmetric(vertical: 14),
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
        textStyle: const TextStyle(fontWeight: FontWeight.w700, fontSize: 15),
      ),
    ),
    bottomNavigationBarTheme: const BottomNavigationBarThemeData(
      backgroundColor: Colors.white,
      selectedItemColor: AppColors.primary,
      unselectedItemColor: AppColors.inkMuted,
      type: BottomNavigationBarType.fixed,
      elevation: 8,
    ),
    cardTheme: CardThemeData(
      color: Colors.white,
      elevation: 0,
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(14),
        side: const BorderSide(color: AppColors.border),
      ),
    ),
  );
}
