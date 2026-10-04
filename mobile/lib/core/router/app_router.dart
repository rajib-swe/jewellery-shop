import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../providers/auth_provider.dart';
import '../../screens/auth/login_screen.dart';
import '../../screens/customers/customers_screen.dart';
import '../../screens/dashboard/dashboard_screen.dart';
import '../../screens/more/more_screen.dart';
import '../../screens/pawn/pawn_screen.dart';
import '../../screens/sale/sale_screen.dart';
import '../../screens/shell/app_shell.dart';

abstract final class AppRoutes {
  static const String login = '/login';
  static const String dashboard = '/dashboard';
  static const String sale = '/sale';
  static const String pawn = '/pawn';
  static const String customers = '/customers';
  static const String more = '/more';
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
              ),
            ],
          ),
          StatefulShellBranch(
            routes: [
              GoRoute(
                path: AppRoutes.pawn,
                builder: (context, state) => const PawnScreen(),
              ),
            ],
          ),
          StatefulShellBranch(
            routes: [
              GoRoute(
                path: AppRoutes.customers,
                builder: (context, state) => const CustomersScreen(),
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