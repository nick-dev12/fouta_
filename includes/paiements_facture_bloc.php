<?php
/**
 * LE BLOC « PAIEMENTS » D'UNE FACTURE (10/09/2026), écrans d'administration seulement.
 *
 * Attend $paiement_bloc = ['type' => 'facture_devis'|'bl'|'facture_mensuelle', 'id' => int].
 * Montre le total imprimé, ce qui est payé, le reste et chaque paiement (montant,
 * moyen, date, référence, auteur, annulation). La comptabilité y enregistre un
 * paiement, ou en annule un avec un motif. Les règles vivent dans
 * models/model_paiements_factures.php.
 */
if (empty($paiement_bloc) || !empty($is_public)) {
    return;
}
require_once __DIR__ . '/../models/model_paiements_factures.php';
require_once __DIR__ . '/admin_permissions.php';

$pb_type = (string) ($paiement_bloc['type'] ?? '');
$pb_id = (int) ($paiement_bloc['id'] ?? 0);
$pb_table_ok = paiements_factures_table_ok();
$pb_etat = null;
if ($pb_table_ok) {
    try {
        $pb_etat = paiement_facture_etat($pb_type, $pb_id);
    } catch (Throwable $e) {
        error_log('[paiements_facture_bloc] ' . $e->getMessage());
    }
}
$pb_peut = admin_can_enregistrer_paiement_facture();
$pb_csrf = (string) ($_SESSION['admin_csrf'] ?? '');
$pb_modes = paiements_factures_modes();
$pb_fcfa = static function ($n) {
    return number_format((float) $n, 0, ',', ' ');
};
$pb_nom = static function ($prenom, $nom) {
    $complet = trim((string) $prenom . ' ' . (string) $nom);
    return $complet !== '' ? $complet : '—';
};
?>
<style>
    .paiements-facture { max-width: 900px; margin: 16px auto; padding: 18px 20px; background: #fff; border: 1px solid #DDE3EC; border-radius: 10px; color: #1F2633; box-sizing: border-box; }
    .paiements-facture h2 { margin: 0 0 12px; font-size: 1.1rem; color: #10316F; }
    .pf-soldes { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 10px; margin: 0 0 14px; }
    .pf-soldes div { padding: 10px 12px; border: 1px solid #E3E7EF; border-radius: 8px; }
    .pf-soldes dt { font-size: .85em; color: #4A5468; }
    .pf-soldes dd { margin: 4px 0 0; font-size: 1.15em; font-weight: 700; font-variant-numeric: tabular-nums; }
    .pf-reste--solde { color: #1E6B42; }
    .pf-reste--du { color: #A61E25; }
    .pf-table-wrap { overflow-x: auto; }
    .pf-table { width: 100%; border-collapse: collapse; font-variant-numeric: tabular-nums; font-size: .92em; }
    .pf-table th, .pf-table td { padding: 7px 8px; border-bottom: 1px solid #E3E7EF; text-align: left; vertical-align: top; }
    .pf-table .pf-num { text-align: right; white-space: nowrap; }
    .pf-annule td { color: #7A8294; }
    .pf-annule td.pf-barre { text-decoration: line-through; }
    .pf-note { margin: 10px 0 0; padding: 9px 12px; background: #F3F5F9; border-radius: 8px; }
    .pf-form { display: grid; grid-template-columns: repeat(auto-fit, minmax(170px, 1fr)); gap: 10px 14px; margin-top: 14px; align-items: end; }
    .pf-form label { display: block; font-size: .85em; font-weight: 600; margin-bottom: 4px; }
    .pf-form input, .pf-form select { width: 100%; box-sizing: border-box; padding: 8px 10px; border: 1px solid #C4CCDA; border-radius: 6px; font: inherit; }
    .pf-form button { padding: 9px 14px; border: 0; border-radius: 6px; background: #10316F; color: #fff; font: inherit; font-weight: 600; cursor: pointer; }
    .pf-annuler { display: flex; flex-wrap: wrap; gap: 6px; margin-top: 6px; }
    .pf-annuler input { flex: 1; min-width: 120px; padding: 6px 8px; border: 1px solid #C4CCDA; border-radius: 6px; font: inherit; }
    .pf-annuler button { padding: 6px 10px; border: 1px solid #A61E25; border-radius: 6px; background: #fff; color: #A61E25; font: inherit; cursor: pointer; }
    .paiements-facture :focus-visible { outline: 2px solid #10316F; outline-offset: 1px; }
    @media print { .paiements-facture { display: none !important; } }
</style>
<section class="paiements-facture" aria-labelledby="pf-titre">
    <h2 id="pf-titre">Paiements</h2>
    <?php if (!$pb_etat || empty($pb_etat['ok'])): ?>
    <p class="pf-note">Les paiements de cette facture ne peuvent pas être lus<?php echo $pb_table_ok ? '' : ' : la base attend migrations/run_paiements_factures.php'; ?>.</p>
    <?php else: ?>
    <dl class="pf-soldes">
        <div><dt>Total de la facture</dt><dd><?php echo $pb_fcfa($pb_etat['du']); ?> FCFA</dd></div>
        <div><dt>Payé</dt><dd><?php echo $pb_fcfa($pb_etat['paye']); ?> FCFA</dd></div>
        <div><dt>Reste à payer</dt>
            <dd class="<?php echo $pb_etat['reste'] < 0.5 ? 'pf-reste--solde' : 'pf-reste--du'; ?>"><?php echo $pb_etat['reste'] < 0.5 ? 'Soldée' : $pb_fcfa($pb_etat['reste']) . ' FCFA'; ?></dd></div>
    </dl>

    <?php if ($pb_etat['anciens_sans_detail']): ?>
    <p class="pf-note">Facture payée<?php echo !empty($pb_etat['date_paiement']) ? ' le ' . htmlspecialchars(date('d/m/Y', strtotime((string) $pb_etat['date_paiement']))) : ''; ?>, avant le registre des paiements : le montant et le moyen n’ont pas été enregistrés.</p>
    <?php endif; ?>

    <?php if ($pb_etat['paiements']): ?>
    <div class="pf-table-wrap">
        <table class="pf-table">
            <thead><tr><th>Reçu le</th><th class="pf-num">Montant</th><th>Moyen</th><th>Référence</th><th>Enregistré par</th><th>État</th></tr></thead>
            <tbody>
            <?php foreach ($pb_etat['paiements'] as $pl): $pl_annule = !empty($pl['date_annulation']); ?>
                <tr class="<?php echo $pl_annule ? 'pf-annule' : ''; ?>">
                    <td class="pf-barre"><?php echo htmlspecialchars(date('d/m/Y', strtotime((string) $pl['date_paiement']))); ?></td>
                    <td class="pf-num pf-barre"><?php echo $pb_fcfa($pl['montant']); ?> FCFA</td>
                    <td class="pf-barre"><?php echo htmlspecialchars($pb_modes[$pl['mode_paiement']] ?? (string) $pl['mode_paiement']); ?></td>
                    <td><?php echo htmlspecialchars((string) ($pl['reference'] ?? '')); ?><?php if (trim((string) ($pl['notes'] ?? '')) !== ''): ?><br><small><?php echo htmlspecialchars((string) $pl['notes']); ?></small><?php endif; ?></td>
                    <td><?php echo htmlspecialchars($pb_nom($pl['auteur_prenom'] ?? '', $pl['auteur_nom'] ?? '')); ?><br><small><?php echo htmlspecialchars(date('d/m/Y H:i', strtotime((string) $pl['date_creation']))); ?></small></td>
                    <td>
                    <?php if ($pl_annule): ?>
                        Annulé le <?php echo htmlspecialchars(date('d/m/Y', strtotime((string) $pl['date_annulation']))); ?>
                        par <?php echo htmlspecialchars($pb_nom($pl['annule_prenom'] ?? '', $pl['annule_nom'] ?? '')); ?> :
                        <?php echo htmlspecialchars((string) ($pl['motif_annulation'] ?? '')); ?>
                    <?php else: ?>
                        Enregistré
                        <?php if ($pb_peut): ?>
                        <form method="post" action="paiement_annuler.php" class="pf-annuler"
                            onsubmit="return confirm('Annuler ce paiement ? Il reste visible, barré, avec votre motif.');">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($pb_csrf, ENT_QUOTES, 'UTF-8'); ?>">
                            <input type="hidden" name="paiement_id" value="<?php echo (int) $pl['id']; ?>">
                            <input type="hidden" name="type" value="<?php echo htmlspecialchars($pb_type, ENT_QUOTES, 'UTF-8'); ?>">
                            <input type="hidden" name="id" value="<?php echo $pb_id; ?>">
                            <input type="text" name="motif" required minlength="3" maxlength="255" placeholder="Motif de l’annulation" aria-label="Motif de l’annulation">
                            <button type="submit">Annuler ce paiement</button>
                        </form>
                        <?php endif; ?>
                    <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>

    <?php if ($pb_etat['payable'] && $pb_peut): ?>
    <form method="post" action="paiement_enregistrer.php" class="pf-form">
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($pb_csrf, ENT_QUOTES, 'UTF-8'); ?>">
        <input type="hidden" name="type" value="<?php echo htmlspecialchars($pb_type, ENT_QUOTES, 'UTF-8'); ?>">
        <input type="hidden" name="id" value="<?php echo $pb_id; ?>">
        <div>
            <label for="pf-montant">Montant reçu (FCFA)</label>
            <input type="text" id="pf-montant" name="montant" inputmode="decimal" required autocomplete="off" value="<?php echo htmlspecialchars((string) round($pb_etat['reste'])); ?>">
        </div>
        <div>
            <label for="pf-mode">Moyen de paiement</label>
            <select id="pf-mode" name="mode_paiement" required>
                <option value="">Choisir…</option>
                <?php foreach ($pb_modes as $pb_code => $pb_libelle): ?>
                <option value="<?php echo htmlspecialchars($pb_code); ?>"><?php echo htmlspecialchars($pb_libelle); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label for="pf-date">Reçu le</label>
            <input type="date" id="pf-date" name="date_paiement" required value="<?php echo date('Y-m-d'); ?>" max="<?php echo date('Y-m-d'); ?>">
        </div>
        <div>
            <label for="pf-reference">Référence (chèque, virement…)</label>
            <input type="text" id="pf-reference" name="reference" maxlength="100" autocomplete="off">
        </div>
        <div>
            <label for="pf-notes">Note</label>
            <input type="text" id="pf-notes" name="notes" maxlength="255" autocomplete="off">
        </div>
        <div><button type="submit">Enregistrer le paiement</button></div>
    </form>
    <?php elseif (!$pb_etat['payee'] && !$pb_peut): ?>
    <p class="pf-note">Le paiement de cette facture est enregistré par la comptabilité.</p>
    <?php elseif (!$pb_etat['payable'] && !$pb_etat['payee'] && $pb_etat['raison'] !== ''): ?>
    <p class="pf-note"><?php echo htmlspecialchars($pb_etat['raison']); ?></p>
    <?php endif; ?>
    <?php endif; ?>
</section>
