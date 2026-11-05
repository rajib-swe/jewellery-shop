import 'package:flutter_test/flutter_test.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:goldapp_mobile/main.dart';

void main() {
  testWidgets('app boots into the login screen', (WidgetTester tester) async {
    await tester.pumpWidget(const ProviderScope(child: GoldApp()));
    await tester.pump();

    expect(find.byType(GoldApp), findsOneWidget);
  });
}