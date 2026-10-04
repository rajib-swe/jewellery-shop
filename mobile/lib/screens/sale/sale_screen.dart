import 'package:flutter/material.dart';

import '../../widgets/state_handler.dart';

class SaleScreen extends StatelessWidget {
  const SaleScreen({super.key});

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Sale')),
      body: StateHandlerView.empty(
        icon: Icons.point_of_sale_outlined,
        message: 'Sale entry arrives in Step 4.',
      ),
    );
  }
}