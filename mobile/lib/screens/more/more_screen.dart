import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../core/router/app_router.dart';
import '../../core/theme/app_colors.dart';
import '../../providers/auth_provider.dart';
import '../../services/daily_closing_service.dart';

class MoreScreen extends ConsumerStatefulWidget {
  const MoreScreen({super.key});

  @override
  ConsumerState<MoreScreen> createState() => _MoreScreenState();
}

class _MoreScreenState extends ConsumerState<MoreScreen> {
  bool _migrating = false;

  static String _roleName(String role) => switch (role.toLowerCase()) {
        'admin' => 'অ্যাডমিন',
        'manager' => 'ম্যানেজার',
        'cashier' => 'ক্যাশিয়ার',
        _ => role,
      };

  Future<void> _handleDatabaseMigration() async {
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (ctx) => AlertDialog(
        title: const Row(
          children: [
            Icon(Icons.storage_outlined, color: AppColors.primary),
            SizedBox(width: 8),
            Text('ডাটাবেস মাইগ্রেশন', style: TextStyle(fontWeight: FontWeight.bold)),
          ],
        ),
        content: const Text(
          'সার্ভারের ডাটাবেস টেবিল (daily_closings, expenses ইত্যাদি) এবং অ্যাডমিন পারমিশন আপডেট করা হবে। আপনি কি শুরু করতে চান?',
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.of(ctx).pop(false),
            child: const Text('বাতিল'),
          ),
          FilledButton(
            onPressed: () => Navigator.of(ctx).pop(true),
            child: const Text('মাইগ্রেশন চালান'),
          ),
        ],
      ),
    );

    if (confirmed != true || !mounted) return;

    setState(() => _migrating = true);
    try {
      final msg = await ref.read(dailyClosingServiceProvider).runMigration();
      if (mounted) {
        // Also refresh user session so newly granted permissions apply
        ref.read(authControllerProvider.notifier).restore();

        showDialog<void>(
          context: context,
          builder: (ctx) => AlertDialog(
            title: const Row(
              children: [
                Icon(Icons.check_circle, color: AppColors.success),
                SizedBox(width: 8),
                Text('সফল হয়েছে'),
              ],
            ),
            content: Text(msg),
            actions: [
              FilledButton(
                onPressed: () => Navigator.of(ctx).pop(),
                child: const Text('ঠিক আছে'),
              ),
            ],
          ),
        );
      }
    } catch (e) {
      if (mounted) {
        showDialog<void>(
          context: context,
          builder: (ctx) => AlertDialog(
            title: const Row(
              children: [
                Icon(Icons.error_outline, color: AppColors.error),
                SizedBox(width: 8),
                Text('মাইগ্রেশন ব্যর্থ'),
              ],
            ),
            content: Text('ত্রুটি: $e\n\nআপনি চাইলে ব্রাউজার থেকে সরাসরি https://appjewellery.shop/migrate-db লিংকে গিয়েও মাইগ্রেশন সম্পন্ন করতে পারেন।'),
            actions: [
              FilledButton(
                onPressed: () => Navigator.of(ctx).pop(),
                child: const Text('ঠিক আছে'),
              ),
            ],
          ),
        );
      }
    } finally {
      if (mounted) {
        setState(() => _migrating = false);
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final auth = ref.watch(authControllerProvider);
    final user = auth is AuthAuthenticated ? auth.user : null;

    return Scaffold(
      appBar: AppBar(title: const Text('মেনু ও সেটিংস')),
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
                    Row(
                      children: [
                        CircleAvatar(
                          radius: 24,
                          backgroundColor: AppColors.primary.withValues(alpha: 0.15),
                          child: const Icon(Icons.person, color: AppColors.primary, size: 28),
                        ),
                        const SizedBox(width: 14),
                        Expanded(
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Text(
                                user.name,
                                style: theme.textTheme.titleMedium?.copyWith(
                                  fontWeight: FontWeight.w700,
                                  color: AppColors.ink,
                                ),
                              ),
                              const SizedBox(height: 2),
                              Text(
                                user.email,
                                style: const TextStyle(
                                  color: AppColors.inkMuted,
                                  fontSize: 13,
                                ),
                              ),
                            ],
                          ),
                        ),
                      ],
                    ),
                    const SizedBox(height: 12),
                    Wrap(
                      spacing: 8,
                      runSpacing: 8,
                      children: [
                        for (final role in user.roles)
                          Container(
                            padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
                            decoration: BoxDecoration(
                              color: AppColors.primary.withValues(alpha: 0.1),
                              borderRadius: BorderRadius.circular(8),
                              border: Border.all(color: AppColors.primary.withValues(alpha: 0.2)),
                            ),
                            child: Text(
                              _roleName(role),
                              style: const TextStyle(
                                color: AppColors.primary,
                                fontWeight: FontWeight.bold,
                                fontSize: 12,
                              ),
                            ),
                          ),
                      ],
                    ),
                  ],
                ),
              ),
            ),
          const SizedBox(height: 16),

          // Operations section
          const Padding(
            padding: EdgeInsets.symmetric(horizontal: 4, vertical: 6),
            child: Text(
              'হিসাব ও দোকান পরিচালনা',
              style: TextStyle(
                fontWeight: FontWeight.bold,
                fontSize: 14,
                color: AppColors.inkMuted,
              ),
            ),
          ),
          Card(
            child: Column(
              children: [
                ListTile(
                  leading: Container(
                    padding: const EdgeInsets.all(8),
                    decoration: BoxDecoration(
                      color: AppColors.primary.withValues(alpha: 0.1),
                      borderRadius: BorderRadius.circular(8),
                    ),
                    child: const Icon(Icons.lock_clock_outlined, color: AppColors.primary),
                  ),
                  title: const Text(
                    'দৈনিক সমাপ্তি (Day Closing)',
                    style: TextStyle(fontWeight: FontWeight.w700, color: AppColors.ink),
                  ),
                  subtitle: const Text(
                    'প্রতিদিনের ক্যাশ ক্লোজিং ও ব্যালেন্স হিসাব',
                    style: TextStyle(fontSize: 12, color: AppColors.inkMuted),
                  ),
                  trailing: const Icon(Icons.chevron_right, color: AppColors.inkMuted),
                  onTap: () => context.push(AppRoutes.dailyClosing),
                ),
                const Divider(height: 1),
                ListTile(
                  leading: Container(
                    padding: const EdgeInsets.all(8),
                    decoration: BoxDecoration(
                      color: AppColors.secondary.withValues(alpha: 0.1),
                      borderRadius: BorderRadius.circular(8),
                    ),
                    child: _migrating
                        ? const SizedBox(
                            width: 24,
                            height: 24,
                            child: CircularProgressIndicator(strokeWidth: 2),
                          )
                        : const Icon(Icons.storage_outlined, color: AppColors.secondary),
                  ),
                  title: const Text(
                    'ডাটাবেস মাইগ্রেশন (Database Migration)',
                    style: TextStyle(fontWeight: FontWeight.w700, color: AppColors.ink),
                  ),
                  subtitle: const Text(
                    'সার্ভারের নতুন টেবিল ও পারমিশন এক ক্লিকে আপডেট করুন',
                    style: TextStyle(fontSize: 12, color: AppColors.inkMuted),
                  ),
                  trailing: const Icon(Icons.chevron_right, color: AppColors.inkMuted),
                  onTap: _migrating ? null : _handleDatabaseMigration,
                ),
              ],
            ),
          ),
          const SizedBox(height: 16),

          // Account & Settings
          const Padding(
            padding: EdgeInsets.symmetric(horizontal: 4, vertical: 6),
            child: Text(
              'অ্যাকাউন্ট',
              style: TextStyle(
                fontWeight: FontWeight.bold,
                fontSize: 14,
                color: AppColors.inkMuted,
              ),
            ),
          ),
          Card(
            child: Column(
              children: [
                ListTile(
                  leading: const Icon(Icons.refresh, color: AppColors.ink),
                  title: const Text('সেশন রিফ্রেশ করুন', style: TextStyle(color: AppColors.ink)),
                  onTap: () async {
                    await ref.read(authControllerProvider.notifier).restore();
                    if (context.mounted) {
                      ScaffoldMessenger.of(context).showSnackBar(
                        const SnackBar(content: Text('সেশন সফলভাবে রিফ্রেশ করা হয়েছে')),
                      );
                    }
                  },
                ),
                const Divider(height: 1),
                ListTile(
                  leading: const Icon(Icons.logout, color: AppColors.error),
                  title: const Text(
                    'লগআউট / প্রস্থান',
                    style: TextStyle(color: AppColors.error, fontWeight: FontWeight.w600),
                  ),
                  onTap: () => ref.read(authControllerProvider.notifier).logout(),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}
