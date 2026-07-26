<?php
/**
 * Contenu commun de la facture (admin et public)
 * Variables attendues: $facture, $commande, $produits, $client_nom, $client_telephone, $adresse_livraison,
 *   $date_facture_aff, $entreprise_nom, $entreprise_rc, $entreprise_ninea, $entreprise_adresse,
 *   $entreprise_tel1, $entreprise_tel2, $entreprise_site, $entreprise_email
 * $is_public (bool): true = page publique (pas d'actions admin), false = page admin
 * $whatsapp_url (string, optionnel): URL WhatsApp pour le bouton
 * $facture_back_url (string, optionnel): URL du lien "Retour" (ex: details.php?id=5)
 * $facture_back_label (string, optionnel): Libellé du lien Retour (défaut: "Retour à la commande")
 * $facture_bl_statut_libelle (string, optionnel): statut du BL (ex. facture BL)
 * $facture_bl_statut_code (string, optionnel): brouillon | valide (ou ancien paye) — couleur du libellé
 * $facture_show_client_zone (bool, optionnel): zone réservée au client en bas de page (signature)
 * $facture_recap_label_ht_decomp (string, optionnel): libellé ligne « base HT » lorsque TVA non en sus (ex. TOTAL BL, TOTAL DEVIS)
 * $facture_recap_label_total (string, optionnel): libellé ligne total final (ex. TOTAL TTC, TOTAL)
 */
$adresse_livraison = $adresse_livraison ?? '';
$adresse_client_display = isset($adresse_client_display) ? trim((string) $adresse_client_display) : trim((string) ($commande['adresse_client'] ?? ''));
$facture_bl_statut_libelle = isset($facture_bl_statut_libelle) ? (string) $facture_bl_statut_libelle : '';
$facture_bl_statut_code = isset($facture_bl_statut_code) ? (string) $facture_bl_statut_code : '';
$facture_show_client_zone = !empty($facture_show_client_zone);
$facture_recap_label_ht_decomp = isset($facture_recap_label_ht_decomp) && (string) $facture_recap_label_ht_decomp !== ''
    ? (string) $facture_recap_label_ht_decomp
    : (!empty($facture_bl_statut_libelle) ? 'TOTAL BL' : 'TOTAL');
$facture_bl_meta_color = '#2d5690';
if ($facture_bl_statut_libelle !== '') {
    if (in_array($facture_bl_statut_code, ['valide', 'paye'], true)) {
        $facture_bl_meta_color = '#1b5e20';
    } elseif ($facture_bl_statut_code === 'brouillon') {
        $facture_bl_meta_color = '#856404';
    }
}
$facture_est_payee = isset($facture_est_payee)
    ? (bool) $facture_est_payee
    : (!empty($facture['payee']));
if (!isset($facture_numero_affichage) || (string) $facture_numero_affichage === '') {
    $facture_numero_affichage = ($facture_est_payee && !empty($facture['numero_reference_fpl']))
        ? (string) $facture['numero_reference_fpl']
        : (string) ($facture['numero_facture'] ?? '');
} else {
    $facture_numero_affichage = (string) $facture_numero_affichage;
}
$facture_afficher_marquer_payee = !empty($facture_afficher_marquer_payee);
$facture_marquer_payee_confirm = isset($facture_marquer_payee_confirm) && (string) $facture_marquer_payee_confirm !== ''
    ? (string) $facture_marquer_payee_confirm
    : 'Confirmer le paiement ? La facture passera au numéro FPL et sera retirée des devis à suivre.';
$facture_csrf_token = isset($facture_csrf_token) ? (string) $facture_csrf_token : '';
$facture_page_flash_success = isset($facture_page_flash_success) ? (string) $facture_page_flash_success : '';
$facture_page_flash_error = isset($facture_page_flash_error) ? (string) $facture_page_flash_error : '';
$facture_document_type_label = isset($facture_document_type_label) && (string) $facture_document_type_label !== ''
    ? (string) $facture_document_type_label
    : 'FACTURE';
$facture_masquer_meta_solde = isset($facture_masquer_meta_solde) ? (bool) $facture_masquer_meta_solde : true;
$facture_masquer_tva_recap = !empty($facture_masquer_tva_recap);
$facture_recap_label_total = isset($facture_recap_label_total) && (string) $facture_recap_label_total !== ''
    ? (string) $facture_recap_label_total
    : 'TOTAL TTC';
