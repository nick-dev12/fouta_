<?php
/**
 * PRÉPARER UN RETOUR CLIENT (11/09/2026).
 *
 * Le client rapporte une pièce : mauvaise pièce ou pièce défectueuse. Le
 * commercial général retrouve le ticket, choisit les pièces rendues, le motif
 * et ce que reçoit le client : un remboursement en espèces ou un échange. Il
 * prépare le retour ; le caissier le valide ensuite dans « Retours clients »,
 * et c'est seulement là que l'argent et le stock bougent.
 * Règles : models/model_caisse_retours.php.
 */
session_start();

if (!isset($_SESSION['admin_id']) || !isset($_SESSION['admin_email'])) {
    header('Location: ../login.php');
    exit;
}

require_once __DIR__ . '/../includes/require_access.php';

require_once __DIR__ . '/../../includes/admin_permissions.php';
if (!admin_can_preparer_retour_caisse()) {
    admin_redirect_role_home();
}

if (empty($_SESSION['admin_csrf'])) {
    $_SESSION['admin_csrf'] = bin2hex(random_bytes(32));
}

require_once __DIR__ . '/../../models/model_caisse_retours.php';
require_once __DIR__ . '/../../models/model_caisse_compta.php';

$page_title = 'Retour client';
$tables_ok = caisse_tables_exist() && caisse_retours_tables_ok();

$flash_ok = (string) ($_SESSION['caisse_flash_success'] ?? '');
$flash_err = (string) ($_SESSION['caisse_flash_error'] ?? '');
unset($_SESSION['caisse_flash_success'], $_SESSION['caisse_flash_error']);

$vente_id = (int) ($_GET['ticket'] ?? 0);
$numero_saisi = trim((string) ($_GET['numero'] ?? ''));
$introuvable = false;
if ($tables_ok && $vente_id <= 0 && $numero_saisi !== '') {
    $trouve = caisse_get_vente_by_numero($numero_saisi);
    if ($trouve) {
        header('Location: retour.php?ticket=' . (int) $trouve['id']);
        exit;
    }
    $introuvable = true;
}

$etat = null;
if ($tables_ok && $vente_id > 0) {
    try {
        $etat = caisse_retour_etat_vente($vente_id);
    } catch (PDOException $e) {
        error_log('[caisse/retour.php] ' . $e->getMessage());
    }
    if (is_array($etat) && empty($etat['ok'])) {
        $introuvable = true;
    }
}
$vente = (is_array($etat) && !empty($etat['ok'])) ? $etat['vente'] : null;
$delai = $tables_ok ? caisse_retour_delai_jours() : null;
$fcfa = static function ($n) {
    return number_format((float) $n, 0, ',', ' ');
};
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <?php include __DIR__ . '/../../includes/favicon.php'; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title); ?> - Administration</title>
    <?php require_once __DIR__ . '/../../includes/asset_version.php'; ?>
