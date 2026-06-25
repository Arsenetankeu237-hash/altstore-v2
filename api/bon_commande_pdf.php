<?php
/**
 * api/bon_commande_pdf.php — Génération PDF d'un Bon de Commande.
 *
 *  Paramètre GET : id (bon_commande ID)
 */
require_once __DIR__ . '/../config/bootstrap.php';
require_boutique();

$id = (int)($_GET['id'] ?? 0);
$bid = active_boutique_id();

$b = fetch_one(
    "SELECT bc.*, f.nom AS fournisseur_nom, f.telephone AS fournisseur_tel, f.adresse AS fournisseur_adr, f.ville AS fournisseur_ville
     FROM bon_commandes bc
     LEFT JOIN fournisseurs f ON f.id = bc.fournisseur_id
     WHERE bc.id = ? AND bc.boutique_id = ?",
    [$id, $bid]
);

if (!$b) {
    http_response_code(404);
    die('Bon de commande introuvable.');
}

$lignes = fetch_all("SELECT * FROM bon_commande_lignes WHERE bon_commande_id = ? ORDER BY id", [$id]);
$ent = pdf_entreprise($bid);

$fournisseurNom = $b['fournisseur_nom'] ?: $b['fournisseur_libre'];
$fournisseurAdr = [];
if (!empty($b['fournisseur_adr'])) $fournisseurAdr[] = $b['fournisseur_adr'];
if (!empty($b['fournisseur_ville'])) $fournisseurAdr[] = $b['fournisseur_ville'];

generer_document_pdf(
    entreprise: $ent,
    docTitle: 'BON DE COMMANDE',
    numero: $b['numero'],
    meta: [
        'dest_nom'     => $fournisseurNom,
        'dest_adresse' => implode(', ', $fournisseurAdr),
        'dest_contact' => $b['fournisseur_tel'] ?? '',
        'date_doc'     => fdate($b['date_commande']),
        'date_libelle' => 'Date commande :',
        'objet'        => !empty($b['date_livraison_prevue']) ? 'Livraison prévue : ' . fdate($b['date_livraison_prevue']) : '',
        'notes'        => $b['notes'] ?? '',
    ],
    lignes: $lignes,
    totaux: [
        'sous_total' => $b['sous_total'],
        'remise'     => $b['remise_montant'],
        'tva'        => $b['montant_tva'],
        'total_ttc'  => $b['total_ttc'],
    ],
    filename: 'BonCommande_' . $b['numero'],
    primaryRgb: '13,79,26' // Vert pour bon de commande
);