require_once __DIR__ . '/site_url.php';
require_once __DIR__ . '/fiscal_tva.php';
require_once __DIR__ . '/facture_ligne_helpers.php';
$facture_tva_incluse = isset($facture_tva_incluse) ? (bool) $facture_tva_incluse : (!empty($facture['tva_incluse']));
$facture_fiscal_taux = isset($facture_fiscal_taux) && (float) $facture_fiscal_taux > 0
    ? (float) $facture_fiscal_taux
    : ((isset($facture['taux_tva_pourcent']) && (float) $facture['taux_tva_pourcent'] > 0) ? (float) $facture['taux_tva_pourcent'] : fiscal_taux_tva_pourcent());

if (!$facture_tva_incluse) {
    $montant_payer = round((float) ($facture['montant_total'] ?? 0), 2);
    $d_inc = fiscal_decomposer_montant_ttc_inclus($montant_payer, $facture_fiscal_taux);
    $facture_fiscal_ht = $d_inc['montant_ht'];
    $facture_fiscal_tva = $d_inc['montant_tva'];
    $facture_afficher_detail_tva = ($facture_fiscal_taux > 0 && $montant_payer > 0);
} else {
    $facture_fiscal_ht = isset($facture_fiscal_ht) ? $facture_fiscal_ht : ($facture['montant_ht'] ?? null);
    $facture_fiscal_ht = ($facture_fiscal_ht !== null && $facture_fiscal_ht !== '') ? (float) $facture_fiscal_ht : null;
    $facture_fiscal_tva = isset($facture_fiscal_tva) ? $facture_fiscal_tva : ($facture['montant_tva'] ?? null);
    $facture_fiscal_tva = ($facture_fiscal_tva !== null && $facture_fiscal_tva !== '') ? (float) $facture_fiscal_tva : null;
    $facture_afficher_detail_tva = $facture_fiscal_ht !== null && $facture_fiscal_tva !== null;
}
$facture_og_title = 'Facture ' . htmlspecialchars($facture_numero_affichage) . ' - FOUTA POIDS LOURDS';
$facture_og_desc = 'Facture FOUTA POIDS LOURDS - ' . ($entreprise_nom ?? 'FOUTA POIDS LOURDS') . ' - Montant : ' . number_format($facture['montant_total'] ?? 0, 0, ',', ' ') . ' CFA';
$facture_og_image = get_site_base_url() . '/image/logo-fpl.png';
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <?php include __DIR__ . '/favicon.php'; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $facture_og_title; ?></title>
    <meta property="og:title" content="<?php echo htmlspecialchars($facture_og_title); ?>">
    <meta property="og:description" content="<?php echo htmlspecialchars($facture_og_desc); ?>">
    <meta property="og:image" content="<?php echo htmlspecialchars($facture_og_image); ?>">
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="FOUTA POIDS LOURDS">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="/css/facture-lignes.css<?php echo function_exists('asset_version_query') ? asset_version_query() : ''; ?>">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            color: #444;
            background: #f5f5f5;
            padding: 20px;
        }

        .facture-container {
            width: 210mm;
            min-height: 297mm;
            max-width: 210mm;
            margin: 0 auto;
            background: #fff;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
            position: relative;
            display: flex;
            flex-direction: column;
        }

        .facture-sheet-body {
            flex: 1 1 auto;
            display: flex;
            flex-direction: column;
            min-height: 0;
        }

        .facture-footer-wrapper {
            margin-top: auto;
            flex-shrink: 0;
            display: flex;
            flex-direction: column;
        }

        .facture-banner-top {
            height: 28px;
            background: linear-gradient(135deg, rgba(53, 100, 166, 0.25) 0%, rgba(45, 86, 144, 0.2) 50%, rgba(53, 100, 166, 0.2) 100%);
            background-image: repeating-linear-gradient(45deg, transparent, transparent 10px, rgba(53, 100, 166, 0.15) 10px, rgba(53, 100, 166, 0.15) 20px);
        }

        .facture-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            flex-direction: row;
            padding: 18px 32px 16px;
            border-bottom: 1px solid #eee;
        }

        .facture-entreprise {
            display: flex;
            align-items: flex-start;
            gap: 20px;
        }

        .facture-logo {
            width: 100px;
            height: 100px;
            border: 2px solid #3564a6;
            border-radius: 50%;
            overflow: hidden;
            flex-shrink: 0;
        }

        .facture-logo img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .facture-entreprise-info h1 {
            font-size: 24px;
            font-weight: 700;
            color: #000;
            margin-bottom: 8px;
            line-height: 1.25;
        }

        .facture-entreprise-forme-juridique {
            font-style: italic;
            font-weight: 500;
            display: inline-block;
            transform: skewX(-10deg);
            margin-left: 0.15em;
            letter-spacing: 0.03em;
        }

        .facture-entreprise-info p {
            font-size: 10px;
            color: #666;
            margin-bottom: 4px;
        }

        .facture-entreprise-info a {
            color: #3b82f6;
            text-decoration: underline;
        }

        .facture-entreprise-info .tel {
            margin-top: 6px;
            font-size: 10px;
            color: #666;
            line-height: 1.35;
        }

        .facture-meta {
            text-align: right;
        }

        .facture-meta .label {
            font-size: 11px;
            font-weight: 700;
            color: #888;
            text-transform: uppercase;
            margin-bottom: 4px;
        }

        .facture-meta .value {
            font-size: 18px;
            font-weight: 700;
            color: #000;
        }

        .facture-meta-kv {
            margin-top: 6px;
        }

        .facture-meta-kv:first-of-type {
            margin-top: 0;
        }

        .facture-meta-kv .label {
            font-size: 9px;
            margin-bottom: 2px;
        }

        .facture-meta-kv:first-of-type .label.facture-doc-type-label {
            font-size: 20px;
            font-weight: 800;
            letter-spacing: 0.04em;
        }

        .facture-meta-kv .value {
            font-size: 13px;
            line-height: 1.25;
        }

        .facture-meta-kv .value.facture-meta-bl-statut {
            font-weight: 700;
        }

        .facture-meta-kv--total .label {
            font-size: 9px;
            margin-bottom: 2px;
        }

        .facture-meta .solde {
            font-size: 16px;
            color: #3564a6;
            margin-top: 8px;
        }

        .facture-payee-mention {
            font-size: 15px;
            font-weight: 700;
            color: #1b5e20;
            margin-top: 6px;
            letter-spacing: 0.02em;
        }

        .facture-flash-bar {
            max-width: 918px;
            margin: 0 auto 14px;
            padding: 12px 16px;
            border-radius: 10px;
            font-size: 14px;
            font-weight: 600;
        }

        .facture-flash-bar--success {
            background: #e8f5e9;
            border: 1px solid #1b5e20;
            color: #1b5e20;
        }

        .facture-flash-bar--error {
            background: #ffebee;
            border: 1px solid #c62828;
            color: #6a1b1b;
        }

        .facture-actions form.facture-form-marquer-paye {
            display: inline-flex;
            margin: 0;
        }

        .facture-actions .btn-marquer-paye {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 18px;
            border-radius: 10px;
            border: none;
            background: #1b5e20;
            color: #fff;
            font-weight: 600;
            font-size: 14px;
            cursor: pointer;
            font-family: inherit;
        }

        .facture-actions .btn-marquer-paye:hover {
            background: #2e7d32;
        }

        .facture-summary .solde-row.facture-solde-paye-row span {
            color: #1b5e20;
            font-weight: 700;
        }

        .facture-billing {
            padding: 14px 32px;
            border-bottom: 1px solid #eee;
        }

        .facture-billing .label {
            font-size: 11px;
            font-weight: 700;
            color: #888;
            text-transform: uppercase;
            margin-bottom: 3px;
        }

        .facture-billing .client-name {
            font-size: 18px;
            font-weight: 700;
            color: #000;
            margin-bottom: 2px;
        }

        .facture-billing .client-tel {
            font-size: 14px;
            color: #444;
            margin-top: 0;
        }

        .facture-billing .adresse-livraison {
            font-size: 13px;
            color: #555;
            margin-top: 4px;
            line-height: 1.35;
        }

        .facture-table-wrapper {
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }

        .facture-table {
            width: 100%;
            border-collapse: collapse;
        }

        .facture-table th {
            background: #3564a6;
            color: #fff;
            font-size: 12px;
            font-weight: 700;
            text-transform: uppercase;
            padding: 6px 10px;
            text-align: left;
        }

        .facture-table th:last-child,
        .facture-table td:last-child {
            text-align: right;
        }

        .facture-table th:nth-child(3),
        .facture-table td:nth-child(3) {
            text-align: center;
        }

        .facture-table td {
            padding: 6px 10px;
            font-size: 13px;
            border-bottom: 1px solid #f0f0f0;
        }

        .facture-table tr:nth-child(even) td {
            background: rgba(53, 100, 166, 0.06);
        }

        .facture-table tr:nth-child(odd) td {
            background: #fff;
        }

        .facture-footer-section {
            display: flex;
            justify-content: space-between;
            padding: 16px 32px 20px;
            gap: 28px;
        }

        .facture-payment h3 {
            font-size: 14px;
            font-weight: 700;
            margin-bottom: 10px;
            color: #000;
        }

        .facture-payment p {
            font-size: 13px;
            color: #666;
        }

        .facture-summary {
            min-width: 280px;
        }

        .facture-summary .row {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            font-size: 13px;
        }

        .facture-summary .facture-row-tva {
            font-size: 13px;
            color: #4a4a4a;
        }

        .facture-summary .total {
            font-weight: 700;
            font-size: 13px;
            padding-top: 12px;
            border-top: 2px solid #3564a6;
            margin-top: 8px;
        }

        .facture-summary .solde-row {
            background: rgba(53, 100, 166, 0.12);
            padding: 8px 12px;
            margin-top: 8px;
            border-radius: 6px;
            font-weight: 700;
            font-size: 13px;
        }

        .facture-adresse-client .facture-addr-label {
            font-size: 10px;
            font-weight: 700;
            color: #888;
            text-transform: uppercase;
            display: block;
            margin-bottom: 2px;
        }

        .facture-client-zone {
            padding: 10px 32px 14px;
            border-top: 1px solid #eee;
            background: #fafafa;
        }

        .facture-client-zone .facture-client-zone__title {
            font-size: 11px;
            font-weight: 700;
            color: #888;
            text-transform: uppercase;
            margin-bottom: 4px;
            letter-spacing: 0.06em;
        }

        .facture-reglement-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 8px 12px;
            margin-top: 8px;
            border-radius: 6px;
            font-size: 13px;
            font-weight: 700;
        }

        .facture-reglement-row--paye {
            background: rgba(46, 125, 50, 0.12);
            color: #1b5e20;
            border: 1px solid rgba(46, 125, 50, 0.35);
        }

        .facture-banner-bottom {
            height: 20px;
            background: linear-gradient(135deg, rgba(53, 100, 166, 0.3) 0%, rgba(45, 86, 144, 0.2) 50%, rgba(53, 100, 166, 0.25) 100%);
            background-image: repeating-linear-gradient(-45deg, transparent, transparent 10px, rgba(53, 100, 166, 0.2) 10px, rgba(53, 100, 166, 0.2) 20px);
        }

        .facture-footer-entreprise {
            padding: 16px 32px;
            background: linear-gradient(135deg, rgba(53, 100, 166, 0.08) 0%, rgba(45, 86, 144, 0.05) 100%);
            border-top: 2px solid #3564a6;
            margin-top: 0;
        }

        .facture-footer-entreprise-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            font-size: 11px;
            color: #444;
            line-height: 1.5;
        }

        .facture-footer-entreprise-col {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }

        .facture-footer-entreprise-col strong {
            color: #3564a6;
            font-weight: 700;
        }

        .facture-actions {
            display: flex;
            flex-wrap: wrap;
            justify-content: center;
            gap: 10px;
            margin-bottom: 20px;
            padding: 12px 0;
        }

        .facture-actions.facture-actions-top {
            margin-bottom: 20px;
            margin-top: 0;
        }

        .facture-actions.facture-actions-bottom {
            margin-top: 20px;
            margin-bottom: 0;
        }

        .facture-actions a {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 20px;
            background: #3564a6;
            color: #fff;
            text-decoration: none;
            border-radius: 8px;
            font-weight: 600;
            font-size: 14px;
            white-space: nowrap;
        }

        .facture-actions a:hover {
            background: #2d5690;
        }

        .facture-actions a.btn-whatsapp {
            background: #25D366;
        }

        .facture-actions a.btn-whatsapp:hover {
            background: #1da851;
        }

        @media print {
            * {
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }

            html, body {
                margin: 0 !important;
                padding: 0 !important;
                background: #fff !important;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }

            body {
                padding: 0 !important;
            }

            .facture-actions {
                display: none !important;
            }

            .facture-flash-bar {
                display: none !important;
            }

            .facture-container {
                max-width: 100% !important;
                width: 100% !important;
                box-shadow: none !important;
                margin: 0 !important;
                overflow: visible !important;
            }

            .facture-table-wrapper {
                overflow: visible !important;
                max-width: 100% !important;
                width: 100% !important;
                margin: 0 !important;
                -webkit-overflow-scrolling: auto !important;
            }

            .facture-table {
                width: 100% !important;
                max-width: 100% !important;
                min-width: 0 !important;
                table-layout: fixed !important;
                border-collapse: collapse !important;
            }

            .facture-table th,
            .facture-table td {
                padding: 4px 6px !important;
                word-wrap: break-word !important;
                overflow-wrap: break-word !important;
                hyphens: auto !important;
            }

            .facture-table th {
                font-size: 11px !important;
            }

            .facture-table td {
                font-size: 13px !important;
            }

            .facture-table th:nth-child(2),
            .facture-table td:nth-child(2) {
                width: 30% !important;
            }

            .facture-table th:nth-child(3),
            .facture-table td:nth-child(3) {
                width: 10% !important;
            }

            .facture-table th:nth-child(4),
            .facture-table td:nth-child(4) {
                width: 20% !important;
            }

            .facture-table th:nth-child(5),
            .facture-table td:nth-child(5) {
                width: 28% !important;
            }

            .facture-banner-top,
            .facture-banner-bottom {
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }

            .facture-table th {
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }

            .facture-table tr:nth-child(even) td {
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }

            .facture-summary .solde-row {
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }

            .facture-logo img {
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }

            .facture-header {
                flex-direction: row !important;
                padding: 10px 8px 10px !important;
                box-sizing: border-box !important;
            }

            .facture-entreprise {
                flex-direction: row !important;
                min-width: 0 !important;
                flex: 1 1 auto !important;
            }

            .facture-meta {
                flex-shrink: 0 !important;
                max-width: 42% !important;
            }

            .facture-footer-section {
                flex-direction: row !important;
                padding: 10px 8px 12px !important;
                gap: 16px !important;
                box-sizing: border-box !important;
            }

            .facture-summary {
                min-width: 0 !important;
                flex: 0 1 auto !important;
                max-width: 48% !important;
            }

            .facture-billing {
                padding: 8px 8px !important;
                box-sizing: border-box !important;
            }

            .facture-client-zone {
                padding: 8px 8px 10px !important;
            }

            html, body {
                overflow: visible !important;
                width: 100% !important;
                max-width: 100% !important;
            }

            @page {
                size: A4 portrait;
                margin: 0 10mm;
            }

            .facture-sheet-body {
                flex: 1 1 auto !important;
                display: flex !important;
                flex-direction: column !important;
                min-height: 0 !important;
            }

            .facture-footer-wrapper {
                margin-top: auto !important;
                flex-shrink: 0 !important;
                display: flex !important;
                flex-direction: column !important;
            }

            .facture-footer-entreprise {
                position: static !important;
                padding: 10px 20px !important;
                font-size: 9px !important;
                background: #f8f9fa !important;
                border-top: 1px solid #3564a6 !important;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }

            .facture-footer-entreprise-grid {
                font-size: 9px !important;
            }

            .facture-container {
                width: 100% !important;
                max-width: 100% !important;
                min-height: 297mm !important;
                display: flex !important;
                flex-direction: column !important;
                height: auto !important;
            }
        }

        @media (max-width: 992px) {
            .facture-header {
                padding: 16px 20px;
            }

            .facture-billing {
                padding: 12px 20px;
            }

            .facture-footer-section {
                padding: 14px 20px;
            }
        }

        @media (max-width: 768px) {
            body {
                padding: 12px;
            }

            .facture-container {
                box-shadow: 0 2px 10px rgba(0, 0, 0, 0.08);
            }

            .facture-banner-top {
                height: 24px;
            }

            .facture-header {

                gap: 6px;
                padding: 14px 8px;
            }

            .facture-entreprise {
                flex-direction: column;
                gap: 12px;
            }

            .facture-logo {
                width: 70px;
                height: 70px;
            }

            .facture-entreprise-info h1 {
                font-size: 20px;
            }

            .facture-entreprise-info p {
                font-size: 10px;
            }

            .facture-entreprise-info .tel {
                font-size: 10px;
            }

            .facture-meta {
                text-align: left;
            }

            .facture-billing {
                padding: 16px;
            }

            .facture-billing .client-name {
                font-size: 16px;
            }

            .facture-table-wrapper {
                margin: 0 -16px;
            }

            .facture-table {
                font-size: 13px;
                min-width: 400px;
            }

            .facture-table th,
            .facture-table td {
                padding: 6px 8px;
            }

            .facture-footer-section {
                flex-direction: column;
                padding: 20px 16px;
                gap: 20px;
            }

            .facture-summary {
                min-width: auto;
            }

            .facture-banner-bottom {
                height: 18px;
            }

            .facture-actions {
                padding: 8px 0;
                gap: 8px;
            }

            .facture-actions a {
                padding: 10px 16px;
                font-size: 13px;
            }
        }

        @media (max-width: 480px) {
            body {
                padding: 8px;
            }

            .facture-header {
                padding: 16px 8px;
            }

            .facture-billing {
                padding: 12px;
            }

            .facture-footer-section {
                padding: 16px 8px;
            }

            .facture-actions {
                flex-direction: column;
            }

            .facture-actions a {
                width: 100%;
                justify-content: center;
            }
        }
    </style>
</head>

<body>
    <?php if ($facture_page_flash_success !== ''): ?>
        <div class="facture-flash-bar facture-flash-bar--success" role="status"><?php echo htmlspecialchars($facture_page_flash_success); ?></div>
    <?php endif; ?>
    <?php if ($facture_page_flash_error !== ''): ?>
        <div class="facture-flash-bar facture-flash-bar--error" role="alert"><?php echo htmlspecialchars($facture_page_flash_error); ?></div>
    <?php endif; ?>
    <?php if (empty($is_public)): ?>
        <?php
        $back_url = $facture_back_url ?? ('details.php?id=' . (int) ($facture['commande_id'] ?? $facture['devis_id'] ?? 0));
        $back_label = $facture_back_label ?? 'Retour à la commande';
        ?>
        <div class="facture-actions facture-actions-top">
            <a href="<?php echo htmlspecialchars($back_url); ?>"><i class="fas fa-arrow-left"></i> <?php echo htmlspecialchars($back_label); ?></a>
            <a href="javascript:window.print();"><i class="fas fa-print"></i> Imprimer</a>
            <?php if ($facture_afficher_marquer_payee): ?>
                <form method="post" action="" class="facture-form-marquer-paye"
                    onsubmit="return confirm(<?php echo json_encode($facture_marquer_payee_confirm, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>);">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($facture_csrf_token, ENT_QUOTES, 'UTF-8'); ?>">
                    <button type="submit" name="marquer_facture_payee" value="1" class="btn-marquer-paye">
                        <i class="fas fa-check-circle" aria-hidden="true"></i> Marquer comme payé
                    </button>
                </form>
            <?php endif; ?>
            <?php if (!empty($whatsapp_url)): ?>
                <a href="<?php echo htmlspecialchars($whatsapp_url); ?>" target="_blank" rel="noopener noreferrer"
                    class="btn-whatsapp">
                    <i class="fab fa-whatsapp"></i> Envoyer la facture sur WhatsApp
                </a>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <div class="facture-actions facture-actions-top">
            <a href="javascript:window.print();"><i class="fas fa-print"></i> Imprimer</a>
        </div>
    <?php endif; ?>

    <div class="facture-container">
        <div class="facture-sheet-body">
        <div class="facture-banner-top"></div>

        <div class="facture-header">
            <div class="facture-entreprise">
                <div class="facture-logo">
                    <img src="/image/logo-fpl.png" alt="FOUTA POIDS LOURDS"
                        onerror="this.style.background='#fef5f9';this.src='data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22%3E%3Ctext x=%2250%22 y=%2255%22 text-anchor=%22middle%22 font-size=%2240%22%3E🍰%3C/text%3E%3C/svg%3E'">
                </div>
                <div class="facture-entreprise-info">
                    <h1><?php echo htmlspecialchars($entreprise_nom); ?> <span class="facture-entreprise-forme-juridique">SUARL</span></h1>
                    <p><?php echo htmlspecialchars($entreprise_adresse); ?></p>
                    <div class="tel">
                        +221 <?php echo htmlspecialchars($entreprise_tel1); ?><?php if (!empty($entreprise_tel2)): ?> · +221 <?php echo htmlspecialchars($entreprise_tel2); ?><?php endif; ?>
                    </div>
                    <p style="margin-top:6px;">
                        <i class="fas fa-globe" style="font-size:11px; margin-right:4px;"></i>
                        <a href="<?php echo htmlspecialchars($entreprise_site); ?>"
                            target="_blank"><?php echo htmlspecialchars($entreprise_site); ?></a>
                    </p>
                    <p><i class="fas fa-envelope"
                            style="font-size:11px; margin-right:4px;"></i><?php echo htmlspecialchars($entreprise_email); ?>
                    </p>
                </div>
            </div>
            <div class="facture-meta">
                <div class="facture-meta-kv">
                    <div class="label facture-doc-type-label"><?php echo htmlspecialchars($facture_document_type_label); ?></div>
                    <div class="value"><?php echo htmlspecialchars($facture_numero_affichage); ?></div>
                    <?php if ($facture_est_payee && !empty($facture['numero_facture']) && $facture['numero_facture'] !== $facture_numero_affichage): ?>
                    <div class="label" style="margin-top:6px;opacity:0.85;">Ancien n°</div>
                    <div class="value" style="font-size:11px;font-weight:600;color:#666;"><?php echo htmlspecialchars((string) $facture['numero_facture']); ?></div>
                    <?php endif; ?>
                </div>
                <div class="facture-meta-kv">
                    <div class="label">DATE</div>
                    <div class="value"><?php echo htmlspecialchars($date_facture_aff); ?></div>
                </div>
                <?php if (!$facture_masquer_meta_solde): ?>
                <div class="facture-meta-kv facture-meta-kv--total">
                    <div class="label"><?php echo $facture_est_payee ? 'MONTANT' : 'SOLDE DÛ'; ?></div>
                    <div class="solde">XOF <?php echo number_format($facture['montant_total'], 2, ',', ' '); ?> CFA</div>
                    <?php if ($facture_est_payee): ?>
                        <div class="facture-payee-mention">Payé</div>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="facture-billing">
            <div class="label">ADRESSE DE FACTURATION</div>
            <div class="client-name"><?php echo htmlspecialchars($client_nom); ?></div>
            <div class="client-tel">TEL : <?php echo htmlspecialchars($client_telephone); ?>
            </div>
            <?php if ($adresse_client_display !== ''): ?>
                <div class="adresse-livraison facture-adresse-client">
                    <span class="facture-addr-label">Adresse du client</span>
                    <span><i class="fas fa-building" style="font-size:11px; margin-right:4px;"></i><?php echo nl2br(htmlspecialchars($adresse_client_display)); ?></span>
                </div>
            <?php endif; ?>
            <?php if (!empty($adresse_livraison)): ?>
                <div class="adresse-livraison"><i class="fas fa-map-marker-alt"
                        style="font-size:11px; margin-right:4px;"></i><?php echo nl2br(htmlspecialchars($adresse_livraison)); ?>
                </div>
            <?php endif; ?>
        </div>

        <div class="facture-table-wrapper">
            <table class="facture-table">
                <thead>
                    <tr>
                        <th>IMAGE</th>
                        <th>ARTICLE</th>
                        <th>QTÉ</th>
                        <th>PRIX<?php echo $facture_afficher_detail_tva ? ' (HT)' : ''; ?></th>
                        <th>MONTANT<?php echo $facture_afficher_detail_tva ? ' (HT)' : ''; ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($produits as $p): ?>
                        <tr>
                            <td><?php echo facture_ligne_image_cell_html($p); ?></td>
                            <td><?php echo facture_ligne_article_cell_html($p); ?></td>
                            <td><?php
                            $qte_ent = (int) round((float) ($p['quantite'] ?? 0));
                            echo number_format($qte_ent, 0, ',', ' ');
                            ?></td>
                            <td><?php echo number_format((float) ($p['prix_unitaire'] ?? 0), 2, ',', ' '); ?> CFA</td>
                            <td><?php echo number_format((float) ($p['prix_total'] ?? 0), 2, ',', ' '); ?> CFA</td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="facture-footer-section">
            <div class="facture-payment">
                <h3>Information De Paiement</h3>
                <p><?php echo nl2br(htmlspecialchars($commande['notes'] ?? '—')); ?></p>
            </div>
            <div class="facture-summary">
                <?php
                $sous_total_produits = 0;
                foreach ($produits as $p) {
                    $sous_total_produits += (float) ($p['prix_total'] ?? 0);
                }
                $frais_livraison = (float) ($commande['frais_livraison'] ?? 0);
                ?>
                <?php if ($facture_afficher_detail_tva): ?>
                <?php if ($frais_livraison > 0): ?>
                <div class="row">
                    <span>FRAIS DE LIVRAISON (HT)</span>
                    <span><?php echo number_format($frais_livraison, 2, ',', ' '); ?> CFA</span>
                </div>
                <?php endif; ?>
                <?php if ($facture_tva_incluse): ?>
                <?php if (!$facture_masquer_tva_recap): ?>
                <div class="row">
                    <span>TOTAL HT</span>
                    <span><?php echo number_format($facture_fiscal_ht, 2, ',', ' '); ?> CFA</span>
                </div>
                <div class="row facture-row-tva">
                    <span>TVA <?php echo number_format($facture_fiscal_taux, 2, ',', ' '); ?>%</span>
                    <span><?php echo number_format($facture_fiscal_tva, 2, ',', ' '); ?> CFA</span>
                </div>
                <?php endif; ?>
                <div class="row">
                    <span><?php echo htmlspecialchars($facture_recap_label_total); ?></span>
                    <span><?php echo number_format($facture['montant_total'], 2, ',', ' '); ?> CFA</span>
                </div>
                <?php else: ?>
                <?php if (!$facture_masquer_tva_recap): ?>
                <div class="row">
                    <span><?php echo htmlspecialchars($facture_recap_label_ht_decomp); ?></span>
                    <span><?php echo number_format($facture_fiscal_ht, 2, ',', ' '); ?> CFA</span>
                </div>
                <div class="row facture-row-tva">
                    <span>TVA <?php echo number_format($facture_fiscal_taux, 2, ',', ' '); ?>%</span>
                    <span><?php echo number_format($facture_fiscal_tva, 2, ',', ' '); ?> CFA</span>
                </div>
                <?php endif; ?>
                <div class="row">
                    <span><?php echo htmlspecialchars($facture_recap_label_total); ?></span>
                    <span><?php echo number_format($facture['montant_total'], 2, ',', ' '); ?> CFA</span>
                </div>
                <?php endif; ?>
                <?php else: ?>
                <?php if ($frais_livraison > 0): ?>
                <div class="row">
                    <span>SOUS-TOTAL PRODUITS</span>
                    <span><?php echo number_format($sous_total_produits, 2, ',', ' '); ?> CFA</span>
                </div>
                <div class="row">
                    <span>FRAIS DE LIVRAISON</span>
                    <span><?php echo number_format($frais_livraison, 2, ',', ' '); ?> CFA</span>
                </div>
                <?php endif; ?>
                <div class="row">
                    <span>TOTAL</span>
                    <span><?php echo number_format($facture['montant_total'], 2, ',', ' '); ?> CFA</span>
                </div>
                <?php endif; ?>
                <?php if ($facture_est_payee): ?>
                <div class="row solde-row facture-solde-paye-row">
                    <span>Payé</span>
                    <span>XOF <?php echo number_format($facture['montant_total'], 2, ',', ' '); ?> CFA</span>
                </div>
                <?php else: ?>
                <div class="row solde-row">
                    <span>SOLDE DÛ</span>
                    <span>XOF <?php echo number_format($facture['montant_total'], 2, ',', ' '); ?> CFA</span>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <?php if ($facture_show_client_zone): ?>
        <div class="facture-client-zone">
            <div class="facture-client-zone__title">Client</div>
        </div>
        <?php endif; ?>
        </div>

        <div class="facture-footer-wrapper">
            <div class="facture-footer-entreprise">
                <div class="facture-footer-entreprise-grid">
                    <div class="facture-footer-entreprise-col">
                        <div><strong>Siège Social :</strong> Rond-Point Zac Mbao</div>
                        <div><strong>Capital :</strong> 10 000 000 FCFA</div>
                        <div><strong>RCCM :</strong> SN DKR2018B4276</div>
                        <div><strong>NINEA :</strong> 006705654/2A2</div>
                    </div>
                    <div class="facture-footer-entreprise-col" style="text-align: right;">
                        <div><strong>Banque :</strong> BOA</div>
                        <div><strong>IBAN :</strong> SN 100 01026 002822180000 88</div>
                        <div style="margin-top: 4px; font-style: italic; color: #3564a6;">Merci pour votre confiance</div>
                    </div>
                </div>
            </div>
            <div class="facture-banner-bottom"></div>
        </div>
    </div>
</body>

</html>