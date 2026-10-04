import '../core/utils/parsing.dart';

class User {
  const User({
    required this.id,
    required this.name,
    required this.email,
    required this.roles,
    required this.permissions,
  });

  final int id;
  final String name;
  final String email;
  final List<String> roles;
  final List<String> permissions;

  String get primaryRole => roles.isEmpty ? '' : roles.first;

  bool can(String permission) => permissions.contains(permission);

  bool get isAdminOrManager =>
      roles.contains('admin') || roles.contains('manager');

  factory User.fromJson(Map<String, dynamic> json) => User(
    id: asInt(json['id']) ?? 0,
    name: asString(json['name']) ?? '',
    email: asString(json['email']) ?? '',
    roles: asStringList(json['roles']),
    permissions: asStringList(json['permissions']),
  );
}