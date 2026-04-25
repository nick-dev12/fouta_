<?php
/**
 * Caissier : recherche par n° de ticket et encaissement (zone paiement)
 */
session_start();

if (!isset($_SESSION['admin_id']) || !isset($_SESSION['admin_email'])) {
    header('Location: ../login.php');
    exit;
}

require_once __DIR__ . '/../includes/require_access.php';

require_once __DIR__ . '/../../includes/admin_permissions.php';
if (!admin_can_encaisser_ticket()) {
    header('Location: ../dashboard.php');
    exit;
}

if (empty($_SESSION['admin_csrf'])) {
    $_SESSION['admin_csrf'] = bin2hex(random_bytes(32));
}

require_once __DIR__ . '/../../models/model_caisse.php';
require_once __DIR__ . '/../../includes/barcode_caisse_ticket.php';

$flash_ok = '';
$flash_err = '';
if (!empty($_SESSION['caisse_flash_success'])) {
    $flash_ok = (string) $_SESSION['caisse_flash_success'];
    unset($_SESSION['caisse_flash_success']);
}
if (!empty($_SESSION['caisse_flash_error'])) {
    $flash_err = (string) $_SESSION['caisse_flash_error'];
    unset($_SESSION['caisse_flash_error']);
}

$numero_search = isset($_GET['numero']) ? trim((string) $_GET['numero']) : '';
$ticket_param = isset($_GET['ticket']) ? (int) $_GET['ticket'] : 0;
$vente = null;
if ($ticket_param > 0) {
    $vente = caisse_get_vente_by_id($ticket_param);
} elseif ($numero_search !== '') {
    $ref5 = caisse_normaliser_saisie_reference_caisse($numero_search);
    if ($ref5 !== null) {
        $vente = caisse_get_vente_par_reference_caisse($ref5);
    }
    if (!$vente) {
        $vente = caisse_get_vente_by_numero($numero_search);
    }
}

$ticket_introuvable = ($numero_search !== '' || $ticket_param > 0) && !$vente;
$ticket_dec = $vente ? caisse_decomposer_ttc($vente['montant_total'] ?? 0) : null;
$ticket_statut = $vente ? caisse_vente_statut($vente) : null;
$ticket_barcode_src = ($vente && $ticket_dec) ? caisse_ticket_get_barcode_web_path($vente) : '';
$ticket_barcode_payload = ($vente && $ticket_dec) ? caisse_ticket_valeur_code_barres($vente) : '';

$tables_ok = caisse_tables_exist();
$tickets_non_payes = $tables_ok ? caisse_list_ventes_en_attente_apercu(300) : [];
$page_title = 'Encaissement des tickets';
$total_ttc = $vente ? (float) ($vente['montant_total'] ?? 0) : 0;

$auto_print = isset($_GET['imprimer']) && $_GET['imprimer'] === '1';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <?php include __DIR__ . '/../../includes/favicon.php'; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title); ?> - Administration</title>
    <?php require_once __DIR__ . '/../../includes/asset_version.php'; ?>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="/css/admin-dashboard.css<?php echo asset_version_query(); ?>">
    <link rel="stylesheet" href="/css/admin-caisse-encaisser-ticket.css<?php echo asset_version_query(); ?>">
    <link rel="stylesheet" href="/css/admin-dashboard-caisse-pages.css<?php echo asset_version_query(); ?>">
</head>
<body class="admin-caisse-page admin-caisse-encaisser<?php echo ($vente && $ticket_dec) ? ' caisse-modal-open' : ''; ?>">
<?php include __DIR__ . '/../includes/nav.php'; ?>

