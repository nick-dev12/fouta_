<?php
/**
 * LE TICKET DE CAISSE, REDESSINÉ (11/09/2026) — demande de la direction : un
 * ticket soigné, avec le logo.
 *
 * Un seul gabarit pour la vente directe (vendeur) et l'encaissement
 * (caissier) : le même ticket s'imprimait jusqu'ici par deux copies du même
 * code, sans logo, avec le nom de l'entreprise en simple texte.
 *
 * Pensé pour l'impression thermique 80 mm : le logo garde le bleu foncé FPL
 * (#10316F, demande de la direction du 11/09/2026), et
 * le total s'imprime en bandeau plein, tout le texte est noir à l'impression.
 * Il reste lisible sur A4 (reçu étroit centré) et à l'écran (papier blanc).
 * L'identité est celle des pages publiques : fpl_public_branding_coords().
 * Styles : css/caisse-ticket-recu.css.
 */

require_once __DIR__ . '/fpl_public_branding.php';

/**
 * Affiche le reçu d'un ticket.
 *
 * @param array<string, mixed> $vente ticket avec ses lignes, le vendeur et le caissier (caisse_get_vente_by_id)
 * @param array<string, float> $recap caisse_vente_recap_fiscal_affichage()
 * @param string $statut paye | en_attente | annule
 */
