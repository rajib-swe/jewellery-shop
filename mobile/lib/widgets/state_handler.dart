import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

class StateHandler<T> extends StatelessWidget {
  const StateHandler({
    super.key,
    required this.value,
    required this.onRetry,
    required this.builder,
    this.isEmpty,
    this.emptyMessage = 'এখনও কোনো তথ্য নেই',
    this.emptyIcon = Icons.inbox_outlined,
    this.loadingMessage,
  });

  final AsyncValue<T> value;
  final VoidCallback onRetry;
  final Widget Function(BuildContext context, T data) builder;
  final bool Function(T data)? isEmpty;
  final String emptyMessage;
  final IconData emptyIcon;
  final String? loadingMessage;

  @override
  Widget build(BuildContext context) {
    return value.when(
      data: (data) =>
          isEmpty?.call(data) == true
              ? StateHandlerView.empty(
                message: emptyMessage,
                icon: emptyIcon,
              )
              : builder(context, data),
      loading: () => StateHandlerView.loading(message: loadingMessage),
      error: (error, _) => StateHandlerView.error(
        message: error.toString(),
        onRetry: onRetry,
      ),
    );
  }
}

class StateHandlerView extends StatelessWidget {
  const StateHandlerView._({
    required this.child,
    this.onRetry,
  });

  factory StateHandlerView.loading({String? message}) => StateHandlerView._(
    child: _LoadingBody(message: message),
  );

  factory StateHandlerView.error({
    required String message,
    required VoidCallback onRetry,
  }) => StateHandlerView._(
    onRetry: onRetry,
    child: _ErrorBody(message: message, onRetry: onRetry),
  );

  factory StateHandlerView.empty({
    required String message,
    IconData icon = Icons.inbox_outlined,
  }) => StateHandlerView._(
    child: _EmptyBody(message: message, icon: icon),
  );

  final Widget child;
  final VoidCallback? onRetry;

  @override
  Widget build(BuildContext context) {
    if (onRetry != null) {
      return RefreshIndicator(onRefresh: () async => onRetry!(), child: child);
    }

    return child;
  }
}

class _LoadingBody extends StatelessWidget {
  const _LoadingBody({this.message});

  final String? message;

  @override
  Widget build(BuildContext context) {
    return Center(
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          const CircularProgressIndicator(),
          if (message != null) ...[
            const SizedBox(height: 16),
            Text(message!, style: Theme.of(context).textTheme.bodyMedium),
          ],
        ],
      ),
    );
  }
}

class _EmptyBody extends StatelessWidget {
  const _EmptyBody({required this.message, required this.icon});

  final String message;
  final IconData icon;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);

    return ListView(
      physics: const AlwaysScrollableScrollPhysics(),
      children: [
        SizedBox(height: MediaQuery.sizeOf(context).height * 0.18),
        Icon(icon, size: 48, color: theme.colorScheme.onSurfaceVariant.withValues(alpha: 0.5)),
        const SizedBox(height: 16),
        Text(
          message,
          textAlign: TextAlign.center,
          style: theme.textTheme.bodyMedium?.copyWith(
            color: theme.colorScheme.onSurfaceVariant,
          ),
        ),
      ],
    );
  }
}

class _ErrorBody extends StatelessWidget {
  const _ErrorBody({required this.message, required this.onRetry});

  final String message;
  final VoidCallback onRetry;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);

    return ListView(
      physics: const AlwaysScrollableScrollPhysics(),
      children: [
        SizedBox(height: MediaQuery.sizeOf(context).height * 0.18),
        Icon(Icons.cloud_off_outlined, size: 48, color: theme.colorScheme.error),
        const SizedBox(height: 16),
        Padding(
          padding: const EdgeInsets.symmetric(horizontal: 32),
          child: Text(
            message,
            textAlign: TextAlign.center,
            style: theme.textTheme.bodyMedium,
          ),
        ),
        const SizedBox(height: 24),
        Padding(
          padding: const EdgeInsets.symmetric(horizontal: 48),
          child: FilledButton.tonalIcon(
            onPressed: onRetry,
            icon: const Icon(Icons.refresh),
            label: const Text('আবার চেষ্টা করুন'),
          ),
        ),
      ],
    );
  }
}