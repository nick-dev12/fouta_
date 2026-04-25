<?php
/**
 * Historique des ventes / Comptabilité
 * Filtres: jour, période (date début - date fin), mois, année
 * Vue par défaut: ventes du jour
 */
session_start();

if (!isset($_SESSION['admin_id']) || !isset($_SESSION['admin_email'])) {
    header('Location: ../login.php');
    exit;
}

require_once __DIR__ . '/../includes/require_access.php';

require_once __DIR__ . '/../../models/model_commandes_admin.php';

$periode = isset($_GET['periode']) ? $_GET['periode'] : 'jour';
if (!in_array($periode, ['jour', 'plage', 'annee'])) {
    $periode = 'jour';
}

$annee = isset($_GET['annee']) ? (int) $_GET['annee'] : (int) date('Y');
$mois = isset($_GET['mois']) ? (int) $_GET['mois'] : (int) date('n');
$jour = isset($_GET['jour']) ? (int) $_GET['jour'] : (int) date('j');
$date_debut = isset($_GET['date_debut']) ? trim($_GET['date_debut']) : null;
$date_fin = isset($_GET['date_fin']) ? trim($_GET['date_fin']) : null;

// Le filtre "mois" travaille uniquement sur le mois choisi de l'année en cours.
if ($periode === 'mois') {
    $annee = (int) date('Y');
}

// Si date_jour fournie (input type=date), extraire annee, mois, jour
if (!empty($_GET['date_jour'])) {
    $parts = explode('-', $_GET['date_jour']);
    if (count($parts) === 3) {
        $annee = (int) $parts[0];
        $mois = (int) $parts[1];
        $jour = (int) $parts[2];
    }
}

$commandes = get_commandes_by_periode($periode, $annee, $mois, $date_debut, $date_fin, $jour);
$stats = get_stats_comptabilite_periode($commandes);

// Stats globales (toutes les commandes, tous statuts)
$stats_globales = [
    'montant_total' => get_montant_total_commandes(),
    'montant_livrees' => get_montant_total_commandes('livree'),
    'montant_non_traitees' => get_montant_total_commandes() - get_montant_total_commandes('livree') - get_montant_total_commandes('annulee'),
    'nb_total' => count_commandes_by_statut(),
    'nb_livrees' => count_commandes_by_statut('livree'),
    'nb_annulees' => count_commandes_by_statut('annulee')
];