function caisse_ticket_recu_afficher(array $vente, array $recap, $statut, $code_barres_src, $code_barres_valeur, $detail_tva)
{
    $e = static function ($texte) {
        return htmlspecialchars((string) $texte, ENT_QUOTES, 'UTF-8');
    };
    $montant = static function ($n) {
        return number_format((float) $n, 0, ',', "\u{00A0}");
    };
    $identite = fpl_public_branding_coords();
    $site = preg_replace('#^https?://(www\.)?#', '', (string) ($identite['site'] ?? ''));
    $statut = (string) $statut;
    $lignes = is_array($vente['lignes'] ?? null) ? $vente['lignes'] : [];

    $pieces = 0;
    $sous_total = 0.0;
    foreach ($lignes as $ligne) {
        $pieces += (int) ($ligne['quantite'] ?? 0);
        $sous_total += (float) ($ligne['total_ligne'] ?? 0);
    }
    $remise_pct = (float) ($vente['remise_globale_pct'] ?? 0);
    $remise = $remise_pct > 0 ? round($sous_total * $remise_pct / 100, 2) : 0.0;
    $pourcentage = static function ($p) {
        return rtrim(rtrim(number_format((float) $p, 2, ',', ''), '0'), ',');
    };

    $vendeur = trim((string) ($vente['admin_prenom'] ?? '') . ' ' . (string) ($vente['admin_nom'] ?? ''));
    $caissier = trim((string) ($vente['caissier_prenom'] ?? '') . ' ' . (string) ($vente['caissier_nom'] ?? ''));
    $numero = function_exists('caisse_ticket_valeur_code_barres') ? caisse_ticket_valeur_code_barres($vente) : (string) ($vente['numero_ticket'] ?? '');
    $moment = ($statut === 'paye' && !empty($vente['date_encaissement'])) ? (string) $vente['date_encaissement'] : (string) ($vente['date_vente'] ?? '');
    $delai = function_exists('caisse_retour_delai_jours') ? caisse_retour_delai_jours() : null;
    $titres = ['paye' => 'Ticket de caisse', 'en_attente' => 'À payer à la caisse', 'annule' => 'Ticket annulé'];
    $titre = $titres[$statut] ?? 'Ticket';
    $reference = trim((string) ($vente['reference'] ?? ''));
    ?>
    <article class="fpl-recu fpl-recu--<?php echo $e($statut); ?>" id="ticket-print-zone" aria-label="<?php echo $e($titre . ' ' . $numero); ?>">
        <header class="fpl-recu__entete">
            <img class="fpl-recu__logo" src="/image/logo-fpl-bleu.png" alt="" width="364" height="434">
            <p class="fpl-recu__marque"><?php echo $e($identite['nom']); ?></p>
            <p class="fpl-recu__coord"><?php echo $e($identite['adresse']); ?></p>
            <p class="fpl-recu__coord">Tél. <?php echo $e($identite['telephone']); ?> · <?php echo $e($identite['telephone2']); ?></p>
            <p class="fpl-recu__legal">NINEA <?php echo $e($identite['ninea']); ?> · RC <?php echo $e($identite['rc']); ?></p>
        </header>

        <p class="fpl-recu__titre"><?php echo $e($titre); ?></p>

        <?php if ($statut === 'en_attente' && $reference !== ''): ?>
        <div class="fpl-recu__reference">
            <span>Référence à donner au caissier</span>
            <strong><?php echo $e($reference); ?></strong>
        </div>
        <?php endif; ?>

        <dl class="fpl-recu__infos">
            <div><dt>N°</dt><dd class="fpl-recu__numero"><?php echo $e($numero); ?></dd></div>
            <div><dt>Date</dt><dd><?php echo $moment !== '' ? $e(date('d/m/Y · H:i', strtotime($moment))) : '—'; ?></dd></div>
            <div><dt>Vendeur</dt><dd><?php echo $e($vendeur !== '' ? $vendeur : '—'); ?></dd></div>
            <?php if ($statut === 'paye' && $caissier !== ''): ?>
            <div><dt>Caissier</dt><dd><?php echo $e($caissier); ?></dd></div>
            <?php endif; ?>
        </dl>

        <ol class="fpl-recu__lignes">
            <?php foreach ($lignes as $ligne): ?>
            <?php $remise_ligne = (float) ($ligne['remise_ligne_pct'] ?? 0); ?>
            <li class="fpl-recu__ligne">
                <span class="fpl-recu__designation"><?php echo $e($ligne['designation'] ?? ''); ?><?php if (!empty($ligne['prix_saisi'])): ?> <span class="fpl-recu__staff no-print" title="<?php echo !empty($ligne['prix_catalogue']) ? $e('Prix du catalogue ce jour-là : ' . $montant($ligne['prix_catalogue']) . ' FCFA') : 'Pièce sans prix au catalogue'; ?>">prix saisi</span><?php endif; ?></span>
                <span class="fpl-recu__calcul"><?php echo (int) ($ligne['quantite'] ?? 0); ?> × <?php echo $montant($ligne['prix_unitaire'] ?? 0); ?><?php echo $remise_ligne > 0 ? ' · remise ' . $e($pourcentage($remise_ligne)) . ' %' : ''; ?></span>
                <span class="fpl-recu__montant"><?php echo $montant($ligne['total_ligne'] ?? 0); ?></span>
            </li>
            <?php endforeach; ?>
        </ol>

        <div class="fpl-recu__totaux">
            <div class="fpl-recu__rang"><span>Sous-total · <?php echo $pieces; ?> pièce<?php echo $pieces > 1 ? 's' : ''; ?></span><span><?php echo $montant($sous_total); ?></span></div>
            <?php if ($remise > 0): ?>
            <div class="fpl-recu__rang"><span>Remise <?php echo $e($pourcentage($remise_pct)); ?> %</span><span>−<?php echo $montant($remise); ?></span></div>
            <?php endif; ?>
            <?php if ($detail_tva): ?>
            <div class="fpl-recu__rang"><span>Total HT</span><span><?php echo $montant($recap['ht'] ?? 0); ?></span></div>
            <div class="fpl-recu__rang"><span>TVA <?php echo $e($pourcentage(defined('CAISSE_TVA_TAUX_POURCENT') ? CAISSE_TVA_TAUX_POURCENT : 18)); ?> %</span><span><?php echo $montant($recap['tva'] ?? 0); ?></span></div>
            <?php endif; ?>
        </div>
        <div class="fpl-recu__total">
            <span><?php echo $detail_tva ? 'Total TTC' : 'Total'; ?></span>
            <strong><?php echo $montant($recap['ttc'] ?? 0); ?> <small>FCFA</small></strong>
        </div>

        <?php if ($statut === 'paye'): ?>
        <dl class="fpl-recu__paiement">
            <div><dt>Payé</dt><dd><?php echo $e(function_exists('caisse_compta_libelle_paiement_ticket') ? caisse_compta_libelle_paiement_ticket($vente) : (string) ($vente['mode_paiement'] ?? '')); ?></dd></div>
            <?php if ((float) ($vente['montant_recu'] ?? 0) > 0): ?>
            <div><dt>Reçu</dt><dd><?php echo $montant($vente['montant_recu']); ?> FCFA</dd></div>
            <?php endif; ?>
            <?php if ((float) ($vente['monnaie_rendue'] ?? 0) > 0): ?>
            <div><dt>Rendu</dt><dd><?php echo $montant($vente['monnaie_rendue']); ?> FCFA</dd></div>
            <?php endif; ?>
        </dl>
        <?php elseif ($statut === 'annule'): ?>
        <p class="fpl-recu__annule">Annulé<?php echo !empty($vente['date_annulation']) ? ' le ' . $e(date('d/m/Y à H:i', strtotime((string) $vente['date_annulation']))) : ''; ?><?php echo !empty($vente['motif_annulation']) ? ' : ' . $e($vente['motif_annulation']) : ''; ?></p>
        <?php endif; ?>

        <?php if ((string) $code_barres_src !== ''): ?>
        <figure class="fpl-recu__code">
            <img src="<?php echo $e($code_barres_src); ?>?v=<?php echo (int) ($vente['id'] ?? 0); ?>" alt="Code-barres du ticket <?php echo $e($code_barres_valeur); ?>">
            <figcaption><?php echo $e($code_barres_valeur); ?></figcaption>
        </figure>
        <?php endif; ?>

        <footer class="fpl-recu__pied">
            <?php if ($statut !== 'annule'): ?>
            <p>Conservez ce ticket : il est demandé pour tout retour ou échange<?php echo $delai !== null ? ', dans les ' . (int) $delai . ' jours suivant l’achat' : ''; ?>.</p>
            <?php endif; ?>
            <p class="fpl-recu__merci">Merci de votre confiance</p>
            <?php if ($site !== ''): ?>
            <p class="fpl-recu__site"><?php echo $e($site); ?></p>
            <?php endif; ?>
        </footer>
    </article>
    <?php
}
