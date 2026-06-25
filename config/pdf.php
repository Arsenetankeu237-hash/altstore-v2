<?php
/**
 * config/pdf.php — Génération de PDF (Pro Forma, Bon de Commande...).
 *
 *  Wrapper autour de FPDF avec :
 *   - En-tête automatique : logo + infos entreprise (depuis table `entreprises`)
 *   - Pied de page automatique : numéro de page + mention
 *   - Support UTF-8 via utf8_decode() (accents français OK)
 *
 *  Dépendance : vendor/fpdf/fpdf.php
 */
declare(strict_types=1);

require_once ROOT_PATH . '/vendor/fpdf/fpdf.php';

/**
 * Charge les infos entreprise d'une boutique (pour en-tête PDF).
 */
function pdf_entreprise(int $boutiqueId): array
{
    $ent = fetch_one("SELECT * FROM entreprises WHERE boutique_id = ?", [$boutiqueId]);
    if ($ent) return $ent;

    // Fallback sur les infos de la boutique si l'entreprise n'est pas configurée
    $b = fetch_one("SELECT * FROM boutiques WHERE id = ?", [$boutiqueId]);
    return [
        'logo'              => null,
        'nom_entreprise'    => $b['nom'] ?? 'Mon Entreprise',
        'sigle'             => '',
        'secteur_activite'  => '',
        'nif'               => '',
        'registre_commerce' => '',
        'telephone'         => $b['telephone'] ?? '',
        'email'             => $b['email'] ?? '',
        'adresse'           => $b['adresse'] ?? '',
        'ville'             => $b['ville'] ?? '',
    ];
}

/**
 * Convertit une chaîne UTF-8 vers Latin-1 (compatible FPDF natif).
 * Les caractères non représentables sont retirés.
 * (utf8_decode() est déprécié en PHP 8.2+ — on utilise iconv/mb_convert_encoding.)
 */
function pdf_text(?string $s): string
{
    $s = (string)($s ?? '');
    if ($s === '') return '';
    if (function_exists('iconv')) {
        $conv = @iconv('UTF-8', 'ISO-8859-1//TRANSLIT//IGNORE', $s);
        if ($conv !== false) return $conv;
    }
    if (function_exists('mb_convert_encoding')) {
        $conv = @mb_convert_encoding($s, 'ISO-8859-1', 'UTF-8');
        if ($conv !== false) return $conv;
    }
    // Fallback ultime (suppression des octets non Latin-1)
    return preg_replace('/[\x80-\xFF]/', '?', $s);
}

/**
 * Classe PDF maison : en-tête + pied de page automatiques.
 */
class AltPDF extends FPDF
{
    public array $entreprise = [];
    public string $docTitle = '';
    public string $docNumero = '';
    public string $primaryColor = '13,79,26'; // RGB vert foncé par défaut

    public function setEntreprise(array $ent): void
    {
        $this->entreprise = $ent;
    }

    public function setDoc(string $title, string $numero, string $rgb = '13,79,26'): void
    {
        $this->docTitle  = $title;
        $this->docNumero = $numero;
        $this->primaryColor = $rgb;
    }

    function Header()
    {
        $ent = $this->entreprise;

        // Logo (si défini)
        $logoPath = $ent['logo'] ?? null;
        if ($logoPath) {
            $fullPath = ROOT_PATH . '/' . ltrim($logoPath, '/');
            if (is_file($fullPath)) {
                $ext = strtolower(pathinfo($fullPath, PATHINFO_EXTENSION));
                $type = $ext === 'png' ? 'PNG' : ($ext === 'jpg' || $ext === 'jpeg' ? 'JPG' : null);
                if ($type) {
                    $this->Image($fullPath, 14, 12, 28);
                }
            }
        }

        // Nom entreprise
        $this->SetFont('Helvetica', 'B', 15);
        $this->SetTextColor(20, 20, 20);
        $this->SetX(46);
        $this->Cell(0, 7, pdf_text($ent['nom_entreprise'] ?? ''), 0, 1);

        // Sigle + secteur
        if (!empty($ent['sigle']) || !empty($ent['secteur_activite'])) {
            $this->SetFont('Helvetica', '', 9);
            $this->SetTextColor(110, 110, 110);
            $this->SetX(46);
            $parts = array_filter([$ent['sigle'] ?? '', $ent['secteur_activite'] ?? '']);
            $this->Cell(0, 4.5, pdf_text(implode(' · ', $parts)), 0, 1);
        }

        // Coordonnées (NIF, RC, tél, email, adresse)
        $this->SetFont('Helvetica', '', 8);
        $this->SetTextColor(130, 130, 130);
        $this->SetX(46);
        $coord = [];
        if (!empty($ent['nif']))               $coord[] = 'NIF: ' . $ent['nif'];
        if (!empty($ent['registre_commerce'])) $coord[] = 'RC: ' . $ent['registre_commerce'];
        if (!empty($coord)) $this->Cell(0, 4, pdf_text(implode('  |  ', $coord)), 0, 1);

        $this->SetX(46);
        $contact = [];
        if (!empty($ent['telephone'])) $contact[] = 'Tel: ' . $ent['telephone'];
        if (!empty($ent['email']))     $contact[] = $ent['email'];
        if (!empty($ent['ville']))     $contact[] = pdf_text($ent['ville']);
        if (!empty($contact)) $this->Cell(0, 4, pdf_text(implode('  |  ', $contact)), 0, 1);

        // Ligne de séparation
        $this->Ln(3);
        [$r, $g, $b] = array_map('intval', explode(',', $this->primaryColor));
        $this->SetDrawColor($r, $g, $b);
        $this->SetLineWidth(0.6);
        $this->Line(14, $this->GetY(), 196, $this->GetY());
        $this->Ln(6);

        // Titre du document (centré, encadré coloré)
        $this->SetFillColor($r, $g, $b);
        $this->SetTextColor(255, 255, 255);
        $this->SetFont('Helvetica', 'B', 13);
        $this->Cell(0, 9, pdf_text($this->docTitle . '   -   N° ' . $this->docNumero), 0, 1, 'C', true);

        $this->Ln(4);
        $this->SetTextColor(20, 20, 20);
    }

