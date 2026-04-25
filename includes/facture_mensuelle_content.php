<?php
/**
 * Facture mensuelle HT (B2B — regroupement de bons de livraison)
 * Variables attendues :
 *   $facture (numero_facture, montant_total = total HT)
 *   $detail_bls : array de [ 'bl' => row BL, 'lignes' => lignes bl_lignes ]
 *   $periode_label (ex. "mars 2026"), $statut_fm_label (ex. "Brouillon")
 *   $client_nom, $client_telephone, $adresse_livraison
 *   $date_facture_aff, $entreprise_*
 *   $is_public (bool), $whatsapp_url (optionnel)
 *   $facture_back_url, $facture_back_label
 *   $fm_show_validate (bool), $facture_mensuelle_id (int), $admin_csrf_token (string)
 *   $notes_facture (string, optionnel) — texte bloc paiement
 *   $fm_flash_success (string, optionnel) — message après validation
 *   $facture_show_client_zone (bool, optionnel) — zone « Client » en bas
 *   $fm_statut (string, optionnel) — brouillon | validee | payee
 *   Une FM « validée » est affichée comme une facture réglée (même rendu que payee).
 */
$adresse_livraison = $adresse_livraison ?? '';
$facture_show_client_zone = !empty($facture_show_client_zone);
$fm_statut = isset($fm_statut) ? (string) $fm_statut : '';
$fm_affiche_comme_reglee = in_array($fm_statut, ['validee', 'payee'], true);
$notes_facture = $notes_facture ?? 'Montants exprimés en HT (hors TVA), conformément aux bons de livraison référencés.';
$detail_bls = isset($detail_bls) && is_array($detail_bls) ? $detail_bls : [];
$fm_flash_success = $fm_flash_success ?? null;
require_once __DIR__ . '/site_url.php';
$facture_og_title = 'Facture ' . htmlspecialchars($facture['numero_facture'] ?? '') . ' - FOUTA POIDS LOURDS';
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
            max-width: 918px;
            margin: 0 auto;
            background: #fff;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
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
            font-size: 28px;
            font-weight: 700;
            color: #000;
            margin-bottom: 8px;
        }

        .facture-entreprise-info p {
            font-size: 12px;
            color: #666;
            margin-bottom: 4px;
        }

        .facture-entreprise-info a {
            color: #3b82f6;
            text-decoration: underline;
        }

        .facture-entreprise-info .tel {
            margin-top: 6px;
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

        .facture-meta-kv .value {
            font-size: 13px;
            line-height: 1.25;
        }

        .facture-meta .solde {
            font-size: 16px;
            color: #3564a6;
            margin-top: 8px;
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
            font-size: 11px;
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
            font-size: 14px;
        }

        .facture-summary .total {
            font-weight: 700;
            font-size: 16px;
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
            font-size: 14px;
        }

        .facture-summary .facture-footer-statut-paye {
            justify-content: center;
            font-size: 13px;
            padding: 8px 12px;
            margin-top: 8px;
        }

        .facture-summary .facture-footer-statut-paye span {
            text-align: center;
            width: 100%;
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
            padding: 12px 16px;
            margin-top: 10px;
            border-radius: 6px;
            font-size: 14px;
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

        .facture-bl-sep td {
            background: rgba(53, 100, 166, 0.12) !important;
            font-weight: 700;
            font-size: 12px;
            color: #2d5690;
            padding: 6px 10px !important;
        }

        .facture-actions form {
            display: inline;
        }

        .facture-btn-validate {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 20px;
            background: #0d9488;
            color: #fff;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            font-size: 14px;
            cursor: pointer;
            font-family: inherit;
        }

        .facture-btn-validate:hover {
            background: #0f766e;
        }

        .facture-statut-badge {
            display: inline-block;
            margin-top: 8px;
            padding: 4px 10px;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 700;
            background: rgba(53, 100, 166, 0.15);
            color: #2d5690;
        }

        .facture-statut-badge--payee {
            background: rgba(22, 163, 74, 0.18);
            color: #15803d;
        }

        .facture-statut-badge--validee {
            background: rgba(53, 100, 166, 0.15);
            color: #2d5690;
        }

        .facture-paiement-badge {
            display: inline-block;
            margin-top: 6px;
            margin-left: 0;
            padding: 4px 10px;
            border-radius: 6px;
            font-size: 11px;
            font-weight: 700;
            background: rgba(22, 163, 74, 0.12);
            color: #166534;
        }

        .facture-meta-statuts {
            margin-top: 6px;
            display: flex;
            flex-direction: column;
            align-items: flex-end;
            gap: 4px;
        }

        .facture-meta-kv--total .label {
            font-size: 9px;
            margin-bottom: 2px;
        }

        .facture-summary .solde-row--paye span:last-child {
            color: #15803d;
            font-weight: 700;
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

            .facture-actions form {
                display: none !important;
            }

            .facture-flash-success {
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
                font-size: 10px !important;
                word-wrap: break-word !important;
                overflow-wrap: break-word !important;
                hyphens: auto !important;
            }

            .facture-table th:nth-child(1),
            .facture-table td:nth-child(1) {
                width: 36% !important;
            }

            .facture-table th:nth-child(2),
            .facture-table td:nth-child(2) {
                width: 22% !important;
            }

            .facture-table th:nth-child(3),
            .facture-table td:nth-child(3) {
                width: 12% !important;
            }

            .facture-table th:nth-child(4),
            .facture-table td:nth-child(4) {
                width: 30% !important;
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
                margin: 10mm;
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
                font-size: 22px;
            }

            .facture-entreprise-info p {
                font-size: 11px;
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
    <?php if (!empty($fm_flash_success) && empty($is_public)): ?>
        <div class="facture-flash-success" style="max-width:918px;margin:0 auto 12px;padding:14px 18px;background:rgba(13,148,136,0.12);border:1px solid #0d9488;border-radius:10px;color:#0f766e;font-size:14px;font-weight:600;">
            <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($fm_flash_success); ?>
        </div>
    <?php endif; ?>
    <?php if (empty($is_public)): ?>
        <?php
        $back_url = $facture_back_url ?? ('bl_par_client.php?id=' . (int) ($facture['client_b2b_id'] ?? 0));
        $back_label = $facture_back_label ?? 'Retour';
        ?>
        <div class="facture-actions facture-actions-top">
            <a href="<?php echo htmlspecialchars($back_url); ?>"><i class="fas fa-arrow-left"></i> <?php echo htmlspecialchars($back_label); ?></a>
            <a href="javascript:window.print();"><i class="fas fa-print"></i> Imprimer</a>
            <?php if (!empty($fm_show_validate) && !empty($facture_mensuelle_id) && !empty($admin_csrf_token)): ?>
                <form method="post" action="facture_mensuelle_valider.php" onsubmit="return confirm('Valider cette facture HT ?');">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($admin_csrf_token); ?>">
                    <input type="hidden" name="facture_mensuelle_id" value="<?php echo (int) $facture_mensuelle_id; ?>">
                    <button type="submit" class="facture-btn-validate"><i class="fas fa-check-circle"></i> Valider (comptabilité)</button>
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
        <div class="facture-banner-top"></div>

        <div class="facture-header">
            <div class="facture-entreprise">
                <div class="facture-logo">
                    <img src="/image/logo-fpl.png" alt="FOUTA POIDS LOURDS"
                        onerror="this.style.background='#fef5f9';this.src='data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22%3E%3Ctext x=%2250%22 y=%2255%22 text-anchor=%22middle%22 font-size=%2240%22%3E🍰%3C/text%3E%3C/svg%3E'">
                </div>
                <div class="facture-entreprise-info">
                    <h1><?php echo htmlspecialchars($entreprise_nom); ?></h1>
                    <p>R.C : <?php echo htmlspecialchars($entreprise_rc); ?></p>
                    <p>N.I.N.E.A : <?php echo htmlspecialchars($entreprise_ninea); ?></p>
                    <p><?php echo htmlspecialchars($entreprise_adresse); ?></p>
                    <div class="tel">
                        <i class="fas fa-phone"
                            style="font-size:11px; margin-right:4px;"></i>+221 <?php echo htmlspecialchars($entreprise_tel1); ?><?php if (!empty($entreprise_tel2)): ?><br>
                        <i class="fas fa-phone"
                            style="font-size:11px; margin-right:4px;"></i>+221 <?php echo htmlspecialchars($entreprise_tel2); ?><?php endif; ?>
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
                    <div class="label">FACTURE HT (B2B)</div>
                    <div class="value"><?php echo htmlspecialchars($facture['numero_facture']); ?></div>
                </div>
                <div class="facture-meta-kv">
                    <div class="label">PÉRIODE</div>
                    <div class="value"><?php echo htmlspecialchars($periode_label ?? '—'); ?></div>
                </div>
                <div class="facture-meta-kv">
                    <div class="label">DATE</div>
                    <div class="value"><?php echo htmlspecialchars($date_facture_aff); ?></div>
                </div>
                <?php if (!empty($statut_fm_label)): ?>
                    <div class="facture-meta-statuts">
                        <span class="facture-statut-badge <?php echo $fm_affiche_comme_reglee ? 'facture-statut-badge--payee' : ($fm_statut === 'brouillon' ? 'facture-statut-badge--validee' : ''); ?>"><?php echo htmlspecialchars($statut_fm_label); ?></span>
                        <?php if ($fm_affiche_comme_reglee): ?>
                            <span class="facture-paiement-badge"><i class="fas fa-check-circle" style="margin-right:4px;"></i>Paiement : réglé</span>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
                <div class="facture-meta-kv facture-meta-kv--total">
                    <div class="label">TOTAL HT</div>
                    <div class="solde">XOF <?php echo number_format($facture['montant_total'], 2, ',', ' '); ?> CFA</div>
                </div>
            </div>
        </div>

        <div class="facture-billing">
            <div class="label">ADRESSE DE FACTURATION</div>
            <div class="client-name"><?php echo htmlspecialchars($client_nom); ?></div>
            <div class="client-tel"><i class="fas fa-phone"
                    style="font-size:11px; margin-right:4px;"></i><?php echo htmlspecialchars($client_telephone); ?>
            </div>
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
                        <th>ARTICLE</th>
                        <th>PRIX</th>
                        <th>QTÉ</th>
                        <th>MONTANT</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($detail_bls)): ?>
                        <tr>
                            <td colspan="4" style="text-align:center;color:#666;padding:14px;">Aucune ligne sur cette facture.</td>
                        </tr>
                    <?php endif; ?>
                    <?php foreach ($detail_bls as $block): ?>
                        <?php $blrow = $block['bl'] ?? []; ?>
                        <tr class="facture-bl-sep">
                            <td colspan="4">Bon de livraison <?php echo htmlspecialchars($blrow['numero_bl'] ?? ''); ?>
                                <?php if (!empty($blrow['date_bl'])): ?> · <?php echo htmlspecialchars($blrow['date_bl']); ?><?php endif; ?>
                            </td>
                        </tr>
                        <?php foreach ($block['lignes'] ?? [] as $ligne): ?>
                        <?php
                        $qte_raw = (float) ($ligne['quantite'] ?? 0);
                        $qte_ent = (int) round($qte_raw);
                        ?>
                        <tr>
                            <td><?php echo htmlspecialchars($ligne['designation'] ?? ''); ?></td>
                            <td><?php echo number_format((float) ($ligne['prix_unitaire_ht'] ?? 0), 2, ',', ' '); ?> CFA</td>
                            <td><?php echo number_format($qte_ent, 0, ',', ' '); ?></td>
                            <td><?php echo number_format((float) ($ligne['total_ligne_ht'] ?? 0), 2, ',', ' '); ?> CFA</td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="facture-footer-section">
            <div class="facture-payment">
                <h3>Information de paiement</h3>
                <p>Facturation professionnelle HT</p>
                <p><?php echo nl2br(htmlspecialchars($notes_facture)); ?></p>
            </div>
            <div class="facture-summary">
                <div class="row total">
                    <span>TOTAL HT</span>
                    <span><?php echo number_format($facture['montant_total'], 2, ',', ' '); ?> CFA</span>
                </div>
                <?php if (!$fm_affiche_comme_reglee): ?>
                <div class="row solde-row">
                    <span>MONTANT HT À RÉGLER</span>
                    <span>XOF <?php echo number_format($facture['montant_total'], 2, ',', ' '); ?> CFA</span>
                </div>
                <?php else: ?>
                <div class="row solde-row solde-row--paye facture-footer-statut-paye">
                    <span>Statut payé</span>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <?php if ($facture_show_client_zone): ?>
        <div class="facture-client-zone">
            <div class="facture-client-zone__title">Client</div>
        </div>
        <?php endif; ?>

        <div class="facture-banner-bottom"></div>
    </div>
</body>

</html>