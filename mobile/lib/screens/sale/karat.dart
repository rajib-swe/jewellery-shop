import 'package:flutter/material.dart';

const karatOptions = [18, 21, 22, 24];

class KaratChoiceField extends StatelessWidget {
  const KaratChoiceField({
    super.key,
    required this.selected,
    required this.onChanged,
  });

  final int selected;
  final ValueChanged<int> onChanged;

  @override
  Widget build(BuildContext context) {
    return InputDecorator(
      decoration: const InputDecoration(
        labelText: 'ক্যারেট',
        prefixIcon: Icon(Icons.token_outlined),
      ),
      child: Wrap(
        spacing: 8,
        children: [
          for (final karat in karatOptions)
            ChoiceChip(
              label: Text('$karat K'),
              selected: selected == karat,
              onSelected: (_) => onChanged(karat),
            ),
        ],
      ),
    );
  }
}