    function Footer()
    {
        $this->SetY(-18);
        $this->SetFont('Helvetica', 'I', 8);
        $this->SetTextColor(150, 150, 150);
        $ent = $this->entreprise;
        $mention = pdf_text(($ent['nom_entreprise'] ?? '') . ' — Merci de votre confiance.');
        $this->Cell(0, 5, $mention, 0, 0, 'L');
        $this->Cell(0, 5, 'Page ' . $this->PageNo() . '/{nb}', 0, 0, 'R');
    }
}

/**
 * Génère un PDF de document (pro forma ou bon de commande).
 *
 * @param array  $ent        Infos entreprise (sortie de pdf_entreprise())
 * @param string $docTitle   Ex: "PRO FORMA" ou "BON DE COMMANDE"
 * @param string $numero     Numéro du document
 * @param array  $meta       [
 *     'dest_nom' => '', // nom client ou fournisseur
 *     'dest_adresse' => '',
 *     'dest_contact' => '',
 *     'date_doc' => '',
 *     'date_libelle' => 'Date', // 'Date', 'Valable jusqu'au', etc.
 *     'objet' => '',
 *     'notes' => '',
 * ]
 * @param array  $lignes     [['designation','reference','quantite','prix_unitaire','total_ligne'], ...]
 * @param array  $totaux     ['sous_total','tva','remise','total_ttc']
 * @param string $filename   Nom du fichier (sans .pdf)
 * @param string $primaryRgb Couleur RGB 'r,g,b'
 */