$libelle_periode = '';
switch ($periode) {
    case 'jour':
        $libelle_periode = date('d/m/Y', strtotime("$annee-$mois-$jour"));
        break;
    case 'plage':
        if ($date_debut && $date_fin) {
            $libelle_periode = date('d/m/Y', strtotime($date_debut)) . ' - ' . date('d/m/Y', strtotime($date_fin));
        } elseif ($date_debut) {
            $libelle_periode = 'À partir du ' . date('d/m/Y', strtotime($date_debut));
        } elseif ($date_fin) {
            $libelle_periode = "Jusqu'au " . date('d/m/Y', strtotime($date_fin));
        } else {
            $libelle_periode = 'Période personnalisée';
        }
        break;
    case 'annee':
        $libelle_periode = "Année $annee";
        break;
}
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <?php include __DIR__ . '/../../includes/favicon.php'; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Historique des ventes - Comptabilité</title>
    <?php require_once __DIR__ . '/../../includes/asset_version.php'; ?>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="/css/variables.css<?php echo asset_version_query(); ?>">
    <link rel="stylesheet" href="/css/admin-dashboard.css<?php echo asset_version_query(); ?>">
    <style>
        .historique-filtres {
            display: flex;
            flex-wrap: wrap;
            gap: 20px;
            align-items: flex-end;
            margin-bottom: 28px;
            padding: 24px 28px;
            background: linear-gradient(135deg, var(--beige-creme) 0%, #fef5f9 100%);
            border-radius: 16px;
            box-shadow: var(--ombre-douce);
            border: 1px solid rgba(229, 72, 138, 0.2);
        }

        .historique-filtres .form-group {
            margin: 0;
            flex: 0 0 auto;
        }

        .historique-filtres label {
            display: block;
            font-size: 13px;
            font-weight: 600;
            color: var(--titres);
            margin-bottom: 10px;
            letter-spacing: 0.02em;
        }

        .historique-filtres select,
        .historique-filtres input[type="number"],
        .historique-filtres input[type="date"] {
            padding: 14px 18px;
            min-height: 48px;
            border: 2px solid rgba(229, 72, 138, 0.2);
            border-radius: 12px;
            font-size: 16px;
            min-width: 140px;
            background: var(--fond-principal);
            color: var(--texte-fonce);
            transition: border-color 0.2s, box-shadow 0.2s;
        }

        .historique-filtres select:focus,
        .historique-filtres input:focus {
            outline: none;
            border-color: var(--couleur-dominante);
            box-shadow: 0 0 0 3px rgba(229, 72, 138, 0.2);
        }

        .historique-filtres select:hover,
        .historique-filtres input:hover {
            border-color: rgba(229, 72, 138, 0.5);
        }

        .historique-filtres .btn-apply {
            padding: 14px 28px;
            min-height: 48px;
            background: linear-gradient(135deg, var(--couleur-dominante) 0%, #c91f6e 100%);
            color: var(--texte-clair);
            border: none;
            border-radius: 12px;
            cursor: pointer;
            font-weight: 600;
            font-size: 15px;
            box-shadow: var(--ombre-promo);
            transition: transform 0.15s, box-shadow 0.15s;
        }

        .historique-filtres .btn-apply:hover {
            transform: translateY(-1px);
            box-shadow: 0 6px 20px rgba(229, 72, 138, 0.35);
        }

        .historique-filtres .btn-apply:active {
            transform: translateY(0);
        }

        @media (max-width: 768px) {
            .historique-filtres {
                padding: 20px;
                gap: 16px;
            }
            .historique-filtres .form-group {
                flex: 1 1 100%;
            }
            .historique-filtres select,
            .historique-filtres input[type="date"] {
                width: 100%;
                min-width: 0;
            }
        }

        .stats-globales-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 16px;
            margin-bottom: 24px;
        }

        .stat-globale-card {
            background: linear-gradient(135deg, rgba(229, 72, 138, 0.08) 0%, rgba(32, 197, 199, 0.06) 100%);
            border: 1px solid rgba(229, 72, 138, 0.25);
            border-radius: 12px;
            padding: 16px;
        }

        .stat-globale-card h4 {
            font-size: 12px;
            color: var(--texte-fonce);
            margin-bottom: 8px;
            text-transform: uppercase;
        }

        .stat-globale-card .value {
            font-size: 20px;
            font-weight: 700;
            color: var(--titres);
        }

        .stats-periode-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 16px;
            margin-bottom: 24px;
        }

        .stat-periode-card {
            background: var(--fond-principal);
            border: 1px solid rgba(229, 72, 138, 0.15);
            border-radius: 12px;
            padding: 16px;
            box-shadow: var(--ombre-douce);
        }

        .stat-periode-card.highlight {
            border-color: var(--couleur-dominante);
            background: linear-gradient(135deg, rgba(229, 72, 138, 0.1) 0%, rgba(244, 211, 94, 0.08) 100%);
        }

        .stat-periode-card .value {
            color: var(--titres);
        }

        .historique-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 14px;
        }

        .historique-table th,
        .historique-table td {
            padding: 12px 16px;
            text-align: left;
            border-bottom: 1px solid rgba(229, 72, 138, 0.1);
        }

        .historique-table th {
            background: linear-gradient(135deg, var(--couleur-dominante) 0%, #c91f6e 100%);
            font-weight: 600;
            color: var(--texte-clair);
        }

        .historique-table tr:hover td {
            background: rgba(229, 72, 138, 0.04);
        }

        .historique-table .montant {
            font-weight: 600;
            text-align: right;
        }

        @media (max-width: 768px) {
            .historique-table {
                font-size: 12px;
            }

            .historique-table th,
            .historique-table td {
                padding: 8px;
            }
        }
    </style>
</head>

<body>
    <?php include '../includes/nav.php'; ?>

    <div class="content-header">
        <h1><i class="fas fa-chart-line"></i> Historique des ventes & Comptabilité</h1>
        <div class="header-actions">
            <a href="index.php" class="btn-back">
                <i class="fas fa-arrow-left"></i> Retour aux commandes
            </a>
        </div>
    </div>

    <!-- Filtres -->
    <form method="GET" action="historique-ventes.php" class="historique-filtres">
        <div class="form-group">
            <label><i class="fas fa-filter" style="margin-right:6px; opacity:0.7;"></i>Type de période</label>
            <select name="periode" id="periode-select" onchange="togglePeriodeFields(this.value)">
                <option value="jour" <?php echo $periode === 'jour' ? 'selected' : ''; ?>>Jour</option>
                <option value="plage" <?php echo $periode === 'plage' ? 'selected' : ''; ?>>Période</option>
                <option value="annee" <?php echo $periode === 'annee' ? 'selected' : ''; ?>>Année</option>
            </select>
        </div>
        <div class="form-group" id="wrap-jour" style="display:<?php echo $periode === 'jour' ? 'block' : 'none'; ?>">
            <label><i class="fas fa-calendar-day" style="margin-right:6px; opacity:0.7;"></i>Date</label>
            <input type="date" name="date_jour" id="date-jour"
                value="<?php echo sprintf('%04d-%02d-%02d', $annee, $mois, $jour); ?>">
        </div>
        <div class="form-group" id="wrap-plage" style="display:<?php echo $periode === 'plage' ? 'block' : 'none'; ?>">
            <label><i class="fas fa-calendar-plus" style="margin-right:6px; opacity:0.7;"></i>Date début</label>
            <input type="date" name="date_debut" id="date-debut"
                value="<?php echo htmlspecialchars($date_debut ?? date('Y-m-d')); ?>">
        </div>
        <div class="form-group" id="wrap-plage-fin" style="display:<?php echo $periode === 'plage' ? 'block' : 'none'; ?>">
            <label><i class="fas fa-calendar-check" style="margin-right:6px; opacity:0.7;"></i>Date fin</label>
            <input type="date" name="date_fin" id="date-fin"
                value="<?php echo htmlspecialchars($date_fin ?? date('Y-m-d')); ?>">
        </div>
        <div class="form-group" id="wrap-annee"
            style="display:<?php echo $periode === 'annee' ? 'block' : 'none'; ?>">
            <label><i class="fas fa-calendar-alt" style="margin-right:6px; opacity:0.7;"></i>Année</label>
            <select name="annee">
                <?php for ($a = date('Y'); $a >= date('Y') - 5; $a--): ?>
                    <option value="<?php echo $a; ?>" <?php echo $annee === $a ? 'selected' : ''; ?>><?php echo $a; ?>
                    </option>
                <?php endfor; ?>
            </select>
        </div>
        <div class="form-group">
            <label>&nbsp;</label>
            <button type="submit" class="btn-apply"><i class="fas fa-search" style="margin-right:8px;"></i> Appliquer</button>
        </div>
    </form>

    <!-- Stats globales -->
    <section class="content-section">
        <div class="section-title">
            <h2><i class="fas fa-globe"></i> Vue d'ensemble globale</h2>
        </div>
        <div class="stats-globales-grid">
            <div class="stat-globale-card">
                <h4>Montant total (toutes commandes)</h4>
                <div class="value"><?php echo number_format($stats_globales['montant_total'], 0, ',', ' '); ?> FCFA
                </div>
            </div>
            <div class="stat-globale-card">
                <h4>Montant livrées</h4>
                <div class="value"><?php echo number_format($stats_globales['montant_livrees'], 0, ',', ' '); ?> FCFA
                </div>
            </div>
            <div class="stat-globale-card">
                <h4>Montant non traitées</h4>
                <div class="value"><?php echo number_format($stats_globales['montant_non_traitees'], 0, ',', ' '); ?>
                    FCFA</div>
            </div>
            <div class="stat-globale-card">
                <h4>Nb commandes total</h4>
                <div class="value"><?php echo $stats_globales['nb_total']; ?></div>
            </div>
            <div class="stat-globale-card">
                <h4>Nb livrées</h4>
                <div class="value"><?php echo $stats_globales['nb_livrees']; ?></div>
            </div>
            <div class="stat-globale-card">
                <h4>Nb annulées</h4>
                <div class="value"><?php echo $stats_globales['nb_annulees']; ?></div>
            </div>
        </div>
    </section>

    <!-- Stats période sélectionnée -->
    <section class="content-section">
        <div class="section-title">
            <h2><i class="fas fa-calendar-alt"></i> Période : <?php echo htmlspecialchars($libelle_periode); ?></h2>
        </div>
        <div class="stats-periode-grid">
            <div class="stat-periode-card highlight">
                <h4>Montant total période</h4>
                <div class="value"><?php echo number_format($stats['montant_total'], 0, ',', ' '); ?> FCFA</div>
            </div>
            <div class="stat-periode-card">
                <h4>Nb commandes</h4>
                <div class="value"><?php echo $stats['nb_commandes']; ?></div>
            </div>
            <div class="stat-periode-card">
                <h4>Montant livrées</h4>
                <div class="value"><?php echo number_format($stats['montant_livrees'], 0, ',', ' '); ?> FCFA</div>
            </div>
            <div class="stat-periode-card">
                <h4>Montant non traitées</h4>
                <div class="value"><?php echo number_format($stats['montant_non_traitees'], 0, ',', ' '); ?> FCFA</div>
            </div>
        </div>

        <?php if (empty($commandes)): ?>
            <div class="empty-state">
                <i class="fas fa-receipt"></i>
                <h3>Aucune vente sur cette période</h3>
                <p>Modifiez les filtres pour afficher l'historique des ventes.</p>
            </div>
        <?php else: ?>
            <div style="overflow-x: auto;">
                <table class="historique-table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>N° Commande</th>
                            <th>Client</th>
                            <th>Statut</th>
                            <th class="montant">Montant</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($commandes as $c): ?>
                            <tr>
                                <td><?php echo date('d/m/Y H:i', strtotime($c['date_commande'])); ?></td>
                                <td><?php echo htmlspecialchars($c['numero_commande']); ?></td>
                                <td><?php echo htmlspecialchars(trim(($c['user_prenom'] ?? '') . ' ' . ($c['user_nom'] ?? ''))); ?>
                                </td>
                                <td><span
                                        class="commande-statut statut-<?php echo $c['statut']; ?>"><?php echo ucfirst(str_replace('_', ' ', $c['statut'])); ?></span>
                                </td>
                                <td class="montant"><?php echo number_format($c['montant_total'], 0, ',', ' '); ?> FCFA</td>
                                <td><a href="details.php?id=<?php echo $c['id']; ?>" class="btn-link"><i
                                            class="fas fa-eye"></i></a></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>

    <?php include '../includes/footer.php'; ?>

    <script>
        (function () {
            var periodeSelect = document.getElementById('periode-select');
            function togglePeriodeFields(periode) {
                var wrapJour = document.getElementById('wrap-jour');
                var wrapPlage = document.getElementById('wrap-plage');
                var wrapPlageFin = document.getElementById('wrap-plage-fin');
                var wrapAnnee = document.getElementById('wrap-annee');
                if (wrapJour) wrapJour.style.display = periode === 'jour' ? 'block' : 'none';
                if (wrapPlage) wrapPlage.style.display = periode === 'plage' ? 'block' : 'none';
                if (wrapPlageFin) wrapPlageFin.style.display = periode === 'plage' ? 'block' : 'none';
                if (wrapAnnee) wrapAnnee.style.display = periode === 'annee' ? 'block' : 'none';
            }
            if (periodeSelect) {
                periodeSelect.addEventListener('change', function () { togglePeriodeFields(this.value); });
            }
        })();
    </script>
</body>

</html>