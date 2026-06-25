<?php
/**
 * api/proforma_pdf.php — Génération PDF d'un Pro Forma.
 *
 *  Paramètre GET : id (proforma ID)
 *  Télécharge le PDF dans le navigateur.
 */
require_once __DIR__ . '/../config/bootstrap.php';
require_boutique();

$id = (int)($_GET['id'] ?? 0);
$bid = active_boutique_id();

$p = fetch_one(
    "SELECT p.*, c.nom AS client_nom, c.entreprise AS client_ent, c.telephone AS client_tel, c.adresse AS client_adr, c.ville AS client_ville
     FROM proformas p
     LEFT JOIN clients c ON c.id = p.client_id
     WHERE p.id = ? AND p.boutique_id = ?",
    [$id, $bid]
);

if (!$p) {
    http_response_code(404);
    die('Pro Forma introuvable.');
}

// Lignes
$lignes = fetch_all(
    "SELECT * FROM proforma_lignes WHERE proforma_id = ? ORDER BY id",
    [$id]
);

// Infos entreprise
$ent = pdf_entreprise($bid);

// Construction du meta
$clientNom = $p['client_ent'] ?: ($p['client_nom'] ?: $p['client_libre']);
$clientAdr = [];
if (!empty($p['client_adr'])) $clientAdr[] = $p['client_adr'];
if (!empty($p['client_ville'])) $clientAdr[] = $p['client_ville'];
$clientContact = $p['client_tel'] ?? '';

generer_document_pdf(
    entreprise: $ent,
    docTitle: 'PRO FORMA',
    numero: $p['numero'],
    meta: [
        'dest_nom'     => $clientNom,
        'dest_adresse' => implode(', ', $clientAdr),
        'dest_contact' => $clientContact,
        'date_doc'     => fdate($p['date_creation']),
        'date_libelle' => 'Date création :',
        'objet'        => '',
        'notes'        => $p['notes'] ?? '',
    ],
    lignes: $lignes,
    totaux: [
        'sous_total' => $p['sous_total'],
        'remise'     => $p['remise_montant'],
        'tva'        => $p['montant_tva'],
        'total_ttc'  => $p['total_ttc'],
    ],
    filename: 'ProForma_' . $p['numero'],
    primaryRgb: '54,132,235' // Bleu pour pro forma
);
