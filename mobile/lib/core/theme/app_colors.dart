import 'package:flutter/material.dart';

abstract final class AppColors {
  // Brand Gold
  static const Color primary = Color(0xFF9E6E12); // Rich Royal Gold
  static const Color primaryLight = Color(0xFFC49A38); // Warm Amber Gold
  static const Color accent = Color(0xFFD4A73C); // Bright Jewel Gold

  // Trust Emerald & Accents
  static const Color secondary = Color(0xFF0F5A47); // Deep Royal Emerald Green
  static const Color secondaryLight = Color(0xFF1B7A62);

  // Surfaces
  static const Color background = Color(0xFFF8FAFC); // Clean Modern Pearl
  static const Color surface = Color(0xFFFFFFFF); // Pure White
  static const Color surfaceVariant = Color(0xFFF1F5F9); // Light Slate Surface

  // Status
  static const Color success = Color(0xFF15803D);
  static const Color warning = Color(0xFFD97706);
  static const Color error = Color(0xFFDC2626);
  static const Color info = Color(0xFF0284C7);

  // Typography & Borders
  static const Color ink = Color(0xFF0F172A); // Slate 900 (High contrast readability)
  static const Color inkMuted = Color(0xFF64748B); // Slate 500 (Clean secondary text)
  static const Color inkSubtle = Color(0xFF94A3B8); // Slate 400
  static const Color outline = Color(0xFFCBD5E1); // Slate 300 (Crisp borders)
  static const Color outlineLight = Color(0xFFE2E8F0); // Slate 200

  // Dark Theme
  static const Color darkBackground = Color(0xFF0F172A);
  static const Color darkSurface = Color(0xFF1E293B);
  static const Color darkInk = Color(0xFFF8FAFC);
  static const Color darkInkMuted = Color(0xFF94A3B8);
  static const Color darkOutline = Color(0xFF334155);
}
