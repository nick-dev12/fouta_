<?php
/**
 * Contenu facture pour commande personnalisée
 * Variables: $facture, $cp, $client_nom, $client_telephone, $date_facture_aff,
 *   $entreprise_nom, $entreprise_rc, $entreprise_ninea, $entreprise_adresse,
 *   $entreprise_tel1, $entreprise_tel2, $entreprise_site, $entreprise_email,
 *   $is_public, $whatsapp_url
 */
$prix_commande = isset($cp['prix']) && $cp['prix'] !== null && (float) $cp['prix'] > 0 ? (float) $cp['prix'] : 0;
$frais_livraison = isset($cp['zone_prix_livraison']) && (float) $cp['zone_prix_livraison'] > 0 ? (float) $cp['zone_prix_livraison'] : 0;
$montant_facture = (float) ($facture['montant_total'] ?? 0);
$montant_total = $prix_commande + $frais_livraison;
if ($montant_total <= 0 && $montant_facture > 0) {
    $montant_total = $montant_facture;
}
$montant_aff = $montant_total > 0
    ? number_format($montant_total, 0, ',', ' ') . ' CFA'
    : 'À définir';
$prix_commande_aff = $prix_commande > 0 ? number_format($prix_commande, 0, ',', ' ') . ' CFA' : null;
$frais_livraison_aff = $frais_livraison > 0 ? number_format($frais_livraison, 0, ',', ' ') . ' CFA' : null;
$zone_libelle = (!empty($cp['zone_ville']) || !empty($cp['zone_quartier'])) ? trim(($cp['zone_ville'] ?? '') . ' - ' . ($cp['zone_quartier'] ?? ''), ' -') : '';
require_once __DIR__ . '/site_url.php';
$facture_og_title = 'Facture ' . htmlspecialchars($facture['numero_facture'] ?? '') . ' - FOUTA POIDS LOURDS';
$facture_og_desc = 'Facture FOUTA POIDS LOURDS - Demande personnalisée - Montant : ' . $montant_aff;
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
    <link rel="stylesheet" href="/css/admin-dashboard.css<?php echo asset_version_query(); ?>">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; color: #444; background: #f5f5f5; padding: 20px; }
        .facture-container { max-width: 918px; margin: 0 auto; background: #fff; box-shadow: 0 4px 20px rgba(0,0,0,0.1); }
        .facture-banner-top { height: 60px; background: linear-gradient(135deg, rgba(53,100,166,0.25), rgba(45,86,144,0.2)); }
        .facture-header { display: flex; justify-content: space-between; align-items: flex-start; padding: 30px 45px 25px; border-bottom: 1px solid #eee; }
        .facture-entreprise { display: flex; align-items: flex-start; gap: 20px; }
        .facture-logo { width: 100px; height: 100px; border: 2px solid #3564a6; border-radius: 50%; overflow: hidden; flex-shrink: 0; }
        .facture-logo img { width: 100%; height: 100%; object-fit: cover; }
        .facture-entreprise-info h1 { font-size: 28px; font-weight: 700; color: #000; margin-bottom: 8px; }
        .facture-entreprise-info p { font-size: 12px; color: #666; margin-bottom: 4px; }
        .facture-meta { text-align: right; }
        .facture-meta .label { font-size: 11px; font-weight: 700; color: #888; text-transform: uppercase; margin-bottom: 4px; }
        .facture-meta .value { font-size: 18px; font-weight: 700; color: #000; }
        .facture-meta .solde { font-size: 16px; color: #3564a6; margin-top: 8px; }
        .facture-billing { padding: 25px 45px; border-bottom: 1px solid #eee; }
        .facture-billing .label { font-size: 11px; font-weight: 700; color: #888; text-transform: uppercase; margin-bottom: 6px; }
        .facture-billing .client-name { font-size: 18px; font-weight: 700; color: #000; margin-bottom: 4px; }
        .facture-billing .client-tel { font-size: 14px; color: #444; }
        .facture-table { width: 100%; border-collapse: collapse; }
        .facture-table th { background: #3564a6; color: #fff; font-size: 12px; font-weight: 700; padding: 14px 20px; text-align: left; }
        .facture-table td { padding: 14px 20px; font-size: 14px; border-bottom: 1px solid #f0f0f0; }
        .facture-footer-section { display: flex; justify-content: space-between; padding: 25px 45px 30px; gap: 40px; }
        .facture-summary .row { display: flex; justify-content: space-between; padding: 8px 0; font-size: 14px; }
        .facture-summary .total { font-weight: 700; font-size: 16px; padding-top: 12px; border-top: 2px solid #3564a6; margin-top: 8px; }
        .facture-summary .solde-row { background: rgba(53,100,166,0.12); padding: 12px 16px; margin-top: 12px; border-radius: 6px; font-weight: 700; font-size: 16px; }
        .facture-banner-bottom { height: 40px; background: linear-gradient(135deg, rgba(53,100,166,0.3), rgba(45,86,144,0.2)); }
        .facture-actions { display: flex; flex-wrap: wrap; justify-content: center; gap: 10px; margin: 20px 0; }
        .facture-actions a { display: inline-flex; align-items: center; gap: 8px; padding: 10px 20px; background: #3564a6; color: #fff; text-decoration: none; border-radius: 8px; font-weight: 600; font-size: 14px; }
        .facture-actions a:hover { background: #2d5690; }
        .facture-actions a.btn-whatsapp { background: #25D366; }
        .facture-actions a.btn-whatsapp:hover { background: #1da851; }
        .facture-desc { padding: 20px 45px; border-bottom: 1px solid #eee; }
        .facture-desc h4 { font-size: 12px; color: #888; text-transform: uppercase; margin-bottom: 8px; }
        .facture-desc p { font-size: 14px; line-height: 1.6; color: #333; white-space: pre-wrap; }
        .facture-payment h3 { font-size: 14px; font-weight: 700; margin-bottom: 10px; color: #000; }
        .facture-payment p { font-size: 13px; color: #666; }
        @media print { .facture-actions { display: none !important; } }
    </style>
</head>
<body>
    <?php if (empty($is_public)): ?>
        <div class="facture-actions">
            <a href="details.php?id=<?php echo (int) $cp['id']; ?>"><i class="fas fa-arrow-left"></i> Retour à la demande</a>
            <a href="javascript:window.print();"><i class="fas fa-print"></i> Imprimer</a>
            <?php if (!empty($whatsapp_url)): ?>
                <a href="<?php echo htmlspecialchars($whatsapp_url); ?>" target="_blank" rel="noopener noreferrer" class="btn-whatsapp">
                    <i class="fab fa-whatsapp"></i> Envoyer la facture sur WhatsApp
                </a>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <div class="facture-actions">
            <a href="javascript:window.print();"><i class="fas fa-print"></i> Imprimer</a>
        </div>
    <?php endif; ?>

    <div class="facture-container">
        <div class="facture-banner-top"></div>
        <div class="facture-header">
            <div class="facture-entreprise">
                <div class="facture-logo">
                    <img src="/image/logo-fpl.png" alt="FOUTA POIDS LOURDS">
                </div>
                <div class="facture-entreprise-info">
                    <h1><?php echo htmlspecialchars($entreprise_nom); ?></h1>
                    <p>R.C : <?php echo htmlspecialchars($entreprise_rc); ?></p>
                    <p>N.I.N.E.A : <?php echo htmlspecialchars($entreprise_ninea); ?></p>
                    <p><?php echo htmlspecialchars($entreprise_adresse); ?></p>
                    <p><i class="fas fa-phone" style="font-size:11px;"></i> +221 <?php echo htmlspecialchars($entreprise_tel1); ?><?php if (!empty($entreprise_tel2)): ?> / <?php echo htmlspecialchars($entreprise_tel2); ?><?php endif; ?></p>
                    <p><i class="fas fa-globe" style="font-size:11px;"></i> <?php echo htmlspecialchars($entreprise_site); ?></p>
                    <p><i class="fas fa-envelope" style="font-size:11px;"></i> <?php echo htmlspecialchars($entreprise_email); ?></p>
                </div>
            </div>
            <div class="facture-meta">
                <div class="label">DEVIS / FACTURE</div>
                <div class="value"><?php echo htmlspecialchars($facture['numero_facture']); ?></div>
                <div class="label" style="margin-top:12px;">DATE</div>
                <div class="value"><?php echo htmlspecialchars($date_facture_aff); ?></div>
                <div class="label" style="margin-top:12px;">MONTANT</div>
                <div class="solde"><?php echo htmlspecialchars($montant_aff); ?></div>
            </div>
        </div>

        <div class="facture-billing">
            <div class="label">CLIENT</div>
            <div class="client-name"><?php echo htmlspecialchars($client_nom); ?></div>
            <div class="client-tel"><i class="fas fa-phone" style="font-size:11px;"></i> <?php echo htmlspecialchars($client_telephone); ?></div>
            <div class="client-tel" style="margin-top:4px;"><i class="fas fa-envelope" style="font-size:11px;"></i> <?php echo htmlspecialchars($cp['email'] ?? ''); ?></div>
        </div>

        <div class="facture-desc">
            <h4>Description de la demande</h4>
            <p><?php echo nl2br(htmlspecialchars($cp['description'] ?? '')); ?></p>
        </div>

        <div style="padding: 20px 45px;">
            <table class="facture-table">
                <thead>
                    <tr>
                        <th>DÉSIGNATION</th>
                        <th>DÉTAILS</th>
                        <th>MONTANT</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($prix_commande > 0): ?>
                    <tr>
                        <td>Demande personnalisée #<?php echo (int) $cp['id']; ?></td>
                        <td>
                            <?php if (!empty($cp['type_produit'])): ?>Type: <?php echo htmlspecialchars($cp['type_produit']); ?>. <?php endif; ?>
                            <?php if (!empty($cp['quantite'])): ?>Quantité: <?php echo htmlspecialchars($cp['quantite']); ?>. <?php endif; ?>
                            <?php if (!empty($cp['date_souhaitee'])): ?>Date souhaitée: <?php echo date('d/m/Y', strtotime($cp['date_souhaitee'])); ?>.<?php endif; ?>
                        </td>
                        <td><?php echo htmlspecialchars($prix_commande_aff); ?></td>
                    </tr>
                    <?php endif; ?>
                    <?php if ($frais_livraison > 0): ?>
                    <tr>
                        <td>Livraison</td>
                        <td><?php echo $zone_libelle ? htmlspecialchars($zone_libelle) : 'Zone de livraison'; ?></td>
                        <td><?php echo htmlspecialchars($frais_livraison_aff); ?></td>
                    </tr>
                    <?php endif; ?>
                    <?php if ($prix_commande <= 0 && $frais_livraison <= 0): ?>
                    <tr>
                        <td>Demande personnalisée #<?php echo (int) $cp['id']; ?></td>
                        <td>
                            <?php if (!empty($cp['type_produit'])): ?>Type: <?php echo htmlspecialchars($cp['type_produit']); ?>. <?php endif; ?>
                            <?php if (!empty($cp['quantite'])): ?>Quantité: <?php echo htmlspecialchars($cp['quantite']); ?>. <?php endif; ?>
                            <?php if (!empty($cp['date_souhaitee'])): ?>Date souhaitée: <?php echo date('d/m/Y', strtotime($cp['date_souhaitee'])); ?>.<?php endif; ?>
                        </td>
                        <td><?php echo htmlspecialchars($montant_aff); ?></td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <div class="facture-footer-section">
            <div class="facture-payment">
                <h3 style="font-size:14px; margin-bottom:10px;">Information</h3>
                <p style="font-size:13px; color:#666;">Devis établi suite à votre demande personnalisée. Contactez-nous pour finaliser.</p>
            </div>
            <div class="facture-summary">
                <?php if ($prix_commande > 0 && $frais_livraison > 0): ?>
                <div class="row">
                    <span>Prix commande</span>
                    <span><?php echo htmlspecialchars($prix_commande_aff); ?></span>
                </div>
                <div class="row">
                    <span>Frais de livraison</span>
                    <span><?php echo htmlspecialchars($frais_livraison_aff); ?></span>
                </div>
                <?php endif; ?>
                <div class="row total">
                    <span>TOTAL</span>
                    <span><?php echo htmlspecialchars($montant_aff); ?></span>
                </div>
                <div class="row solde-row">
                    <span>SOLDE DÛ</span>
                    <span><?php echo htmlspecialchars($montant_aff); ?></span>
                </div>
            </div>
        </div>

        <div class="facture-banner-bottom"></div>
    </div>
</body>
</html>
