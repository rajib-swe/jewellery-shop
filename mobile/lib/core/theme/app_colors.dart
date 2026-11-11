import 'package:flutter/material.dart';

abstract final class AppColors {
  // Brand Gold
  static const Color primary = Color(0xFF9E6E12); // Rich Royal Gold
  static const Color primaryLight = Color(0xFFC49A38); // Warm Amber Gold
  static const Color accent = Color(0xFFD4A73C); // Bright Jewel Gold

  // Trust Emerald & Accents
  static const Color secondary = Color(0xFF0F5A47); // Deep Royal Emerald Green
  static const Color secondaryLight = Color(0xFF1B7A62);

  // Surfaces - Crisp Pure White
  static const Color background = Color(0xFFFFFFFF); // Pure White Background
  static const Color surface = Color(0xFFFFFFFF); // Pure White Surface
  static const Color surfaceVariant = Color(0xFFF8FAFC); // Very Soft Off-White

  // Status
  static const Color success = Color(0xFF15803D);
  static const Color warning = Color(0xFFD97706);
  static const Color error = Color(0xFFDC2626);
  static const Color info = Color(0xFF0284C7);

  // Typography & Borders - Deep Black & High Contrast
  static const Color ink = Color(0xFF000000); // Pure Black (Clear Readability)
  static const Color inkMuted = Color(0xFF374151); // Gray 700 (High-Contrast Secondary)
  static const Color inkSubtle = Color(0xFF4B5563); // Gray 600
  static const Color outline = Color(0xFFD1D5DB); // Gray 300 (Crisp borders)
  static const Color outlineLight = Color(0xFFE5E7EB); // Gray 200

  // Dark Theme fallbacks (keep light, white, and clear per user request)
  static const Color darkBackground = Color(0xFFFFFFFF);
  static const Color darkSurface = Color(0xFFFFFFFF);
  static const Color darkInk = Color(0xFF000000);
  static const Color darkInkMuted = Color(0xFF374151);
  static const Color darkOutline = Color(0xFFD1D5DB);
}
