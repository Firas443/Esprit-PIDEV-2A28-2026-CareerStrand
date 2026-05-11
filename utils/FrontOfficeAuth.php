<?php

function ensureFrontSession(): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
}

function currentFrontUser(): ?array
{
    ensureFrontSession();
    return isset($_SESSION['user']) && is_array($_SESSION['user']) ? $_SESSION['user'] : null;
}

function requireFrontUser(): array
{
    $user = currentFrontUser();
    if ($user === null) {
        header('Location: login.php');
        exit;
    }

    return $user;
}

function frontUserInitials(?array $user): string
{
    $name = trim((string) ($user['fullName'] ?? ''));
    if ($name === '') {
        return 'CS';
    }

    $parts = preg_split('/\s+/', $name);
    $initials = '';
    foreach ($parts as $part) {
        $initials .= strtoupper(substr($part, 0, 1));
        if (strlen($initials) >= 2) {
            break;
        }
    }

    return $initials ?: 'CS';
}

