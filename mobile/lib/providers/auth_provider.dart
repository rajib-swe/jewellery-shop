import 'dart:async';

import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../core/api/api_exception.dart';
import '../core/api/dio_client.dart';
import '../core/config/app_config.dart';
import '../models/user.dart';
import '../services/auth_service.dart';
import '../services/token_storage.dart';

sealed class AuthState {
  const AuthState();
}

class AuthStarting extends AuthState {
  const AuthStarting();
}

class AuthAuthenticated extends AuthState {
  const AuthAuthenticated(this.user);

  final User user;
}

class AuthUnauthenticated extends AuthState {
  const AuthUnauthenticated({this.reason});

  final String? reason;
}

class AuthUnavailable extends AuthState {
  const AuthUnavailable(this.message);

  final String message;
}

final tokenStorageProvider = Provider<TokenStorage>((ref) => TokenStorage());

final dioProvider = Provider<Dio>((ref) {
  return buildDio(
    baseUrl: AppConfig.apiBaseUrl,
    tokenStorage: ref.watch(tokenStorageProvider),
    onUnauthorized: () async {
      await ref.read(authControllerProvider.notifier).handleUnauthorized();
    },
  );
});

final authServiceProvider = Provider<AuthService>((ref) {
  return AuthService(
    dio: ref.watch(dioProvider),
    tokenStorage: ref.watch(tokenStorageProvider),
  );
});

final authControllerProvider = NotifierProvider<AuthController, AuthState>(
  AuthController.new,
);

class AuthController extends Notifier<AuthState> {
  late final AuthService _service;

  @override
  AuthState build() {
    _service = ref.watch(authServiceProvider);
    unawaited(Future<void>.microtask(restore));

    return const AuthStarting();
  }

  Future<void> restore() async {
    final token = await _service.storedToken();

    if (token == null || token.isEmpty) {
      _emit(const AuthUnauthenticated());
      return;
    }

    try {
      _emit(AuthAuthenticated(await _service.me()));
    } on ApiException catch (error) {
      if (error.isUnauthorized) {
        await _service.forgetToken();
        _emit(const AuthUnauthenticated());
        return;
      }

      _emit(AuthUnavailable(error.message));
    }
  }

  Future<String?> login({required String email, required String password}) async {
    try {
      final session = await _service.login(email: email, password: password);
      _emit(AuthAuthenticated(session.user));
      return null;
    } on ApiException catch (error) {
      return error.fieldError('email') ?? error.message;
    }
  }

  Future<void> logout() async {
    try {
      await _service.logout();
    } on ApiException {
      _emit(const AuthUnauthenticated());
      await _service.forgetToken();
      return;
    }

    await _service.forgetToken();
    _emit(const AuthUnauthenticated());
  }

  Future<void> handleUnauthorized() async {
    if (state is AuthUnauthenticated) {
      return;
    }

    await _service.forgetToken();
    _emit(
      const AuthUnauthenticated(reason: 'আপনার সেশনের মেয়াদ শেষ হয়েছে। আবার প্রবেশ করুন।'),
    );
  }

  void _emit(AuthState next) {
    if (ref.mounted) {
      state = next;
    }
  }
}