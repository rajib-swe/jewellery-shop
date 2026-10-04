import 'package:flutter/material.dart';

import '../../widgets/state_handler.dart';

class CustomersScreen extends StatelessWidget {
  const CustomersScreen({super.key});

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Customers')),
      body: StateHandlerView.empty(
        icon: Icons.people_alt_outlined,
        message: 'Customer search arrives in Step 2.',
      ),
    );
  }
}