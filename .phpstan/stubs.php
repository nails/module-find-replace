<?php

function userHasPermission(mixed $permission, mixed $user = null): bool
{
    return $permission !== null;
}

function activeUser(string $keys = '', string $delimiter = ' '): mixed
{
    return null;
}

function redirect(
    string $url = '',
    ?string $method = null,
    ?int $httpResponseCode = null,
    ?bool $allowExternal = null,
): void {
}

function show404(bool $logError = false): void
{
}

function unauthorised(?string $flashMessage = null, ?string $returnUrl = null, bool $logError = true): void
{
}