<?php include __DIR__ . '/..//includes/fpl_head.php'; ?>
    <?php fpl_css_link('admin-dashboard-caisse-pages.css'); ?>
    <style>
        .retour-section { margin-top: 18px; }
        .retour-section h2 { margin: 0 0 6px; font-size: 1.15rem; color: #10316F; }
        .retour-meta { margin: 0 0 12px; color: #4A5468; }
        .retour-flash { display: flex; gap: 10px; align-items: center; margin-top: 14px; padding: 12px 14px; border-radius: 8px; }
        .retour-flash--ok { background: #E6F4EC; color: #1E6B42; }
        .retour-flash--err { background: #FBE9EA; color: #A61E25; }
        .retour-recherche-ticket { display: flex; flex-wrap: wrap; gap: 8px; align-items: center; }
        .retour-recherche-ticket input { flex: 1; min-width: 220px; max-width: 360px; padding: 9px 11px; border: 1px solid #C4CCDA; border-radius: 6px; font: inherit; }
        .retour-table-wrap { overflow-x: auto; }
        .retour-table { width: 100%; border-collapse: collapse; font-variant-numeric: tabular-nums; }
        .retour-table th, .retour-table td { padding: 8px 10px; border-bottom: 1px solid #E3E7EF; text-align: left; vertical-align: middle; }
        .retour-table .num { text-align: right; white-space: nowrap; }
        .retour-table input[type="number"] { width: 84px; padding: 6px 8px; border: 1px solid #C4CCDA; border-radius: 6px; font: inherit; text-align: right; }
        .retour-form fieldset { border: 1px solid #E3E7EF; border-radius: 8px; padding: 10px 14px 12px; margin: 16px 0 0; }
        .retour-form legend { font-weight: 600; padding: 0 6px; color: #10316F; }
        .retour-form label.retour-choix { display: flex; gap: 8px; align-items: center; margin: 6px 0; }
        .retour-form textarea, .retour-form input[type="text"] { width: 100%; max-width: 560px; padding: 9px 11px; border: 1px solid #C4CCDA; border-radius: 6px; font: inherit; box-sizing: border-box; }
        .retour-form [hidden] { display: none !important; }
        .retour-aide { margin: 4px 0 0; font-size: 0.9em; color: #4A5468; }
        .retour-recherche { display: flex; flex-wrap: wrap; gap: 8px; margin: 10px 0 6px; }
        .retour-recherche input { max-width: 360px; }
        .retour-bilan { margin: 16px 0; padding: 12px 14px; background: #F3F5F9; border-radius: 8px; display: grid; gap: 4px; font-variant-numeric: tabular-nums; }
        .retour-bilan strong { color: #10316F; }
        .retour-form :focus-visible, .retour-recherche-ticket :focus-visible { outline: 2px solid #10316F; outline-offset: 1px; }
    </style>
</head>
<body class="admin-caisse-page admin-caisse-retour">
<?php include __DIR__ . '/../includes/nav.php'; ?>

<div class="caisse-page-wrap">
    <header class="caisse-page-head caisse-hist-head">
        <div class="caisse-page-head-inner">
            <h1 class="caisse-page-title"><i class="fas fa-undo"></i> <?php echo htmlspecialchars($page_title); ?></h1>
            <p class="caisse-page-lead">Le client rapporte une pièce ? Retrouvez son ticket, choisissez ce qu’il rend et ce qu’il reçoit. Le caissier validera le retour : c’est lui qui rend l’argent.</p>
            <div class="caisse-hist-head-actions">
                <a href="retours.php" class="btn-secondary"><i class="fas fa-list"></i> Retours clients</a>
                <a href="index.php" class="btn-secondary"><i class="fas fa-cash-register"></i> Caisse magasin</a>
            </div>
        </div>
    </header>

    <?php if ($flash_ok !== ''): ?>
    <div class="retour-flash retour-flash--ok" role="status"><i class="fas fa-check-circle" aria-hidden="true"></i> <span><?php echo htmlspecialchars($flash_ok); ?></span></div>
    <?php endif; ?>
    <?php if ($flash_err !== ''): ?>
    <div class="retour-flash retour-flash--err" role="alert"><i class="fas fa-exclamation-triangle" aria-hidden="true"></i> <span><?php echo htmlspecialchars($flash_err); ?></span></div>
    <?php endif; ?>

    <?php if (!$tables_ok): ?>
    <div class="caisse-banner caisse-banner--warn">
        <i class="fas fa-database"></i>
        <span>Les retours attendent la mise à jour de la base : exécutez migrations/run_caisse_retours.php.</span>
    </div>
    <?php else: ?>

    <section class="card-style-caisse retour-section" aria-labelledby="retour-cherche-titre">
        <h2 id="retour-cherche-titre">Le ticket du client</h2>
        <form method="get" action="retour.php" class="retour-recherche-ticket">
            <label for="retour-numero" class="visually-hidden">Numéro du ticket</label>
            <input type="text" id="retour-numero" name="numero" autocomplete="off" placeholder="Scannez le ticket ou tapez son numéro TKT…"
                value="<?php echo htmlspecialchars($vente ? (string) $vente['numero_ticket'] : $numero_saisi); ?>">
            <button type="submit" class="btn-primary"><i class="fas fa-search"></i> Retrouver le ticket</button>
        </form>
        <p class="retour-aide">Pas de ticket, pas de retour. <?php echo $delai === null ? 'Aucun délai de retour n’est fixé pour l’instant.' : 'Délai de retour : ' . $delai . ' jour(s) après l’encaissement.'; ?></p>
        <?php if ($introuvable): ?>
        <div class="retour-flash retour-flash--err" role="alert"><span>Aucun ticket ne correspond à ce numéro.</span></div>
        <?php endif; ?>
    </section>

    <?php if ($vente): ?>
    <section class="card-style-caisse retour-section" aria-labelledby="retour-ticket-titre">
        <h2 id="retour-ticket-titre">Ticket <code><?php echo htmlspecialchars((string) $vente['numero_ticket']); ?></code></h2>
        <p class="retour-meta">
            <?php if (caisse_vente_statut($vente) === 'paye'): ?>
            Encaissé le <?php echo htmlspecialchars(date('d/m/Y à H:i', strtotime((string) $etat['moment']))); ?>,
            payé en <?php echo htmlspecialchars(caisse_compta_libelle_paiement_ticket($vente)); ?>,
            <?php else: ?>
            Ticket <?php echo htmlspecialchars(caisse_vente_statut($vente) === 'annule' ? 'annulé' : 'en attente de caisse'); ?>,
            <?php endif; ?>
            total <?php echo $fcfa($vente['montant_total']); ?> FCFA. Préparé par <?php echo htmlspecialchars(trim($vente['vendeur_prenom'] . ' ' . $vente['vendeur_nom']) ?: '—'); ?>.
        </p>

        <?php if (!$etat['retournable']): ?>
        <div class="retour-flash retour-flash--err" role="alert"><i class="fas fa-ban" aria-hidden="true"></i> <span><?php echo htmlspecialchars($etat['raison']); ?></span></div>
        <?php else: ?>
        <form method="post" action="post.php" class="retour-form" id="retour-form"
            data-tva="<?php echo !empty($vente['tva_incluse']) ? '1' : '0'; ?>" data-taux="<?php echo htmlspecialchars((string) CAISSE_TVA_TAUX_POURCENT); ?>">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars((string) $_SESSION['admin_csrf'], ENT_QUOTES, 'UTF-8'); ?>">
            <input type="hidden" name="caisse_action" value="retour_preparer">
            <input type="hidden" name="vente_id" value="<?php echo (int) $vente['id']; ?>">

            <fieldset>
                <legend>1. Les pièces rendues</legend>
                <div class="retour-table-wrap">
                    <table class="retour-table">
                        <thead>
                            <tr><th>Pièce</th><th class="num">Vendues</th><th class="num">Déjà rendues</th><th class="num">Prix payé par pièce</th><th class="num">Quantité rendue</th></tr>
                        </thead>
                        <tbody>
                        <?php foreach ($etat['lignes'] as $ligne): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($ligne['designation']); ?></td>
                                <td class="num"><?php echo (int) $ligne['quantite']; ?></td>
                                <td class="num"><?php echo (int) $ligne['deja_rendue']; ?></td>
                                <td class="num"><?php echo $fcfa($ligne['valeur_unitaire']); ?> FCFA</td>
                                <td class="num">
                                    <label class="visually-hidden" for="qte-<?php echo (int) $ligne['id']; ?>">Quantité rendue</label>
                                    <input type="number" id="qte-<?php echo (int) $ligne['id']; ?>" class="retour-qte" name="quantites[<?php echo (int) $ligne['id']; ?>]"
                                        min="0" max="<?php echo (int) $ligne['disponible']; ?>" value="0" data-valeur="<?php echo htmlspecialchars((string) $ligne['valeur_unitaire']); ?>"
                                        <?php echo $ligne['disponible'] > 0 ? '' : 'disabled'; ?>>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </fieldset>

            <fieldset>
                <legend>2. Le motif</legend>
                <label class="retour-choix"><input type="radio" name="motif" value="mauvaise_piece" required> Mauvaise pièce</label>
                <label class="retour-choix"><input type="radio" name="motif" value="defectueuse"> Pièce défectueuse</label>
                <label class="retour-choix" id="retour-intacte" hidden><input type="checkbox" name="piece_intacte" value="1"> La pièce est intacte et n’a jamais été montée</label>
                <p class="retour-aide">Une pièce montée ou abîmée par le client ne se reprend pas. Une pièce défectueuse ne revient jamais en vente.</p>
            </fieldset>

            <fieldset>
                <legend>3. Ce que reçoit le client</legend>
                <label class="retour-choix"><input type="radio" name="solution" value="remboursement" required> Remboursement en espèces</label>
                <label class="retour-choix"><input type="radio" name="solution" value="echange"> Échange</label>
                <div id="retour-echange" hidden>
                    <label class="retour-choix" id="retour-meme-piece-choix" hidden><input type="radio" name="echange_type" value="meme_piece"> La même pièce, à la place de la pièce défectueuse</label>
                    <label class="retour-choix"><input type="radio" name="echange_type" value="autre_piece"> Une autre pièce</label>
                    <div id="retour-autre" hidden>
                        <div class="retour-recherche">
                            <label class="visually-hidden" for="retour-code">Pièce remise</label>
                            <input type="text" id="retour-code" autocomplete="off" placeholder="Scannez l’étiquette ou tapez la référence">
                            <button type="button" class="btn-secondary" id="retour-ajouter"><i class="fas fa-plus"></i> Ajouter</button>
                        </div>
                        <p class="retour-aide" id="retour-recherche-message" role="status"></p>
                        <div class="retour-table-wrap">
                            <table class="retour-table">
                                <thead><tr><th>Pièce remise</th><th class="num">Prix</th><th class="num">En stock</th><th class="num">Quantité</th><th></th></tr></thead>
                                <tbody id="retour-remises"></tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </fieldset>

            <fieldset>
                <legend>4. L’explication</legend>
                <label class="visually-hidden" for="retour-explication">Explication</label>
                <textarea id="retour-explication" name="explication" rows="2" required minlength="3" maxlength="255" placeholder="Ex. le client voulait le rétroviseur gauche, pas le droit"></textarea>
            </fieldset>

            <div class="retour-bilan" aria-live="polite">
                <span>Valeur rendue au prix payé : <strong id="bilan-rendu">0</strong> FCFA</span>
                <span>Valeur des pièces remises : <strong id="bilan-remis">0</strong> FCFA</span>
                <strong id="bilan-especes">Aucun argent à échanger</strong>
            </div>
            <button type="submit" class="btn-primary"><i class="fas fa-undo"></i> Préparer le retour</button>
            <p class="retour-aide">Rien ne bouge encore : le stock et les espèces changent quand le caissier valide.</p>
        </form>
        <?php endif; ?>
    </section>
    <?php endif; ?>
    <?php endif; ?>
</div>

<script>
(function () {
    var form = document.getElementById('retour-form');
    if (!form) {
        return;
    }
    var tva = form.getAttribute('data-tva') === '1';
    var taux = parseFloat(form.getAttribute('data-taux')) || 0;
    var csrf = form.querySelector('input[name="csrf_token"]').value;
    var remises = document.getElementById('retour-remises');
    var fcfa = function (n) {
        return String(Math.round(n)).replace(/\B(?=(\d{3})+(?!\d))/g, ' ');
    };
    var choisi = function (nom) {
        var c = form.querySelector('input[name="' + nom + '"]:checked');
        return c ? c.value : '';
    };
    function majFormulaire() {
        var motif = choisi('motif');
        var solution = choisi('solution');
        var type = choisi('echange_type');
        document.getElementById('retour-intacte').hidden = motif !== 'mauvaise_piece';
        document.getElementById('retour-echange').hidden = solution !== 'echange';
        document.getElementById('retour-meme-piece-choix').hidden = motif !== 'defectueuse';
        if (motif !== 'defectueuse' && type === 'meme_piece') {
            form.querySelector('input[name="echange_type"][value="meme_piece"]').checked = false;
            type = '';
        }
        document.getElementById('retour-autre').hidden = !(solution === 'echange' && type === 'autre_piece');

        var rendu = 0;
        form.querySelectorAll('.retour-qte').forEach(function (champ) {
            rendu += (parseInt(champ.value, 10) || 0) * (parseFloat(champ.getAttribute('data-valeur')) || 0);
        });
        var remis = 0;
        if (solution === 'echange' && type === 'meme_piece') {
            remis = rendu;
        } else if (solution === 'echange' && type === 'autre_piece') {
            remises.querySelectorAll('input[data-prix]').forEach(function (champ) {
                remis += (parseInt(champ.value, 10) || 0) * (parseFloat(champ.getAttribute('data-prix')) || 0);
            });
        }
        document.getElementById('bilan-rendu').textContent = fcfa(rendu);
        document.getElementById('bilan-remis').textContent = fcfa(remis);
        var difference = remis - rendu;
        document.getElementById('bilan-especes').textContent = Math.abs(difference) < 0.5
            ? 'Aucun argent à échanger'
            : (difference < 0
                ? 'Le caissier rendra ' + fcfa(-difference) + ' FCFA en espèces'
                : 'Le client paiera ' + fcfa(difference) + ' FCFA en espèces au caissier');
    }
    function ajouterPiece() {
        var champ = document.getElementById('retour-code');
        var message = document.getElementById('retour-recherche-message');
        var code = champ.value.trim();
        if (!code) {
            return;
        }
        message.textContent = 'Recherche…';
        fetch('api.php', {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
            body: JSON.stringify({ action: 'resolve_product', code: code, csrf_token: csrf })
        }).then(function (r) {
            return r.json();
        }).then(function (res) {
            if (!res.ok || !res.produit) {
                message.textContent = res.type === 'ticket' ? 'C’est un ticket, pas une pièce.' : (res.error || 'Pièce introuvable.');
                return;
            }
            var p = res.produit;
            if (p.prix <= 0) {
                message.textContent = '« ' + p.nom + ' » n’a pas de prix : elle se vend par un ticket.';
                return;
            }
            if (p.stock <= 0) {
                message.textContent = '« ' + p.nom + ' » n’est plus en stock.';
                return;
            }
            var existant = remises.querySelector('input[name="remises[' + p.id + ']"]');
            if (existant) {
                existant.value = Math.min(p.stock, (parseInt(existant.value, 10) || 0) + 1);
            } else {
                var prix = tva ? Math.round(p.prix * (1 + taux / 100) * 100) / 100 : p.prix;
                var tr = document.createElement('tr');
                tr.innerHTML = '<td></td><td class="num"></td><td class="num"></td><td class="num"><input type="number" min="1" value="1"></td>'
                    + '<td><button type="button" class="btn-secondary btn-sm">Retirer</button></td>';
                tr.children[0].textContent = p.nom + (p.ref ? ' · ' + p.ref : '');
                tr.children[1].textContent = fcfa(prix) + ' FCFA';
                tr.children[2].textContent = p.stock;
                var quantite = tr.querySelector('input');
                quantite.name = 'remises[' + p.id + ']';
                quantite.max = p.stock;
                quantite.setAttribute('data-prix', prix);
                quantite.setAttribute('aria-label', 'Quantité remise');
                tr.querySelector('button').addEventListener('click', function () {
                    tr.remove();
                    majFormulaire();
                });
                remises.appendChild(tr);
            }
            champ.value = '';
            message.textContent = '« ' + p.nom + ' » ajoutée.';
            majFormulaire();
        }).catch(function () {
            message.textContent = 'Erreur réseau : réessayez.';
        });
    }
    // Les refus évidents se disent avant l'envoi : la saisie n'est pas perdue.
    form.addEventListener('submit', function (ev) {
        var rendues = 0;
        form.querySelectorAll('.retour-qte').forEach(function (champ) {
            rendues += parseInt(champ.value, 10) || 0;
        });
        var motif = choisi('motif');
        var solution = choisi('solution');
        var type = choisi('echange_type');
        var message = '';
        if (rendues <= 0) {
            message = 'Indiquez au moins une pièce rendue.';
        } else if (motif === 'mauvaise_piece' && !form.querySelector('input[name="piece_intacte"]').checked) {
            message = 'Une pièce montée ou abîmée par le client ne se reprend pas : confirmez que la pièce est intacte.';
        } else if (solution === 'echange' && type === '') {
            message = 'Choisissez l’échange : la même pièce ou une autre pièce.';
        } else if (solution === 'echange' && type === 'autre_piece' && !remises.querySelector('input[data-prix]')) {
            message = 'Ajoutez la ou les pièces remises au client en échange.';
        }
        if (message !== '') {
            ev.preventDefault();
            alert(message);
        }
    });
    form.addEventListener('input', majFormulaire);
    form.addEventListener('change', majFormulaire);
    document.getElementById('retour-ajouter').addEventListener('click', ajouterPiece);
    document.getElementById('retour-code').addEventListener('keydown', function (ev) {
        if (ev.key === 'Enter') {
            ev.preventDefault();
            ajouterPiece();
        }
    });
    majFormulaire();
})();
</script>
</body>
</html>
