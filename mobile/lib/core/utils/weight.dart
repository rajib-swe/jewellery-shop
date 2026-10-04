class TraditionalWeight {
  const TraditionalWeight({
    required this.vori,
    required this.ana,
    required this.roti,
    required this.point,
  });

  final double vori;
  final double ana;
  final double roti;
  final double point;
}

class Weight {
  const Weight._();

  static const double voriInGrams = 11.664;
  static const int anaPerVori = 16;
  static const int rotiPerAna = 6;
  static const int pointPerRoti = 10;

  static const double anaInGrams = voriInGrams / anaPerVori;
  static const double rotiInGrams = anaInGrams / rotiPerAna;
  static const double pointInGrams = rotiInGrams / pointPerRoti;

  static const int precision = 12;

  static double gramsToVori(double grams) => _fromGrams(grams, voriInGrams);

  static double voriToGrams(double vori) => _toGrams(vori, voriInGrams, 'vori');

  static double gramsToAna(double grams) => _fromGrams(grams, anaInGrams);

  static double anaToGrams(double ana) => _toGrams(ana, anaInGrams, 'ana');

  static double gramsToRoti(double grams) => _fromGrams(grams, rotiInGrams);

  static double rotiToGrams(double roti) => _toGrams(roti, rotiInGrams, 'roti');

  static double gramsToPoint(double grams) => _fromGrams(grams, pointInGrams);

  static double pointToGrams(double point) => _toGrams(point, pointInGrams, 'point');

  static TraditionalWeight gramsToTraditional(double grams) => TraditionalWeight(
    vori: gramsToVori(grams),
    ana: gramsToAna(grams),
    roti: gramsToRoti(grams),
    point: gramsToPoint(grams),
  );

  static double traditionalToGrams({
    double vori = 0,
    double ana = 0,
    double roti = 0,
    double point = 0,
  }) => _normalize(
    voriToGrams(vori) +
        anaToGrams(ana) +
        rotiToGrams(roti) +
        pointToGrams(point),
  );

  static double _fromGrams(double grams, double gramsPerUnit) =>
      _normalize(_parseWeight(grams, 'grams') / gramsPerUnit);

  static double _toGrams(double value, double gramsPerUnit, String unit) =>
      _normalize(_parseWeight(value, unit) * gramsPerUnit);

  static double _parseWeight(double value, String unit) {
    if (value.isNaN || value.isInfinite || value < 0) {
      throw ArgumentError.value(
        value,
        unit,
        'Weight cannot be negative or non-finite',
      );
    }

    return value;
  }

  static double _normalize(double value) =>
      double.parse(value.toStringAsFixed(precision));
}