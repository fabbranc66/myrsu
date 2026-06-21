<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$templates = $root . '/ui/templates';
$ui = $root . '/ui';

function tpl(string $path): string
{
    $content = file_get_contents($path);

    if ($content === false) {
        throw new RuntimeException('Template not found: ' . $path);
    }

    return $content;
}

function fill(string $template, array $vars): string
{
    return preg_replace_callback('/{{\s*([a-z_]+)\s*}}/i', static function (array $match) use ($vars): string {
        return (string)($vars[$match[1]] ?? '');
    }, $template) ?? $template;
}

$layout = tpl($templates . '/layout.tpl');
$header = tpl($templates . '/partials/header.tpl');
$menu = tpl($templates . '/partials/menu.tpl');
$users = tpl($templates . '/pages/users.tpl');

$menuHtml = fill($menu, [
    'dashboard_class' => '',
    'anagraphics_group_class' => ' active',
    'users_class' => ' class="active"',
    'profile_class' => '',
    'protocol_group_class' => '',
    'protocol_class' => '',
    'documents_group_class' => '',
    'documents_class' => '',
]);

$headerHtml = fill($header, [
    'heading' => 'Anagrafica utenti',
    'menu' => $menuHtml,
]);

$html = fill($layout, [
    'title' => 'MyRSU Users',
    'styles' => implode("\n  ", [
        '<link rel="stylesheet" href="menu.css">',
        '<link rel="stylesheet" href="users.css">',
    ]),
    'header' => $headerHtml,
    'content' => $users,
    'scripts' => implode("\n  ", [
        '<script src="icons.js"></script>',
        '<script src="user-row.js"></script>',
        '<script src="users-helpers.js"></script>',
        '<script src="users.js"></script>',
        '<script src="auth-menu.js"></script>',
    ]),
]);

file_put_contents($ui . '/users.html', $html);
echo "Rendered ui/users.html\n";