function generer_document_pdf(
    array $ent,
    string $docTitle,
    string $numero,
    array $meta,
    array $lignes,
    array $totaux,
    string $filename,
    string $primaryRgb = '13,79,26'
): void {
    $pdf = new AltPDF();
    $pdf->SetAutoPageBreak(true, 22);
    $pdf->AliasNbPages();
    $pdf->setEntreprise($ent);
    $pdf->setDoc($docTitle, $numero, $primaryRgb);
    $pdf->AddPage();

    // ---- Bloc destinataire + infos ----
    $pdf->SetFont('Helvetica', 'B', 10);
    $pdf->SetTextColor(110, 110, 110);
    $pdf->Cell(95, 5, pdf_text('ADRESSE A :'), 0, 0);
    $pdf->SetX(120);
    $pdf->Cell(0, 5, pdf_text($meta['date_libelle'] ?? 'Date :'), 0, 1);
    $pdf->SetTextColor(20, 20, 20);

    $pdf->SetFont('Helvetica', 'B', 11);
    $pdf->MultiCell(95, 5.5, pdf_text($meta['dest_nom'] ?? ''), 0, 'L');

    $pdf->SetFont('Helvetica', '', 9);
    $pdf->SetTextColor(100, 100, 100);
    if (!empty($meta['dest_adresse'])) {
        $pdf->MultiCell(95, 4.5, pdf_text($meta['dest_adresse']), 0, 'L');
    }
    if (!empty($meta['dest_contact'])) {
        $pdf->MultiCell(95, 4.5, pdf_text($meta['dest_contact']), 0, 'L');
    }

    // Date à droite
    $pdf->SetY($pdf->GetY() - (4.5 * (!empty($meta['dest_adresse']) ? 1 : 0) + 4.5 * (!empty($meta['dest_contact']) ? 1 : 0)));
    $pdf->SetXY(120, $pdf->GetY() < 60 ? 60 : $pdf->GetY());
    $pdf->SetFont('Helvetica', 'B', 11);
    $pdf->SetTextColor(20, 20, 20);
    $pdf->Cell(0, 6, pdf_text($meta['date_doc'] ?? ''), 0, 1);

    // Objet
    if (!empty($meta['objet'])) {
        $pdf->Ln(3);
        $pdf->SetFont('Helvetica', 'B', 9);
        $pdf->SetTextColor(110, 110, 110);
        $pdf->Cell(20, 5, 'Objet : ', 0, 0);
        $pdf->SetFont('Helvetica', '', 9);
        $pdf->SetTextColor(40, 40, 40);
        $pdf->MultiCell(0, 5, pdf_text($meta['objet']), 0, 'L');
    }

    $pdf->Ln(4);

    // ---- Tableau des lignes ----
    [$r, $g, $b] = array_map('intval', explode(',', $primaryRgb));
    $pdf->SetFillColor($r, $g, $b);
    $pdf->SetTextColor(255, 255, 255);
    $pdf->SetFont('Helvetica', 'B', 9);

    // En-tête du tableau
    $pdf->Cell(75, 8, pdf_text('Designation'), 1, 0, 'L', true);
    $pdf->Cell(28, 8, pdf_text('Reference'), 1, 0, 'L', true);
    $pdf->Cell(22, 8, pdf_text('Qte'), 1, 0, 'C', true);
    $pdf->Cell(30, 8, pdf_text('P.U. (F)'), 1, 0, 'R', true);
    $pdf->Cell(27, 8, pdf_text('Total (F)'), 1, 1, 'R', true);

    // Lignes
    $pdf->SetTextColor(30, 30, 30);
    $fill = false;
    foreach ($lignes as $i => $l) {
        if ($pdf->GetY() > 250) $pdf->AddPage();
        $pdf->SetFont('Helvetica', '', 9);
        $pdf->SetFillColor(248, 248, 248);
        if ($fill) $pdf->SetFillColor(248, 248, 248);

        $h = 7;
        $designation = pdf_text($l['designation'] ?? '');
        $nbLignesDesig = $pdf->GetStringWidth($designation) > 70 ? 2 : 1;
        $h = max($h, 5.5 * $nbLignesDesig);

        $pdf->Cell(75, $h, $designation, 1, 0, 'L', $fill);
        $pdf->Cell(28, $h, pdf_text($l['reference'] ?? '-'), 1, 0, 'L', $fill);
        $pdf->Cell(22, $h, pdf_text((string)($l['quantite'] ?? 1)), 1, 0, 'C', $fill);
        $pdf->Cell(30, $h, pdf_text(number_format((float)($l['prix_unitaire'] ?? 0), 0, ',', ' ')), 1, 0, 'R', $fill);
        $pdf->Cell(27, $h, pdf_text(number_format((float)($l['total_ligne'] ?? 0), 0, ',', ' ')), 1, 1, 'R', $fill);
        $fill = !$fill;
    }

    // ---- Totaux ----
    $pdf->Ln(3);
    $startY = $pdf->GetY();
    $pdf->SetXY(120, $startY);

    $pdf->SetFont('Helvetica', '', 9);
    $pdf->SetTextColor(60, 60, 60);

    $sousTotal = (float)($totaux['sous_total'] ?? 0);
    $tva       = (float)($totaux['tva'] ?? 0);
    $remise    = (float)($totaux['remise'] ?? 0);
    $total     = (float)($totaux['total_ttc'] ?? 0);

    $pdf->SetX(120);
    $pdf->Cell(46, 7, pdf_text('Sous-total :'), 0, 0, 'L');
    $pdf->Cell(36, 7, pdf_text(number_format($sousTotal, 0, ',', ' ') . ' F'), 0, 1, 'R');

    if ($remise > 0) {
        $pdf->SetX(120);
        $pdf->Cell(46, 7, pdf_text('Remise :'), 0, 0, 'L');
        $pdf->Cell(36, 7, pdf_text('- ' . number_format($remise, 0, ',', ' ') . ' F'), 0, 1, 'R');
    }
    if ($tva > 0) {
        $pdf->SetX(120);
        $pdf->Cell(46, 7, pdf_text('TVA :'), 0, 0, 'L');
        $pdf->Cell(36, 7, pdf_text(number_format($tva, 0, ',', ' ') . ' F'), 0, 1, 'R');
    }

    // Total TTC (en gras, encadré)
    $pdf->SetX(120);
    $pdf->SetFillColor($r, $g, $b);
    $pdf->SetTextColor(255, 255, 255);
    $pdf->SetFont('Helvetica', 'B', 11);
    $pdf->Cell(46, 10, pdf_text('TOTAL TTC :'), 0, 0, 'L', true);
    $pdf->Cell(36, 10, pdf_text(number_format($total, 0, ',', ' ') . ' F'), 0, 1, 'R', true);

    // ---- Notes ----
    $pdf->Ln(6);
    if (!empty($meta['notes'])) {
        $pdf->SetTextColor(120, 120, 120);
        $pdf->SetFont('Helvetica', 'I', 8);
        $pdf->MultiCell(0, 4.5, pdf_text('Notes : ' . $meta['notes']), 0, 'L');
    }

    // Mention bas de page
    $pdf->Ln(2);
    $pdf->SetTextColor(150, 150, 150);
    $pdf->SetFont('Helvetica', 'I', 8);
    $pdf->MultiCell(0, 4, pdf_text('Document généré le ' . date('d/m/Y') . ' par ALT STORE ERP.'), 0, 'C');

    // ---- Sortie ----
    $pdf->Output('D', $filename . '.pdf');
    exit;
}