<div class="caisse-page-wrap page-caisse-encaisser">
    <header class="caisse-page-head page-caisse-encaisser__hero">
        <div class="caisse-page-head-inner page-caisse-encaisser__head-inner">
            <div class="page-caisse-encaisser__head-text">
                <p class="page-caisse-encaisser__eyebrow">Caisse magasin</p>
                <h1 class="caisse-page-title" id="page-encaisser-title"><i class="fas fa-money-bill-wave" aria-hidden="true"></i> <?php echo htmlspecialchars($page_title); ?></h1>
                <p class="caisse-page-lead">Saisissez un n° de ticket, une <strong>référence 5 chiffres</strong> ou ouvrez un ticket depuis la file d’attente, puis encaissez (TTC).</p>
            </div>
            <div class="caisse-encaisse-toolbar no-print page-caisse-encaisser__toolbar" role="toolbar" aria-label="Actions rapides">
                <a href="historique-encaissements.php" class="btn-secondary page-caisse-encaisser__toolbar-btn"><i class="fas fa-history" aria-hidden="true"></i> Historique</a>
                <a href="encaisser-ticket.php" class="btn-primary page-caisse-encaisser__toolbar-btn"><i class="fas fa-sync-alt" aria-hidden="true"></i> Actualiser</a>
            </div>
        </div>
    </header>

    <?php if (!$tables_ok): ?>
    <div class="caisse-banner caisse-banner--warn page-caisse-encaisser__alert" role="alert">
        <i class="fas fa-database" aria-hidden="true"></i>
        <span>Tables caisse ou colonnes manquantes — exécutez les migrations <code>create_caisse_tables.sql</code> et <code>alter_caisse_ventes_statut_encaissement.sql</code>.</span>
    </div>
    <?php endif; ?>

    <?php if ($flash_ok !== ''): ?>
    <div class="caisse-banner caisse-banner--ok page-caisse-encaisser__flash" role="status"><?php echo htmlspecialchars($flash_ok); ?></div>
    <?php endif; ?>
    <?php if ($flash_err !== ''): ?>
    <div class="caisse-banner caisse-banner--err page-caisse-encaisser__flash" role="alert"><?php echo htmlspecialchars($flash_err); ?></div>
    <?php endif; ?>

    <?php if ($auto_print && $vente && $ticket_statut === 'paye'): ?>
    <div class="caisse-banner caisse-banner--ok no-print caisse-encaisse-apres-paiement page-caisse-encaisser__banner-paiement" role="status">
        <span><i class="fas fa-check-circle" aria-hidden="true"></i> Paiement enregistré. L’impression peut se lancer automatiquement. Utilisez <strong>Actualiser</strong> ci-dessus pour vider l’affichage et traiter un autre ticket.</span>
        <a href="encaisser-ticket.php" class="btn-primary btn-sm page-caisse-encaisser__btn-inline"><?php echo htmlspecialchars("Actualiser l'écran"); ?></a>
    </div>
    <?php endif; ?>

    <div class="caisse-encaisseur-search card-style-caisse page-caisse-encaisser__search no-print" aria-label="Recherche d’un ticket">
        <form method="get" action="encaisser-ticket.php" class="caisse-encaisseur-search-form page-caisse-encaisser__search-form">
            <label for="numero_ticket_search"><i class="fas fa-barcode" aria-hidden="true"></i> N° ticket ou référence caisse (5 chiffres)</label>
            <p class="page-caisse-encaisser__search-hint">Raccourci : les <strong>5 derniers chiffres</strong> du ticket suffisent souvent (scanner ou clavier pavé).</p>
            <div class="caisse-encaisseur-search-row page-caisse-encaisser__search-row">
                <input type="text" name="numero" id="numero_ticket_search"
                    value="<?php echo htmlspecialchars($numero_search ?: ($vente['numero_ticket'] ?? '')); ?>"
                    placeholder="Ex. 01542 ou TKT20260331000008" class="caisse-search-input" autocomplete="off" inputmode="text" spellcheck="false">
                <button type="submit" class="btn-primary page-caisse-encaisser__search-submit">Afficher</button>
            </div>
        </form>
    </div>

    <?php if ($ticket_introuvable): ?>
    <div class="caisse-banner caisse-banner--warn page-caisse-encaisser__notfound" role="status">Aucun ticket trouvé pour cette recherche.</div>
    <?php endif; ?>

    <?php if ($tables_ok): ?>
    <section class="caisse-attente-liste card-style-caisse no-print page-caisse-encaisser__attente" aria-labelledby="caisse-attente-heading">
        <div class="caisse-attente-liste__head">
            <h2 id="caisse-attente-heading" class="caisse-attente-liste__title">
                <i class="fas fa-hourglass-half" aria-hidden="true"></i> Tickets non payés
                <span class="caisse-attente-liste__count"><?php echo count($tickets_non_payes); ?></span>
            </h2>
            <p class="caisse-attente-liste__hint">Cliquez sur <strong>Ouvrir</strong> pour afficher le ticket et encaisser. Tri : du plus ancien au plus récent.</p>
        </div>
        <?php if (empty($tickets_non_payes)): ?>
        <p class="caisse-attente-liste__empty"><i class="fas fa-check" aria-hidden="true"></i> Aucun ticket en attente d’encaissement.</p>
        <?php else: ?>
        <div class="caisse-attente-table-wrap">
            <table class="caisse-attente-table">
                <thead>
                    <tr>
                        <th scope="col">Ref</th>
                        <th scope="col">N° ticket</th>
                        <th scope="col">Date</th>
                        <th scope="col">Montant TTC</th>
                        <th scope="col">Préparé par</th>
                        <th scope="col"><span class="visually-hidden">Action</span></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($tickets_non_payes as $trow):
                        $tid = (int) ($trow['id'] ?? 0);
                        $refDisp = (isset($trow['reference']) && (string) $trow['reference'] !== '') ? htmlspecialchars((string) $trow['reference']) : '—';
                        $rowCurrent = ($ticket_param > 0 && $ticket_param === $tid)
                            || ($vente && (int) ($vente['id'] ?? 0) === $tid);
                    ?>
                    <tr class="<?php echo $rowCurrent ? 'caisse-attente-table__row--current' : ''; ?>">
                        <td><code class="caisse-attente-ref"><?php echo $refDisp; ?></code></td>
                        <td><code><?php echo htmlspecialchars((string) ($trow['numero_ticket'] ?? '')); ?></code></td>
                        <td><?php echo isset($trow['date_vente']) ? htmlspecialchars(date('d/m/Y H:i', strtotime($trow['date_vente']))) : '—'; ?></td>
                        <td><strong><?php echo number_format((float) ($trow['montant_total'] ?? 0), 0, ',', ' '); ?></strong> FCFA</td>
                        <td><?php echo htmlspecialchars(trim(($trow['admin_prenom'] ?? '') . ' ' . ($trow['admin_nom'] ?? '')) ?: '—'); ?></td>
                        <td class="caisse-attente-table__act">
                            <a href="encaisser-ticket.php?ticket=<?php echo $tid; ?>" class="btn-primary btn-sm"><?php echo $rowCurrent ? 'Affiché' : 'Ouvrir'; ?></a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </section>
    <?php endif; ?>

    <?php if ($vente && $ticket_dec): ?>
    <div id="caisseTicketViewModal" class="caisse-ticket-view-modal is-open" role="dialog" aria-modal="true" aria-labelledby="caisseTicketViewTitle" aria-hidden="false">
        <div class="caisse-ticket-view-modal__backdrop no-print" data-ticket-view-close tabindex="-1"></div>
        <div class="caisse-ticket-view-modal__panel">
            <div class="caisse-ticket-view-modal__head no-print">
                <h2 id="caisseTicketViewTitle" class="caisse-ticket-view-modal__title"><i class="fas fa-receipt" aria-hidden="true"></i> Ticket</h2>
                <a href="encaisser-ticket.php" class="caisse-ticket-view-modal__close" data-ticket-view-close aria-label="Fermer le ticket"><i class="fas fa-times"></i></a>
            </div>
            <div class="caisse-encaisseur-wrap caisse-encaisseur-wrap--in-modal">
            <div class="caisse-ticket-card" id="ticket-print-zone">
                <div class="caisse-ticket-brand">FOUTA POIDS LOURDS</div>
                <div class="caisse-ticket-head">
                    <strong><?php echo htmlspecialchars(caisse_ticket_numero_date_public($vente)); ?></strong>
                    <span><?php echo isset($vente['date_vente']) ? htmlspecialchars(date('d/m/Y H:i', strtotime($vente['date_vente']))) : ''; ?></span>
                </div>
                <p class="caisse-ticket-numero-scan-hint">Code à saisir / scanner : <code><?php echo htmlspecialchars($ticket_barcode_payload); ?></code></p>
                <?php if ($ticket_statut === 'en_attente' && isset($vente['reference']) && (string) $vente['reference'] !== ''): ?>
                <p class="caisse-ticket-ref-caisse">Ref : <strong><code><?php echo htmlspecialchars((string) $vente['reference']); ?></code></strong></p>
                <?php endif; ?>
                <p class="caisse-ticket-meta">
                    Préparé par : <?php echo htmlspecialchars(trim(($vente['admin_prenom'] ?? '') . ' ' . ($vente['admin_nom'] ?? ''))); ?>
                </p>
                <?php if ($ticket_statut === 'paye' && trim(($vente['caissier_prenom'] ?? '') . ' ' . ($vente['caissier_nom'] ?? '')) !== ''): ?>
                <p class="caisse-ticket-meta">
                    Encaissé par : <?php echo htmlspecialchars(trim(($vente['caissier_prenom'] ?? '') . ' ' . ($vente['caissier_nom'] ?? ''))); ?>
                </p>
                <?php endif; ?>
                <table class="caisse-ticket-table">
                    <thead>
                        <tr><th>Article</th><th>Qté</th><th>PU TTC</th><th>Total</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($vente['lignes'] as $lg): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($lg['designation'] ?? ''); ?></td>
                            <td><?php echo (int) ($lg['quantite'] ?? 0); ?></td>
                            <td><?php echo number_format((float) ($lg['prix_unitaire'] ?? 0), 0, ',', ' '); ?></td>
                            <td><?php echo number_format((float) ($lg['total_ligne'] ?? 0), 0, ',', ' '); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <div class="caisse-ticket-tva-block">
                    <div class="caisse-ticket-row"><span>Total HT</span><strong><?php echo number_format($ticket_dec['ht'], 0, ',', ' '); ?> FCFA</strong></div>
                    <div class="caisse-ticket-row"><span>TVA (<?php echo htmlspecialchars((string) CAISSE_TVA_TAUX_POURCENT); ?> %)</span><strong><?php echo number_format($ticket_dec['tva'], 0, ',', ' '); ?> FCFA</strong></div>
                    <div class="caisse-ticket-row caisse-ticket-row--total"><span>Total TTC</span><strong><?php echo number_format($ticket_dec['ttc'], 0, ',', ' '); ?> FCFA</strong></div>
                </div>
                <?php if ($ticket_barcode_src !== ''): ?>
                <div class="caisse-ticket-barcode-block">
                    <p class="caisse-ticket-barcode-label"><i class="fas fa-barcode"></i> Code-barres (Code 128)</p>
                    <div class="caisse-ticket-barcode-wrap">
                        <img src="<?php echo htmlspecialchars($ticket_barcode_src); ?>?v=<?php echo (int) ($vente['id'] ?? 0); ?>"
                            alt="Code-barres ticket <?php echo htmlspecialchars($ticket_barcode_payload); ?>"
                            class="caisse-ticket-barcode-img">
                    </div>
                    <div class="caisse-ticket-barcode-value"><?php echo htmlspecialchars($ticket_barcode_payload); ?></div>
                </div>
                <?php endif; ?>
                <?php if ($ticket_statut === 'paye'):
                    $mode_tkt = (string) ($vente['mode_paiement'] ?? '');
                    $lib_mode = [
                        'especes' => 'Espèces',
                        'carte' => 'Carte bancaire',
                        'mobile_money' => 'Mobile money',
                        'cheque' => 'Chèque',
                        'mixte' => 'Mixte',
                        'autre' => 'Autre',
                    ];
                ?>
                <p class="caisse-ticket-pay">Paiement : <strong><?php echo htmlspecialchars($lib_mode[$mode_tkt] ?? $mode_tkt); ?></strong></p>
                <?php endif; ?>
                <div class="caisse-ticket-actions no-print">
                    <?php if ($ticket_statut === 'en_attente'): ?>
                    <button type="button" class="btn-primary" id="btnOpenEncaisseModal" <?php echo !$tables_ok ? 'disabled' : ''; ?>>
                        <i class="fas fa-cash-register"></i> Marquer comme payé
                    </button>
                    <?php endif; ?>
                    <button type="button" class="btn-secondary" id="btnPrintTicket"><i class="fas fa-print"></i> Imprimer</button>
                </div>
            </div>
            </div>
        </div>
    </div>

    <?php if ($vente && $ticket_dec && $ticket_statut === 'en_attente'): ?>
    <div id="encaisseFullModal" class="caisse-encaisse-modal" aria-hidden="true" hidden>
        <div class="caisse-encaisse-modal__backdrop" data-caisse-close-modal tabindex="-1"></div>
        <div class="caisse-encaisse-modal__panel" role="dialog" aria-labelledby="encaisseModalTitle" aria-modal="true">
            <div class="caisse-encaisse-modal__head">
                <h2 id="encaisseModalTitle" class="caisse-encaisse-modal__title"><i class="fas fa-money-check-alt"></i> Encaissement du ticket</h2>
                <button type="button" class="caisse-encaisse-modal__close" data-caisse-close-modal aria-label="Fermer"><i class="fas fa-times"></i></button>
            </div>
            <p class="caisse-encaisse-modal__recap">Total à payer : <strong><?php echo number_format($total_ttc, 0, ',', ' '); ?> FCFA</strong></p>

            <form method="post" action="post.php" class="caisse-pay-form caisse-encaisse-modal__form" id="formEncaisseTicket"
                data-total-ttc="<?php echo htmlspecialchars((string) $total_ttc); ?>">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['admin_csrf']); ?>">
                <input type="hidden" name="caisse_action" value="finaliser_ticket">
                <input type="hidden" name="vente_id" value="<?php echo (int) ($vente['id'] ?? 0); ?>">

                <label for="encaisse_mode_paiement">Mode de paiement</label>
                <select name="mode_paiement" id="encaisse_mode_paiement" class="caisse-select-pay" required>
                    <option value="especes">Espèces</option>
                    <option value="mobile_money">Mobile money (Wave, Orange Money…)</option>
                    <option value="carte">Carte bancaire</option>
                    <option value="cheque">Chèque</option>
                    <option value="mixte">Mixte</option>
                    <option value="autre">Autre</option>
                </select>

                <div id="encaisseBlocEspeces">
                    <label for="encaisse_montant_recu">Montant reçu (espèces)</label>
                    <input type="text" name="montant_recu" id="encaisse_montant_recu" inputmode="decimal" placeholder="Ex. 15000" class="caisse-input-pay" autocomplete="off">
                    <div id="encaisseMonnaieTempsReel" class="caisse-encaisse-monnaie-live" aria-live="polite">
                        <span class="caisse-encaisse-monnaie-live__label">Monnaie à rendre</span>
                        <span id="encaisseMonnaieValeur" class="caisse-encaisse-monnaie-live__valeur">—</span>
                    </div>
                </div>

                <div id="encaisseBlocMixte" class="caisse-encaisse-bloc-mixte is-hidden">
                    <p class="caisse-pay-split-title">Répartition du paiement mixte</p>
                    <label for="encaisse_montant_especes">Part espèces</label>
                    <input type="text" name="montant_especes" id="encaisse_montant_especes" inputmode="decimal" placeholder="0" class="caisse-input-pay" value="">
                    <label for="encaisse_montant_carte">Part carte</label>
                    <input type="text" name="montant_carte" id="encaisse_montant_carte" inputmode="decimal" placeholder="0" class="caisse-input-pay" value="">
                    <label for="encaisse_montant_mobile">Part mobile money</label>
                    <input type="text" name="montant_mobile_money" id="encaisse_montant_mobile" inputmode="decimal" placeholder="0" class="caisse-input-pay" value="">
                </div>

                <label for="encaisse_notes">Note (optionnel)</label>
                <textarea name="notes_vente" id="encaisse_notes" rows="2" class="caisse-textarea-pay" placeholder="Référence…"></textarea>

                <div class="caisse-encaisse-modal__actions">
                    <button type="button" class="btn-secondary" data-caisse-close-modal>Annuler</button>
                    <button type="submit" class="caisse-btn-valider" <?php echo !$tables_ok ? 'disabled' : ''; ?>>
                        <i class="fas fa-check"></i> Valider le paiement
                    </button>
                </div>
            </form>
        </div>
    </div>
    <?php endif; ?>
    <?php endif; ?>
