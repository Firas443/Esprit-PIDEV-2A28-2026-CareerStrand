<?php

require_once __DIR__ . '/FrontOfficeAuth.php';

function frontPeopleMap(PDO $pdo): array
{
    $stmt = $pdo->query(
        "SELECT u.userId, u.fullName, u.role, COALESCE(p.photoUrl, '') AS photoUrl
         FROM Users u
         LEFT JOIN Profile p ON p.userId = u.userId
         ORDER BY u.userId ASC"
    );

    $map = [];
    foreach ($stmt->fetchAll() as $user) {
        $map[(int) ($user['userId'] ?? 0)] = [
            'userId' => (int) ($user['userId'] ?? 0),
            'fullName' => trim((string) ($user['fullName'] ?? '')),
            'role' => trim((string) ($user['role'] ?? 'user')),
            'photoUrl' => trim((string) ($user['photoUrl'] ?? '')),
        ];
    }

    return $map;
}

function frontPersonInitials(string $name): string
{
    $trimmed = trim($name);
    if ($trimmed === '') {
        return 'CS';
    }

    $parts = preg_split('/\s+/', $trimmed) ?: [];
    $initials = '';
    foreach (array_slice($parts, 0, 2) as $part) {
        $initials .= strtoupper(substr($part, 0, 1));
    }

    return $initials !== '' ? $initials : 'CS';
}

function frontPersonRoleClass(?string $role): string
{
    return match (strtolower(trim((string) $role))) {
        'admin' => 'admin',
        'manager' => 'manager',
        default => 'user',
    };
}

function frontPersonProfileUrl(int $userId): string
{
    return $userId > 0 ? 'public_portfolio.php?userId=' . $userId : '#';
}

function frontPersonPhotoUrl(?array $person): string
{
    return trim((string) ($person['photoUrl'] ?? ''));
}

function frontPersonDisplayName(?array $person, string $fallback = 'CareerStrand member'): string
{
    $name = trim((string) ($person['fullName'] ?? ''));
    return $name !== '' ? $name : $fallback;
}
