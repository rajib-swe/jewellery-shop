import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../providers/auth_provider.dart';
import '../../screens/accounts/daily_closing_screen.dart';
import '../../screens/auth/login_screen.dart';
import '../../screens/customers/customer_profile_screen.dart';
import '../../screens/customers/customers_screen.dart';
import '../../screens/dashboard/dashboard_screen.dart';
import '../../screens/more/more_screen.dart';
import '../../screens/pawn/new_pawn_screen.dart';
import '../../screens/pawn/pawn_detail_screen.dart';
import '../../screens/pawn/pawn_screen.dart';
import '../../screens/sale/new_sale_screen.dart';
import '../../screens/sale/sale_detail_screen.dart';
import '../../screens/sale/sale_screen.dart';
import '../../screens/shell/app_shell.dart';

abstract final class AppRoutes {
  static const String login = '/login';
  static const String dashboard = '/dashboard';
  static const String sale = '/sale';
  static const String pawn = '/pawn';
  static const String customers = '/customers';
  static const String more = '/more';
  static const String dailyClosing = '/daily-closing';
}

final routerProvider = Provider<GoRouter>((ref) {
  final router = GoRouter(
    initialLocation: AppRoutes.login,
    redirect: (context, state) {
      final auth = ref.read(authControllerProvider);
      final onLogin = state.matchedLocation == AppRoutes.login;

      return switch (auth) {
        AuthAuthenticated() => onLogin ? AppRoutes.dashboard : null,
        _ => onLogin ? null : AppRoutes.login,
      };
    },
    routes: [
      GoRoute(
        path: AppRoutes.login,
        builder: (context, state) => const LoginScreen(),
      ),
      GoRoute(
        path: AppRoutes.dailyClosing,
        builder: (context, state) => const DailyClosingScreen(),
      ),
      StatefulShellRoute.indexedStack(
        builder: (context, state, navigationShell) =>
            AppShell(navigationShell: navigationShell),
        branches: [
          StatefulShellBranch(
            routes: [
              GoRoute(
                path: AppRoutes.dashboard,
                builder: (context, state) => const DashboardScreen(),
              ),
            ],
          ),
          StatefulShellBranch(
            routes: [
              GoRoute(
                path: AppRoutes.sale,
                builder: (context, state) => const SaleScreen(),
                routes: [
                  GoRoute(
                    path: 'new',
                    builder: (context, state) => const NewSaleScreen(),
                  ),
                  GoRoute(
                    path: ':id',
                    builder: (context, state) => SaleDetailScreen(
                      saleId:
                          int.tryParse(state.pathParameters['id'] ?? '') ?? 0,
                    ),
                  ),
                ],
              ),
            ],
          ),
          StatefulShellBranch(
            routes: [
              GoRoute(
                path: AppRoutes.pawn,
                builder: (context, state) => const PawnScreen(),
                routes: [
                  GoRoute(
                    path: 'new',
                    builder: (context, state) => const NewPawnScreen(),
                  ),
                  GoRoute(
                    path: ':id',
                    builder: (context, state) => PawnDetailScreen(
                      pawnId:
                          int.tryParse(state.pathParameters['id'] ?? '') ?? 0,
                    ),
                  ),
                ],
              ),
            ],
          ),
          StatefulShellBranch(
            routes: [
              GoRoute(
                path: AppRoutes.customers,
                builder: (context, state) => const CustomersScreen(),
                routes: [
                  GoRoute(
                    path: ':id',
                    builder: (context, state) => CustomerProfileScreen(
                      customerId:
                          int.tryParse(state.pathParameters['id'] ?? '') ?? 0,
                    ),
                  ),
                ],
              ),
            ],
          ),
          StatefulShellBranch(
            routes: [
              GoRoute(
                path: AppRoutes.more,
                builder: (context, state) => const MoreScreen(),
              ),
            ],
          ),
        ],
      ),
    ],
  );

  ref.listen<AuthState>(
    authControllerProvider,
    (previous, next) => router.refresh(),
  );

  return router;
});
