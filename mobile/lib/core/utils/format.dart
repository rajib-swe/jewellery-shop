import 'package:intl/intl.dart';

const String _defaultCurrencySymbol = '৳';

final NumberFormat _moneyFormat = NumberFormat('#,##0.00');
final NumberFormat _weightFormat = NumberFormat('0.00##');
final DateFormat _dateFormat = DateFormat('d MMM yyyy');

String formatMoney(num? value, {String symbol = _defaultCurrencySymbol}) {
  return '$symbol${_moneyFormat.format(value ?? 0)}';
}

String formatDate(DateTime? value) {
  return value == null ? '—' : _dateFormat.format(value);
}

String formatWeightInVori(num? grams) {
  final value = grams ?? 0;
  return '${_weightFormat.format(value / 11.664)} vori';
}

String formatIsoDate(DateTime value) {
  final month = value.month.toString().padLeft(2, '0');
  final day = value.day.toString().padLeft(2, '0');

  return '${value.year}-$month-$day';
}
