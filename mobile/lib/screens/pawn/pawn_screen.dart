import 'package:flutter/material.dart';

import '../../widgets/state_handler.dart';

class PawnScreen extends StatelessWidget {
  const PawnScreen({super.key});

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Pawn')),
      body: StateHandlerView.empty(
        icon: Icons.payments_outlined,
        message: 'Pawn accounts arrive in Step 4.',
      ),
    );
  }
}