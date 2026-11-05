import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../models/dashboard_summary.dart';
import '../models/gold_rate.dart';
import '../services/dashboard_service.dart';
import '../services/gold_rate_service.dart';
import 'auth_provider.dart';

final dashboardServiceProvider = Provider<DashboardService>((ref) {
  return DashboardService(dio: ref.watch(dioProvider));
});

final dashboardProvider = FutureProvider<DashboardSummary>((ref) {
  return ref.watch(dashboardServiceProvider).fetch();
});

final goldRateServiceProvider = Provider<GoldRateService>((ref) {
  return GoldRateService(dio: ref.watch(dioProvider));
});

final latestGoldRatesProvider = FutureProvider<List<GoldRate>>((ref) {
  return ref.watch(goldRateServiceProvider).latest();
});