</div>

<script>
(function () {
    var btnPrint = document.getElementById('btnPrintTicket');
    if (btnPrint) btnPrint.addEventListener('click', function () { window.print(); });
    <?php if ($auto_print): ?>
    window.addEventListener('load', function () {
        setTimeout(function () { window.print(); }, 300);
    });
    <?php endif; ?>

    var tvModal = document.getElementById('caisseTicketViewModal');
    if (tvModal) {
        tvModal.querySelectorAll('[data-ticket-view-close]').forEach(function (el) {
            if (el.tagName === 'A') {
                return;
            }
            el.addEventListener('click', function () {
                window.location.href = 'encaisser-ticket.php';
            });
        });
    }

    function ticketViewIsOpen() {
        return tvModal && tvModal.classList.contains('is-open');
    }

    var modal = document.getElementById('encaisseFullModal');
    var btnOpen = document.getElementById('btnOpenEncaisseModal');

    document.addEventListener('keydown', function (e) {
        if (e.key !== 'Escape') {
            return;
        }
        if (modal && modal.classList.contains('is-open')) {
            modal.setAttribute('hidden', 'hidden');
            modal.classList.remove('is-open');
            modal.setAttribute('aria-hidden', 'true');
            if (!ticketViewIsOpen()) {
                document.body.classList.remove('caisse-modal-open');
            }
            return;
        }
        if (ticketViewIsOpen()) {
            window.location.href = 'encaisser-ticket.php';
        }
    });

    if (!modal || !btnOpen) {
        return;
    }

    function parseMontant(s) {
        if (!s || typeof s !== 'string') return NaN;
        var t = s.replace(/\s/g, '').replace(',', '.');
        if (t === '') return NaN;
        var n = parseFloat(t);
        return isNaN(n) ? NaN : n;
    }

    function formatFcfa(n) {
        return Math.round(n).toString().replace(/\B(?=(\d{3})+(?!\d))/g, '\u202f') + '\u00a0FCFA';
    }

    function openModal() {
        modal.removeAttribute('hidden');
        modal.classList.add('is-open');
        modal.setAttribute('aria-hidden', 'false');
        document.body.classList.add('caisse-modal-open');
        var recu = document.getElementById('encaisse_montant_recu');
        if (recu) setTimeout(function () { recu.focus(); }, 50);
    }

    function closeModal() {
        modal.setAttribute('hidden', 'hidden');
        modal.classList.remove('is-open');
        modal.setAttribute('aria-hidden', 'true');
        if (!ticketViewIsOpen()) {
            document.body.classList.remove('caisse-modal-open');
        }
    }

    btnOpen.addEventListener('click', openModal);
    modal.querySelectorAll('[data-caisse-close-modal]').forEach(function (el) {
        el.addEventListener('click', closeModal);
    });

    var form = document.getElementById('formEncaisseTicket');
    var totalTtc = form ? parseFloat(form.getAttribute('data-total-ttc')) : 0;
    if (isNaN(totalTtc)) totalTtc = 0;
    var inpRecu = document.getElementById('encaisse_montant_recu');
    var outMonnaie = document.getElementById('encaisseMonnaieValeur');
    var selMode = document.getElementById('encaisse_mode_paiement');
    var blocEsp = document.getElementById('encaisseBlocEspeces');
    var blocMix = document.getElementById('encaisseBlocMixte');

    function updateMonnaieLive() {
        if (!outMonnaie || !selMode) return;
        if (selMode.value !== 'especes') {
            outMonnaie.textContent = '—';
            return;
        }
        if (!inpRecu) return;
        var recu = parseMontant(inpRecu.value);
        if (isNaN(recu)) {
            outMonnaie.textContent = '—';
            return;
        }
        if (recu + 1e-6 >= totalTtc) {
            outMonnaie.textContent = formatFcfa(Math.max(0, recu - totalTtc));
            outMonnaie.className = 'caisse-encaisse-monnaie-live__valeur is-ok';
        } else {
            var manque = totalTtc - recu;
            outMonnaie.textContent = 'Manque ' + formatFcfa(manque);
            outMonnaie.className = 'caisse-encaisse-monnaie-live__valeur is-manque';
        }
    }

    function toggleModeBlocks() {
        if (!selMode || !blocEsp || !blocMix) return;
        var m = selMode.value;
        if (m === 'mixte') {
            blocMix.classList.remove('is-hidden');
        } else {
            blocMix.classList.add('is-hidden');
        }
        if (m === 'especes') {
            blocEsp.style.display = '';
        } else {
            blocEsp.style.display = 'none';
        }
        updateMonnaieLive();
    }

    if (inpRecu) inpRecu.addEventListener('input', updateMonnaieLive);
    if (selMode) {
        selMode.addEventListener('change', toggleModeBlocks);
        toggleModeBlocks();
    }
})();
</script>
</body>
</html>
