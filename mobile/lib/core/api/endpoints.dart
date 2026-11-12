abstract final class Endpoints {
  static const String login = '/login';
  static const String logout = '/logout';
  static const String me = '/me';

  static const String settings = '/settings';
  static const String dashboard = '/reports/dashboard';

  static const String goldRates = '/gold-rates';
  static const String latestGoldRates = '/gold-rates/latest';

  static const String customers = '/customers';
  static const String categories = '/categories';
  static const String items = '/items';
  static const String stockSummary = '/stock/summary';

  static const String sales = '/sales';
  static const String pawns = '/pawns';

  static const String dailyClosings = '/daily-closings';
  static const String dailyClosingShow = '/daily-closings/show';
  static const String migrateDatabase = '/settings/migrate';
  static String dailyClosingReopen(int id) => '/daily-closings/$id/reopen';

  static String customer(int id) => '/customers/$id';

  static String customerHistory(int id) => '/customers/$id/history';

  static String item(int id) => '/items/$id';

  static String stockAdjustment(int id) => '/items/$id/stock-adjustments';

  static String sale(int id) => '/sales/$id';

  static String saleVoid(int id) => '/sales/$id/void';

  static String salePayments(int id) => '/sales/$id/payments';

  static String pawn(int id) => '/pawns/$id';

  static String pawnPayments(int id) => '/pawns/$id/payments';

  static String pawnRedeem(int id) => '/pawns/$id/redeem';

  static String pawnRenew(int id) => '/pawns/$id/renew';

  static String pawnForfeit(int id) => '/pawns/$id/forfeit';
}
