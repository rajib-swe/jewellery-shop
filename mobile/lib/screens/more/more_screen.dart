import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../providers/auth_provider.dart';

class MoreScreen extends ConsumerWidget {
  const MoreScreen({super.key});

  static String _roleName(String role) => switch (role.toLowerCase()) {
    'admin' => 'অ্যাডমিন',
    'manager' => 'ম্যানেজার',
    'cashier' => 'ক্যাশিয়ার',
    _ => role,
  };

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final theme = Theme.of(context);
    final auth = ref.watch(authControllerProvider);
    final user = auth is AuthAuthenticated ? auth.user : null;

    return Scaffold(
      appBar: AppBar(title: const Text('মেনু')),
      body: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          if (user != null)
            Card(
              child: Padding(
                padding: const EdgeInsets.all(16),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      user.name,
                      style: theme.textTheme.titleMedium?.copyWith(
                        fontWeight: FontWeight.w600,
                      ),
                    ),
                    const SizedBox(height: 4),
                    Text(
                      user.email,
                      style: theme.textTheme.bodySmall?.copyWith(
                        color: theme.colorScheme.onSurfaceVariant,
                      ),
                    ),
                    const SizedBox(height: 12),
                    Wrap(
                      spacing: 8,
                      runSpacing: 8,
                      children: [
                        for (final role in user.roles)
                          Chip(
                            label: Text(_roleName(role)),
                            visualDensity: VisualDensity.compact,
                          ),
                      ],
                    ),
                  ],
                ),
              ),
            ),
          if (user != null) const SizedBox(height: 16),
          Card(
            child: Column(
              children: [
                ListTile(
                  leading: const Icon(Icons.refresh),
                  title: const Text('সেশন রিফ্রেশ করুন'),
                  onTap: () =>
                      ref.read(authControllerProvider.notifier).restore(),
                ),
                const Divider(),
                ListTile(
                  leading: Icon(
                    Icons.logout,
                    color: theme.colorScheme.error,
                  ),
                  title: Text(
                    'লগআউট / প্রস্থান',
                    style: TextStyle(color: theme.colorScheme.error),
                  ),
                  onTap: () =>
                      ref.read(authControllerProvider.notifier).logout(),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}