import 'package:flutter_test/flutter_test.dart';
import 'package:goldapp_mobile/core/utils/weight.dart';

void main() {
  group('fixed ratios', () {
    test('matches the ratios shared with the backend', () {
      expect(Weight.voriInGrams, 11.664);
      expect(Weight.anaPerVori, 16);
      expect(Weight.rotiPerAna, 6);
      expect(Weight.pointPerRoti, 10);
      expect(Weight.anaInGrams, closeTo(0.729, 1e-12));
      expect(Weight.rotiInGrams, closeTo(0.1215, 1e-12));
      expect(Weight.pointInGrams, closeTo(0.01215, 1e-12));
    });

    test('one vori is exactly 16 ana, 96 roti and 960 point', () {
      expect(Weight.gramsToAna(Weight.voriInGrams), closeTo(16, 1e-9));
      expect(Weight.gramsToRoti(Weight.voriInGrams), closeTo(96, 1e-9));
      expect(Weight.gramsToPoint(Weight.voriInGrams), closeTo(960, 1e-9));
    });
  });

  group('grams to vori and back', () {
    const gramsSamples = <double>[
      0,
      0.001,
      0.01215,
      0.1215,
      0.729,
      1,
      2.335,
      9.5,
      10.5,
      11.664,
      12.5,
      123.456,
      999.999,
      12345.678,
    ];

    test('gram to vori round trips without drift', () {
      for (final grams in gramsSamples) {
        expect(
          Weight.voriToGrams(Weight.gramsToVori(grams)),
          closeTo(grams, 1e-9),
          reason: 'round trip drifted for $grams grams',
        );
      }
    });

    test('vori to gram round trips without drift', () {
      const voriSamples = <double>[0, 0.5, 1, 2.25, 16.75, 100, 1234.5678];

      for (final vori in voriSamples) {
        expect(
          Weight.gramsToVori(Weight.voriToGrams(vori)),
          closeTo(vori, 1e-9),
          reason: 'round trip drifted for $vori vori',
        );
      }
    });

    test('converts known values', () {
      expect(Weight.gramsToVori(11.664), 1);
      expect(Weight.gramsToVori(0), 0);
      expect(Weight.voriToGrams(1), 11.664);
      expect(Weight.gramsToVori(1), closeTo(0.085733882030, 1e-12));
      expect(Weight.gramsToAna(11.664), 16);
      expect(Weight.anaToGrams(16), closeTo(11.664, 1e-12));
      expect(Weight.gramsToRoti(11.664), 96);
      expect(Weight.gramsToPoint(11.664), 960);
    });
  });

  group('every smaller unit round trips without drift', () {
    const gramsSamples = <double>[0, 0.003, 0.729, 4.5, 33.21, 511.007];

    test('ana', () {
      for (final grams in gramsSamples) {
        expect(
          Weight.anaToGrams(Weight.gramsToAna(grams)),
          closeTo(grams, 1e-9),
        );
      }
    });

    test('roti', () {
      for (final grams in gramsSamples) {
        expect(
          Weight.rotiToGrams(Weight.gramsToRoti(grams)),
          closeTo(grams, 1e-9),
        );
      }
    });

    test('point', () {
      for (final grams in gramsSamples) {
        expect(
          Weight.pointToGrams(Weight.gramsToPoint(grams)),
          closeTo(grams, 1e-9),
        );
      }
    });
  });

  group('traditional units', () {
    test('grams to traditional exposes all four units', () {
      final traditional = Weight.gramsToTraditional(11.664);

      expect(traditional.vori, 1);
      expect(traditional.ana, 16);
      expect(traditional.roti, 96);
      expect(traditional.point, 960);
    });

    test('one vori eight ana four roti five point', () {
      expect(
        Weight.traditionalToGrams(vori: 1, ana: 8, roti: 4, point: 5),
        closeTo(11.664 + Weight.anaToGrams(8) + 0.486 + 0.06075, 1e-9),
      );
    });

    test('combines discrete quantities like the backend', () {
      expect(
        Weight.traditionalToGrams(vori: 1, ana: 2, roti: 3, point: 4),
        closeTo(13.5351, 1e-12),
      );
    });

    test('each unit expression of a weight converts back to that weight', () {
      const gramsSamples = <double>[0, 0.01215, 0.1215, 5.83, 11.664, 87.4];

      for (final grams in gramsSamples) {
        final t = Weight.gramsToTraditional(grams);
        expect(Weight.voriToGrams(t.vori), closeTo(grams, 1e-9));
        expect(Weight.anaToGrams(t.ana), closeTo(grams, 1e-9));
        expect(Weight.rotiToGrams(t.roti), closeTo(grams, 1e-9));
        expect(Weight.pointToGrams(t.point), closeTo(grams, 1e-9));
      }
    });

    test('whole traditional quantities round trip through grams', () {
      const quantities = <double>[0, 1, 2.5, 16, 99.75, 1000];

      for (final vori in quantities) {
        expect(
          Weight.gramsToVori(Weight.voriToGrams(vori)),
          closeTo(vori, 1e-9),
        );
      }
    });

    test('defaults to zero', () {
      expect(Weight.traditionalToGrams(), 0);
    });
  });

  group('rejects unusable input', () {
    test('negative grams', () {
      expect(() => Weight.gramsToVori(-1), throwsArgumentError);
      expect(() => Weight.gramsToAna(-0.1), throwsArgumentError);
    });

    test('negative traditional units', () {
      expect(() => Weight.voriToGrams(-1), throwsArgumentError);
      expect(() => Weight.pointToGrams(-0.001), throwsArgumentError);
      expect(() => Weight.traditionalToGrams(ana: -1), throwsArgumentError);
    });

    test('non finite input', () {
      expect(() => Weight.gramsToVori(double.nan), throwsArgumentError);
      expect(
        () => Weight.voriToGrams(double.infinity),
        throwsArgumentError,
      );
      expect(
        () => Weight.traditionalToGrams(roti: double.nan),
        throwsArgumentError,
      );
    });
  });
}