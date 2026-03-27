<?php

function audit_log(PDO $pdo, int $user_id, string $action, string $detail = ''): void {
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    $pdo->prepare("INSERT INTO audit_logs (user_id, action, detail, ip, date) VALUES (?, ?, ?, ?, NOW())")
        ->execute([$user_id, $action, $detail, $ip]);
}

function get_categories(): array {
    return [
        'Ordonnances',
        'Radios / Imagerie',
        'Analyses / Biologie',
        'Vaccins',
        'Comptes-rendus',
        'Autres',
    ];
}

function category_color(string $cat): string {
    $colors = [
        'Ordonnances'         => '#8f5fff',
        'Radios / Imagerie'   => '#1e90ff',
        'Analyses / Biologie' => '#00c875',
        'Vaccins'             => '#ff9800',
        'Comptes-rendus'      => '#e91e63',
        'Autres'              => '#607d8b',
    ];
    return $colors[$cat] ?? '#607d8b';
}
