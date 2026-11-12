import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:intl/intl.dart';

import '../../core/api/api_exception.dart';
import '../../core/theme/app_colors.dart';
import '../../core/utils/format.dart';
import '../../models/daily_closing.dart';
import '../../services/daily_closing_service.dart';

class DailyClosingScreen extends ConsumerStatefulWidget {
  const DailyClosingScreen({super.key});

  @override
  ConsumerState<DailyClosingScreen> createState() => _DailyClosingScreenState();
}

class _DailyClosingScreenState extends ConsumerState<DailyClosingScreen>
    with SingleTickerProviderStateMixin {
  late TabController _tabController;
  DateTime _selectedDate = DateTime.now();

  bool _loadingSummary = false;
  CashBookSummary? _summary;
  String? _summaryError;

  bool _loadingHistory = false;
  List<DailyClosingRecord> _history = [];
  String? _historyError;

  final _noteController = TextEditingController();
  bool _submitting = false;

  @override
  void initState() {
    super.initState();
    _tabController = TabController(length: 2, vsync: this);
    _loadSummary();
    _loadHistory();
  }

  @override
  void dispose() {
    _tabController.dispose();
    _noteController.dispose();
    super.dispose();
  }

  String get _formattedSelectedDate =>
      DateFormat('yyyy-MM-dd').format(_selectedDate);

  Future<void> _loadSummary() async {
    setState(() {
      _loadingSummary = true;
      _summaryError = null;
    });

    try {
      final summary = await ref
          .read(dailyClosingServiceProvider)
          .fetchSummary(_formattedSelectedDate);
      if (mounted) {
        setState(() {
          _summary = summary;
          _loadingSummary = false;
        });
      }
    } on ApiException catch (e) {
      if (mounted) {
        setState(() {
          _summaryError = e.message;
          _loadingSummary = false;
        });
      }
    } catch (e) {
      if (mounted) {
        setState(() {
          _summaryError = 'হিসাব লোড করতে সমস্যা হয়েছে: $e';
          _loadingSummary = false;
        });
      }
    }
  }

  Future<void> _loadHistory() async {
    setState(() {
      _loadingHistory = true;
      _historyError = null;
    });

    try {
      final list =
          await ref.read(dailyClosingServiceProvider).listClosings();
      if (mounted) {
        setState(() {
          _history = list;
          _loadingHistory = false;
        });
      }
    } on ApiException catch (e) {
      if (mounted) {
        setState(() {
          _historyError = e.message;
          _loadingHistory = false;
        });
      }
    } catch (_) {
      if (mounted) {
        setState(() {
          _historyError = 'ইতিহাস লোড করা যায়নি';
          _loadingHistory = false;
        });
      }
    }
  }

  Future<void> _pickDate() async {
    final picked = await showDatePicker(
      context: context,
      initialDate: _selectedDate,
      firstDate: DateTime(2020),
      lastDate: DateTime.now(),
    );

    if (picked != null && picked != _selectedDate) {
      setState(() => _selectedDate = picked);
      _loadSummary();
    }
  }

  void _previousDay() {
    setState(() {
      _selectedDate = _selectedDate.subtract(const Duration(days: 1));
    });
    _loadSummary();
  }

  void _nextDay() {
    final next = _selectedDate.add(const Duration(days: 1));
    if (next.isBefore(DateTime.now()) || DateFormat('yyyy-MM-dd').format(next) == DateFormat('yyyy-MM-dd').format(DateTime.now())) {
      setState(() => _selectedDate = next);
      _loadSummary();
    }
  }

  Future<void> _confirmCloseDay() async {
    final summary = _summary;
    if (summary == null) return;

    final confirmed = await showDialog<bool>(
      context: context,
      builder: (ctx) => AlertDialog(
        title: const Row(
          children: [
            Icon(Icons.lock_clock, color: AppColors.primary),
            SizedBox(width: 8),
            Text('দিন সমাপ্ত নিশ্চিতকরণ', style: TextStyle(fontWeight: FontWeight.bold)),
          ],
        ),
        content: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(
              'তারিখ: ${formatDate(_selectedDate)}',
              style: const TextStyle(fontWeight: FontWeight.w600),
            ),
            const SizedBox(height: 8),
            Text(
              'সমাপনী ক্যাশ ব্যালেন্স: ${formatMoney(summary.closingBalance)}',
              style: const TextStyle(
                fontWeight: FontWeight.bold,
                fontSize: 16,
                color: AppColors.primary,
              ),
            ),
            const SizedBox(height: 12),
            const Text(
              'সতর্কতা: দিন সমাপ্ত (লক) করলে এই দিনের লেনদেন সুরক্ষিত থাকবে এবং নতুন এন্ট্রি করা যাবে না। আপনি কি নিশ্চিত?',
              style: TextStyle(fontSize: 13, color: AppColors.inkMuted),
            ),
          ],
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.of(ctx).pop(false),
            child: const Text('বাতিল'),
          ),
          FilledButton(
            onPressed: () => Navigator.of(ctx).pop(true),
            child: const Text('হ্যাঁ, দিন সমাপ্ত করুন'),
          ),
        ],
      ),
    );

    if (confirmed != true || !mounted) return;

    setState(() => _submitting = true);
    try {
      await ref.read(dailyClosingServiceProvider).closeDay(
            _formattedSelectedDate,
            note: _noteController.text.trim(),
          );

      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(
            content: Text('দিন সফলভাবে সমাপ্ত (লক) করা হয়েছে।'),
            backgroundColor: AppColors.success,
          ),
        );
        _noteController.clear();
        _loadSummary();
        _loadHistory();
      }
    } on ApiException catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text(e.message),
            backgroundColor: AppColors.error,
          ),
        );
      }
    } finally {
      if (mounted) {
        setState(() => _submitting = false);
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('দৈনিক সমাপ্তি (Daily Closing)'),
        bottom: TabBar(
          controller: _tabController,
          tabs: const [
            Tab(icon: Icon(Icons.account_balance_wallet_outlined), text: 'দৈনিক ক্যাশ'),
            Tab(icon: Icon(Icons.history_outlined), text: 'পূর্ববর্তী সমাপ্তি'),
          ],
        ),
      ),
      body: TabBarView(
        controller: _tabController,
        children: [
          _buildSummaryTab(),
          _buildHistoryTab(),
        ],
      ),
    );
  }

  Widget _buildSummaryTab() {
    if (_loadingSummary) {
      return const Center(child: CircularProgressIndicator());
    }

    if (_summaryError != null) {
      return Center(
        child: Padding(
          padding: const EdgeInsets.all(24),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              const Icon(Icons.error_outline, size: 48, color: AppColors.error),
              const SizedBox(height: 12),
              Text(
                _summaryError!,
                textAlign: TextAlign.center,
                style: const TextStyle(color: AppColors.error, fontWeight: FontWeight.w600),
              ),
              const SizedBox(height: 16),
              FilledButton.tonal(
                onPressed: _loadSummary,
                child: const Text('আবার চেষ্টা করুন'),
              ),
            ],
          ),
        ),
      );
    }

    final summary = _summary;
    if (summary == null) {
      return const SizedBox.shrink();
    }

    final isToday = DateFormat('yyyy-MM-dd').format(DateTime.now()) == _formattedSelectedDate;

    return RefreshIndicator(
      onRefresh: _loadSummary,
      child: ListView(
        padding: const EdgeInsets.fromLTRB(16, 16, 16, 40),
        children: [
          // Date selector bar
          Card(
            color: AppColors.surface,
            child: Padding(
              padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
              child: Row(
                mainAxisAlignment: MainAxisAlignment.spaceBetween,
                children: [
                  IconButton(
                    icon: const Icon(Icons.chevron_left),
                    tooltip: 'আগের দিন',
                    onPressed: _previousDay,
                  ),
                  InkWell(
                    onTap: _pickDate,
                    borderRadius: BorderRadius.circular(8),
                    child: Padding(
                      padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
                      child: Row(
                        children: [
                          const Icon(Icons.calendar_today, size: 18, color: AppColors.primary),
                          const SizedBox(width: 8),
                          Text(
                            formatDate(_selectedDate),
                            style: const TextStyle(
                              fontWeight: FontWeight.bold,
                              fontSize: 15,
                              color: AppColors.ink,
                            ),
                          ),
                          const SizedBox(width: 4),
                          const Icon(Icons.arrow_drop_down, color: AppColors.inkMuted),
                        ],
                      ),
                    ),
                  ),
                  IconButton(
                    icon: const Icon(Icons.chevron_right),
                    tooltip: 'পরের দিন',
                    onPressed: isToday ? null : _nextDay,
                  ),
                ],
              ),
            ),
          ),
          const SizedBox(height: 12),

          // Status Badge
          Container(
            padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 10),
            decoration: BoxDecoration(
              color: summary.isLocked
                  ? AppColors.primary.withValues(alpha: 0.1)
                  : AppColors.success.withValues(alpha: 0.1),
              borderRadius: BorderRadius.circular(12),
              border: Border.all(
                color: summary.isLocked
                    ? AppColors.primary.withValues(alpha: 0.3)
                    : AppColors.success.withValues(alpha: 0.3),
              ),
            ),
            child: Row(
              children: [
                Icon(
                  summary.isLocked ? Icons.lock : Icons.lock_open,
                  color: summary.isLocked ? AppColors.primary : AppColors.success,
                  size: 20,
                ),
                const SizedBox(width: 10),
                Expanded(
                  child: Text(
                    summary.isLocked
                        ? 'দিন সমাপ্ত (লক করা হয়েছে)'
                        : 'দিন চালু রয়েছে (হিসাব চলমান)',
                    style: TextStyle(
                      fontWeight: FontWeight.bold,
                      color: summary.isLocked ? AppColors.primary : AppColors.success,
                      fontSize: 14,
                    ),
                  ),
                ),
                Text(
                  '${summary.transactionCount} টি লেনদেন',
                  style: const TextStyle(
                    fontSize: 12,
                    fontWeight: FontWeight.w600,
                    color: AppColors.inkMuted,
                  ),
                ),
              ],
            ),
          ),
          const SizedBox(height: 16),

          // Key Financial Metrics Grid
          Row(
            children: [
              Expanded(
                child: _MetricCard(
                  title: 'প্রারম্ভিক ক্যাশ',
                  subtitle: 'দিনের শুরুতে',
                  amount: summary.openingBalance,
                  icon: Icons.account_balance_wallet,
                  color: AppColors.secondary,
                ),
              ),
              const SizedBox(width: 10),
              Expanded(
                child: _MetricCard(
                  title: 'সমাপনী ব্যালেন্স',
                  subtitle: 'বর্তমান অবশিষ্ট',
                  amount: summary.closingBalance,
                  icon: Icons.payments,
                  color: AppColors.primary,
                  isBold: true,
                ),
              ),
            ],
          ),
          const SizedBox(height: 10),
          Row(
            children: [
              Expanded(
                child: _MetricCard(
                  title: 'মোট জমা (ইন)',
                  subtitle: 'বিক্রয়, বন্ধক ও অন্যান্য',
                  amount: summary.totalIn,
                  icon: Icons.arrow_downward,
                  color: AppColors.success,
                ),
              ),
              const SizedBox(width: 10),
              Expanded(
                child: _MetricCard(
                  title: 'মোট প্রদান (আউট)',
                  subtitle: 'বন্ধক ঋণ, খরচ ও ক্রয়',
                  amount: summary.totalOut,
                  icon: Icons.arrow_upward,
                  color: AppColors.error,
                ),
              ),
            ],
          ),
          const SizedBox(height: 20),

          // Method breakdown if available
          if (summary.byMethod.isNotEmpty) ...[
            const Text(
              'পেমেন্ট মেথড ভিত্তিক বিবরণ',
              style: TextStyle(
                fontWeight: FontWeight.bold,
                fontSize: 15,
                color: AppColors.ink,
              ),
            ),
            const SizedBox(height: 8),
            Card(
              child: Column(
                children: [
                  for (var i = 0; i < summary.byMethod.length; i++) ...[
                    ListTile(
                      dense: true,
                      leading: Icon(
                        summary.byMethod[i].method == 'cash'
                            ? Icons.money
                            : Icons.credit_card,
                        color: AppColors.primary,
                      ),
                      title: Text(
                        _methodLabel(summary.byMethod[i].method),
                        style: const TextStyle(fontWeight: FontWeight.w600),
                      ),
                      subtitle: Text(
                        'ইন: ${formatMoney(summary.byMethod[i].totalIn)} · আউট: ${formatMoney(summary.byMethod[i].totalOut)}',
                        style: const TextStyle(fontSize: 12),
                      ),
                      trailing: Text(
                        formatMoney(summary.byMethod[i].net),
                        style: const TextStyle(
                          fontWeight: FontWeight.bold,
                          fontSize: 14,
                          color: AppColors.ink,
                        ),
                      ),
                    ),
                    if (i < summary.byMethod.length - 1) const Divider(height: 1),
                  ],
                ],
              ),
            ),
            const SizedBox(height: 20),
          ],

          // Action Section
          if (!summary.isLocked) ...[
            Card(
              child: Padding(
                padding: const EdgeInsets.all(16),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    const Text(
                      'সমাপ্তি নোট বা মন্তব্য (ঐচ্ছিক)',
                      style: TextStyle(
                        fontWeight: FontWeight.bold,
                        fontSize: 14,
                        color: AppColors.ink,
                      ),
                    ),
                    const SizedBox(height: 8),
                    TextField(
                      controller: _noteController,
                      maxLines: 2,
                      decoration: const InputDecoration(
                        hintText: 'যেমন: দিনের শেষ ক্যাশ মিলিয়ে ক্লোজ করা হলো',
                        prefixIcon: Icon(Icons.edit_note),
                      ),
                    ),
                    const SizedBox(height: 16),
                    FilledButton.icon(
                      onPressed: _submitting ? null : _confirmCloseDay,
                      icon: _submitting
                          ? const SizedBox(
                              width: 18,
                              height: 18,
                              child: CircularProgressIndicator(
                                strokeWidth: 2,
                                color: Colors.white,
                              ),
                            )
                          : const Icon(Icons.lock),
                      label: Text(_submitting ? 'প্রসেসিং হচ্ছে…' : 'দিন সমাপ্ত / ক্লোজ করুন'),
                      style: FilledButton.styleFrom(
                        backgroundColor: AppColors.primary,
                        foregroundColor: Colors.white,
                      ),
                    ),
                  ],
                ),
              ),
            ),
          ] else ...[
            Card(
              color: AppColors.surfaceVariant,
              child: const Padding(
                padding: EdgeInsets.all(16),
                child: Row(
                  children: [
                    Icon(Icons.check_circle, color: AppColors.success, size: 24),
                    SizedBox(width: 12),
                    Expanded(
                      child: Text(
                        'এই দিনের হিসাব সফলভাবে সমাপ্ত হয়েছে। সব ব্যালেন্স সংরক্ষিত রয়েছে।',
                        style: TextStyle(
                          fontWeight: FontWeight.w600,
                          fontSize: 13,
                          color: AppColors.ink,
                        ),
                      ),
                    ),
                  ],
                ),
              ),
            ),
          ],
        ],
      ),
    );
  }

  Widget _buildHistoryTab() {
    if (_loadingHistory) {
      return const Center(child: CircularProgressIndicator());
    }

    if (_historyError != null) {
      return Center(
        child: Padding(
          padding: const EdgeInsets.all(24),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              Text(_historyError!, style: const TextStyle(color: AppColors.error)),
              const SizedBox(height: 12),
              FilledButton.tonal(
                onPressed: _loadHistory,
                child: const Text('আবার চেষ্টা করুন'),
              ),
            ],
          ),
        ),
      );
    }

    if (_history.isEmpty) {
      return Center(
        child: Padding(
          padding: const EdgeInsets.all(32),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              Container(
                padding: const EdgeInsets.all(16),
                decoration: BoxDecoration(
                  color: AppColors.primary.withValues(alpha: 0.1),
                  shape: BoxShape.circle,
                ),
                child: const Icon(Icons.event_busy, color: AppColors.primary, size: 36),
              ),
              const SizedBox(height: 12),
              const Text(
                'কোনো পূর্ববর্তী সমাপ্তির রেকর্ড পাওয়া যায়নি',
                style: TextStyle(fontWeight: FontWeight.bold, fontSize: 15),
              ),
              const SizedBox(height: 4),
              const Text(
                'প্রতিদিনের হিসাব শেষ হলে দিন সমাপ্ত করুন।',
                style: TextStyle(color: AppColors.inkMuted, fontSize: 13),
              ),
            ],
          ),
        ),
      );
    }

    return RefreshIndicator(
      onRefresh: _loadHistory,
      child: ListView.separated(
        padding: const EdgeInsets.all(16),
        itemCount: _history.length,
        separatorBuilder: (_, _) => const SizedBox(height: 8),
        itemBuilder: (context, index) {
          final record = _history[index];
          return Card(
            child: ListTile(
              contentPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
              leading: Container(
                padding: const EdgeInsets.all(10),
                decoration: BoxDecoration(
                  color: AppColors.primary.withValues(alpha: 0.12),
                  borderRadius: BorderRadius.circular(10),
                ),
                child: const Icon(Icons.lock_clock, color: AppColors.primary, size: 22),
              ),
              title: Text(
                record.date,
                style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 15),
              ),
              subtitle: Text(
                'ইন: ${formatMoney(record.totalIn)} · আউট: ${formatMoney(record.totalOut)}${record.closedByName != null ? '\nক্লোজ করেছেন: ${record.closedByName}' : ''}',
                style: const TextStyle(fontSize: 12, color: AppColors.inkMuted),
              ),
              trailing: Column(
                mainAxisAlignment: MainAxisAlignment.center,
                crossAxisAlignment: CrossAxisAlignment.end,
                children: [
                  const Text('সমাপনী স্থিতি', style: TextStyle(fontSize: 11, color: AppColors.inkMuted)),
                  Text(
                    formatMoney(record.closingBalance),
                    style: const TextStyle(
                      fontWeight: FontWeight.bold,
                      fontSize: 14,
                      color: AppColors.ink,
                    ),
                  ),
                ],
              ),
            ),
          );
        },
      ),
    );
  }

  static String _methodLabel(String method) => switch (method.toLowerCase()) {
        'cash' => 'নগদ (Cash)',
        'bank' => 'ব্যাংক ট্রান্সফার',
        'bkash' => 'বিকাশ (bKash)',
        'nagad' => 'নগদ (Nagad)',
        'rocket' => 'রকেট (Rocket)',
        _ => method,
      };
}

class _MetricCard extends StatelessWidget {
  const _MetricCard({
    required this.title,
    required this.subtitle,
    required this.amount,
    required this.icon,
    required this.color,
    this.isBold = false,
  });

  final String title;
  final String subtitle;
  final double amount;
  final IconData icon;
  final Color color;
  final bool isBold;

  @override
  Widget build(BuildContext context) {
    return Card(
      child: Padding(
        padding: const EdgeInsets.all(14),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                Icon(icon, size: 16, color: color),
                const SizedBox(width: 6),
                Expanded(
                  child: Text(
                    title,
                    style: const TextStyle(
                      fontSize: 12,
                      fontWeight: FontWeight.w600,
                      color: AppColors.inkMuted,
                    ),
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                  ),
                ),
              ],
            ),
            const SizedBox(height: 8),
            Text(
              formatMoney(amount),
              style: TextStyle(
                fontSize: 16,
                fontWeight: isBold ? FontWeight.w800 : FontWeight.w700,
                color: color,
              ),
            ),
            const SizedBox(height: 2),
            Text(
              subtitle,
              style: const TextStyle(fontSize: 11, color: AppColors.inkSubtle),
            ),
          ],
        ),
      ),
    );
  }
}

