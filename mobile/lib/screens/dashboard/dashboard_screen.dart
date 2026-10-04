import 'package:flutter/material.dart';

import '../../widgets/state_handler.dart';

class DashboardScreen extends StatelessWidget {
  const DashboardScreen({super.key});

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Dashboard')),
      body: StateHandlerView.empty(
        icon: Icons.dashboard_outlined,
        message: 'Dashboard cards arrive in Step 2.',
      ),
    );
  }
}