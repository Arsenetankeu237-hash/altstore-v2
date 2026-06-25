<?php
/**
 * config/permissions.php — Matrice de permissions par rôle.
 *
 *  Principe : un rôle = un sac de permissions (clés string).
 *  Une permission = "{module}.{action}".
 *
 *  Modules   : dashboard, articles, ventes, clients, fournisseurs,
 *              caisse, factures, devis, stocks, personnel, boutiques, rapports
 *  Actions   : view, create, edit, delete, validate, export
 *
 *  Le propriétaire (proprietaire) a TOUJOURS toutes les permissions.
 *  Les autres rôles ont un sous-ensemble défini ici.
 */
declare(strict_types=1);

/** Catalogue des permissions disponibles (pour l'UI d'attribution). */
const PERMISSIONS_CATALOG = [
    'dashboard'  => ['view'],
    'articles'   => ['view', 'create', 'edit', 'delete'],
    'ventes'     => ['view', 'create', 'validate', 'refund', 'delete'],
    'clients'    => ['view', 'create', 'edit', 'delete'],
    'fournisseurs' => ['view', 'create', 'edit', 'delete'],
    'caisse'     => ['view', 'encaisser', 'decaisser', 'cloturer', 'transferer'],
    'factures'   => ['view', 'create', 'edit', 'delete', 'export'],
    'devis'      => ['view', 'create', 'edit', 'delete', 'transformer'],
    'stocks'     => ['view', 'ajuster', 'inventaire'],
    'personnel'  => ['view', 'manage'],
    'boutiques'  => ['view', 'manage'],
    'rapports'   => ['view', 'export'],
    // Modules ajoutés v2
    'proforma'     => ['view', 'create', 'edit', 'delete', 'export'],
    'bon_commande' => ['view', 'create', 'edit', 'delete', 'export'],
    'pipeline'     => ['view', 'manage'],
    'entreprise'   => ['view', 'manage'],
];

/** Définition des permissions accordées par rôle. */
const ROLE_PERMISSIONS = [
    'proprietaire'   => ['*'], // toutes les permissions
    'directeur'      => [
        'dashboard.view',
        'articles.*', 'ventes.*', 'clients.*', 'fournisseurs.*',
        'caisse.*', 'factures.*', 'devis.*', 'stocks.*',
        'personnel.view', 'rapports.*',
        'proforma.*', 'bon_commande.*', 'pipeline.*', 'entreprise.manage',
    ],
    'comptable'      => [
        'dashboard.view',
        'factures.*', 'rapports.*', 'caisse.view',
        'clients.view', 'fournisseurs.view',
        'proforma.view', 'bon_commande.view',
    ],
    'commercial'     => [
        'dashboard.view',
        'ventes.view', 'ventes.create', 'ventes.validate',
        'clients.*', 'devis.*',
        'articles.view', 'caisse.encaisser',
        'proforma.*', 'bon_commande.view', 'pipeline.view',
    ],
    'caissier'       => [
        'dashboard.view',
        'ventes.view', 'ventes.create',
        'caisse.*',
        'clients.view', 'clients.create', 'articles.view',
    ],
    'gestionnaire_stock' => [
        'dashboard.view',
        'articles.*', 'stocks.*', 'fournisseurs.view',
        'bon_commande.view', 'pipeline.view',
    ],
    'employe'        => ['dashboard.view'],
];

/**
 * Toutes les permissions effectives d'un rôle, expansées en liste plate.
 * ex. 'articles.*' -> ['articles.view','articles.create',...]
 */
function role_permissions(string $role): array
{
    $raw = ROLE_PERMISSIONS[$role] ?? [];
    if (in_array('*', $raw, true)) {
        // Toutes les permissions du catalogue
        $all = [];
        foreach (PERMISSIONS_CATALOG as $mod => $actions) {
            foreach ($actions as $a) $all[] = "$mod.$a";
        }
        return $all;
    }

    $out = [];
    foreach ($raw as $perm) {
        if (str_ends_with($perm, '.*')) {
            $mod = substr($perm, 0, -2);
            foreach (PERMISSIONS_CATALOG[$mod] ?? [] as $a) {
                $out[] = "$mod.$a";
            }
        } else {
            $out[] = $perm;
        }
    }
    return $out;
}

/** L'utilisateur courant a-t-il cette permission dans la boutique active ? */
function can(string $permission): bool
{
    $role = current_role();
    if ($role === null) return false;
    if ($role === 'proprietaire') return true;
    return in_array($permission, role_permissions($role), true);
}

/** Garde : exige une permission, sinon 403. */
function require_permission(string $permission): void
{
    require_boutique();
    if (!can($permission)) {
        http_response_code(403);
        die('Accès refusé : permission « ' . htmlspecialchars($permission) . ' » requise.');
    }
}

/** Liste des rôles disponibles (pour les menus déroulants). */
function available_roles(): array
{
    return ['directeur', 'comptable', 'commercial', 'caissier', 'gestionnaire_stock', 'employe'];
}
