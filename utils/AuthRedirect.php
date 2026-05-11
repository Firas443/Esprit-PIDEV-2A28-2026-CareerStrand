<?php

function normalizedRole(?string $role): string
{
    return strtolower(trim((string) $role));
}

function isBackOfficeRole(?string $role): bool
{
    return normalizedRole($role) === 'admin';
}

function redirectForRole(?string $role, bool $absolute = false): string
{
    if ($absolute) {
        return isBackOfficeRole($role)
            ? '/Careerstrand/View/BackOffice/admin-dashboard.php'
            : '/Careerstrand/View/FrontOffice/home.php';
    }

    return isBackOfficeRole($role)
        ? '../BackOffice/admin-dashboard.php'
        : 'home.php';
}
?>
