import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';

import 'package:simalas_mobile/main.dart';

void main() {
  testWidgets('SIMALAS app shows auth landing', (WidgetTester tester) async {
    await tester.pumpWidget(
      MaterialApp(
        home: AuthScreen(
          initialBaseUrl: defaultApiBaseUrl,
          onAuthenticated:
              ({required baseUrl, required token, required user}) async {},
        ),
      ),
    );
    await tester.pumpAndSettle();

    expect(find.text('IoT SIMALAS'), findsOneWidget);
    expect(find.text('Login'), findsOneWidget);
    expect(find.text('Register'), findsOneWidget);
  });
}
