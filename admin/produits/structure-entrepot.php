<?php
/**
 * La STRUCTURE de l'entrepôt : navigation par FORAGE — on descend niveau
 * par niveau (Étage → Zone → Rayon → …) au lieu d'afficher un arbre entier
 * illisible. À chaque niveau : le tableau de ce qu'il contient, la création
 * en ligne (et en série), la fiche du niveau ouvert avec ses barres et ses
 * pièces. Programmation procédurale uniquement
 *
 * PORTAGE de fpl_natif/admin/structure-entrepot.php (24/08/2026), au moteur
 * de CE dépôt : la hiérarchie libre (entrepot_hierarchie_niveau /
 * entrepot_hierarchie_noeud + entrepot_etage pour le premier niveau) et ses
 * gestes déjà écrits (entrepot_noeud_ajouter / _modifier / _supprimer,
 * sous-arbres, comptages). PAS de multi-entrepôts : un seul bâtiment ici —
 * décision du 24/08, comme l'écart du gestionnaire d'étage.
 * Les niveaux eux-mêmes (renommer, réordonner, lier l'étiquette) se gèrent
 * dans la page Hiérarchie de ce dépôt, qui affiche les impacts — gardée.
 */

session_start();

if (!isset($_SESSION['admin_id']) || !isset($_SESSION['admin_email'])) {
    header('Location: ../login.php');
    exit;
}

require_once __DIR__ . '/../includes/require_access.php';
require_once __DIR__ . '/../../includes/admin_permissions.php';
require_once __DIR__ . '/../../includes/fpl_texte.php';
require_once __DIR__ . '/../../includes/fpl_ui.php';
require_once __DIR__ . '/../../models/model_entrepot_hierarchie.php';
require_once __DIR__ . '/../../models/model_entrepot_hierarchie_libre.php';

if (!admin_can_gestion_stock()) {
    header('Location: ../dashboard.php');
    exit;
}
if (empty($_SESSION['admin_csrf'])) {
    $_SESSION['admin_csrf'] = bin2hex(random_bytes(32));
}

$erreurs_page = [];

/* =====================================================================
 * LES NIVEAUX : le premier est l'ÉTAGE (table entrepot_etage), les
 * suivants sont les niveaux de nœuds, dans l'ordre configuré.
 * =================================================================== */
$defs = entrepot_hierarchie_def_list(true);
$defs_noeuds = array_values(array_filter($defs, function ($d) {
    return (string) ($d['slug'] ?? '') !== 'etage';
}));
$def_etage = null;
foreach ($defs as $d) {
    if ((string) ($d['slug'] ?? '') === 'etage') {
        $def_etage = $d;
        break;
    }
}
$label_etage = $def_etage !== null ? (string) $def_etage['label'] : 'Étage';

/** L'indice (0 = premier niveau de nœuds) d'un niveau par son id. */
$indice_niveau = function ($niveau_id) use ($defs_noeuds) {
    foreach ($defs_noeuds as $i => $d) {
        if ((int) $d['id'] === (int) $niveau_id) {
            return $i;
        }
    }

    return -1;
};

/* =====================================================================
 * LES GESTES (PRG : succès → redirection sur la même adresse)
 * =================================================================== */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $jeton = isset($_POST['csrf_token']) ? (string) $_POST['csrf_token'] : '';
    if ($jeton === '' || !hash_equals((string) $_SESSION['admin_csrf'], $jeton)) {
        $erreurs_page[] = 'La page a expiré. Rechargez-la puis réessayez.';
    } else {
        $action = isset($_POST['action']) ? (string) $_POST['action'] : '';
        $ici = 'structure-entrepot.php' . (isset($_GET['loc']) ? '?loc=' . (int) $_GET['loc']
            : (isset($_GET['etage']) ? '?etage=' . (int) $_GET['etage'] : ''));

        if ($action === 'etage_creer') {
            $nom = trim((string) ($_POST['nom'] ?? ''));
            $lettre = strtoupper(trim((string) ($_POST['lettre'] ?? '')));
            if ($nom === '' || mb_strlen($nom) > 60) {
                $erreurs_page[] = 'Donnez un nom à l\'étage (ex. RDC, Étage 2…).';
            } elseif (!preg_match('/^[A-Z0-9]{1,3}$/', $lettre)) {
                $erreurs_page[] = 'La lettre de l\'étage : 1 à 3 lettres ou chiffres (ex. A, B, C).';
            } else {
                try {
                    $doublon = $db->prepare('SELECT COUNT(*) FROM entrepot_etage WHERE actif = 1 AND UPPER(code_abrege) = :l');
                    $doublon->execute(['l' => $lettre]);
                    if ((int) $doublon->fetchColumn() > 0) {
                        $erreurs_page[] = 'La lettre « ' . $lettre . ' » sert déjà à un autre étage — chaque étage a la sienne (elle ouvre les libellés d\'étiquettes).';
                    } else {
                        $num = (int) $db->query('SELECT COALESCE(MAX(numero_etage), 0) + 1 FROM entrepot_etage')->fetchColumn();
                        $db->prepare('INSERT INTO entrepot_etage (numero_etage, nom, code, code_abrege, actif, date_modification)
                                      VALUES (:n, :nom, :code, :ab, 1, NOW())')
                           ->execute(['n' => $num, 'nom' => $nom, 'code' => 'E' . $num, 'ab' => $lettre]);
                        $_SESSION['success_message'] = $label_etage . ' « ' . $nom . ' » créé — ouvrez-le pour bâtir dedans.';
                        header('Location: ' . $ici);
                        exit;
                    }
                } catch (PDOException $e) {
                    $erreurs_page[] = 'La création a échoué — réessayez.';
                }
            }
        } elseif ($action === 'etage_maj') {
            $nom = trim((string) ($_POST['nom'] ?? ''));
            $lettre = strtoupper(trim((string) ($_POST['lettre'] ?? '')));
            $eid = (int) ($_POST['id'] ?? 0);
            if ($nom === '' || !preg_match('/^[A-Z0-9]{1,3}$/', $lettre)) {
                $erreurs_page[] = 'Le nom et la lettre (1 à 3 caractères) sont obligatoires.';
            } else {
                try {
                    $doublon = $db->prepare('SELECT COUNT(*) FROM entrepot_etage WHERE actif = 1 AND UPPER(code_abrege) = :l AND id <> :id');
                    $doublon->execute(['l' => $lettre, 'id' => $eid]);
                    if ((int) $doublon->fetchColumn() > 0) {
                        $erreurs_page[] = 'La lettre « ' . $lettre . ' » sert déjà à un autre étage.';
                    } else {
                        $db->prepare('UPDATE entrepot_etage SET nom = :nom, code_abrege = :ab, date_modification = NOW() WHERE id = :id')
                           ->execute(['nom' => $nom, 'ab' => $lettre, 'id' => $eid]);
                        $_SESSION['success_message'] = $label_etage . ' mis à jour — les libellés d\'étiquettes suivront la lettre.';
                        header('Location: ' . $ici);
                        exit;
                    }
                } catch (PDOException $e) {
                    $erreurs_page[] = 'La mise à jour a échoué — réessayez.';
                }
            }
        } elseif ($action === 'noeud_creer') {
            $nom = trim((string) ($_POST['nom'] ?? ''));
            $etage_id = (int) ($_POST['etage_id'] ?? 0);
            $niveau_id = (int) ($_POST['niveau_id'] ?? 0);
            $parent_id = (int) ($_POST['parent_id'] ?? 0);
            $combien = max(1, min(50, (int) ($_POST['combien'] ?? 1)));
            if ($nom === '' || mb_strlen($nom) > 120) {
                $erreurs_page[] = 'Donnez un nom à l\'emplacement.';
            } else {
                $crees = 0;
                $dernier_message = '';
                for ($i = 0; $i < $combien; $i++) {
                    // En série : « B » → B1, B2, B3… ; à l'unité : le nom tel quel.
                    $nom_i = $combien > 1 ? ($nom . ($i + 1)) : $nom;
                    $res = entrepot_noeud_ajouter($etage_id, $niveau_id, $parent_id, $nom_i, 0);
                    if (!empty($res['success'])) {
                        $crees++;
                    } else {
                        $dernier_message = (string) ($res['message'] ?? 'La création a échoué.');
                        break;
                    }
                }
                if ($crees > 0) {
                    $_SESSION['success_message'] = $crees > 1
                        ? $crees . ' emplacements créés (' . $nom . '1 → ' . $nom . $crees . ').'
                        : 'Emplacement « ' . $nom . ' » créé.';
                    if ($dernier_message !== '') {
                        $_SESSION['success_message'] .= ' Puis : ' . $dernier_message;
                    }
                    header('Location: ' . $ici);
                    exit;
                }
                $erreurs_page[] = $dernier_message !== '' ? $dernier_message : 'La création a échoué.';
            }
        } elseif ($action === 'noeud_maj') {
            $res = entrepot_noeud_modifier((int) ($_POST['id'] ?? 0), trim((string) ($_POST['nom'] ?? '')), (int) ($_POST['numero'] ?? 0));
            if (!empty($res['success'])) {
                $_SESSION['success_message'] = 'Emplacement mis à jour — les libellés d\'étiquettes ont suivi.';
                header('Location: ' . $ici);
                exit;
            }
            $erreurs_page[] = (string) ($res['message'] ?? 'La mise à jour a échoué.');
        } elseif ($action === 'noeud_supprimer') {
            $vers_parent = '';
            $noeud_avant = entrepot_noeud_get((int) ($_POST['id'] ?? 0));
            if ($noeud_avant !== null) {
                $vers_parent = !empty($noeud_avant['parent_id'])
                    ? 'structure-entrepot.php?loc=' . (int) $noeud_avant['parent_id']
                    : 'structure-entrepot.php?etage=' . (int) $noeud_avant['etage_id'];
            }
            $res = entrepot_noeud_supprimer((int) ($_POST['id'] ?? 0));
            if (!empty($res['success'])) {
                $_SESSION['success_message'] = (string) $res['message'];
                // On supprimait l'emplacement OUVERT : sa page n'existe plus,
                // on remonte au parent. L'adresse est construite ICI.
                header('Location: ' . (!empty($_POST['retour_parent']) && $vers_parent !== '' ? $vers_parent : $ici));
                exit;
            }
            $erreurs_page[] = (string) ($res['message'] ?? 'La suppression a échoué.');
        }
    }
}

/* =====================================================================
 * OÙ SOMMES-NOUS ? (racine, un étage, ou un nœud foré)
 * =================================================================== */
$etages = entrepot_hierarchie_liste_niveaux();

$courant = null;          // le nœud ouvert (ou null)
$etage_courant = null;    // l'étage ouvert (toujours posé si un nœud l'est)
if (!empty($_GET['loc'])) {
    $courant = entrepot_noeud_get((int) $_GET['loc']);
}
if ($courant !== null) {
    foreach ($etages as $e) {
        if ((int) $e['id'] === (int) $courant['etage_id']) {
            $etage_courant = $e;
            break;
        }
    }
} elseif (!empty($_GET['etage'])) {
    foreach ($etages as $e) {
        if ((int) $e['id'] === (int) $_GET['etage']) {
            $etage_courant = $e;
            break;
        }
    }
}

// Le fil d'Ariane du nœud ouvert (du haut vers lui)
$fil = [];
if ($courant !== null) {
    $marche = $courant;
    $garde = 0;
    while ($marche !== null && $garde < 20) {
        array_unshift($fil, $marche);
        $marche = !empty($marche['parent_id']) ? entrepot_noeud_get((int) $marche['parent_id']) : null;
        $garde++;
    }
}

// Le(s) niveau(x) des ENFANTS listés. Quand le niveau suivant est un
// contenant-feuille à QR (barre), ses FRÈRES contenants (box…) sont proposés
// AU MÊME endroit : sous une étagère, on peut créer une barre OU une box.
$def_enfants = null;   // le premier (compat des libellés)
$defs_enfants = [];    // tous les niveaux enfants proposés à ce cran
if ($courant !== null) {
    $idx = $indice_niveau((int) $courant['niveau_id']);
    if ($idx >= 0 && isset($defs_noeuds[$idx + 1])) {
        $suivant = $defs_noeuds[$idx + 1];
        if ((int) ($suivant['est_etiquette_qr'] ?? 0) === 1) {
            // tous les contenants frères contigus (barre, box…)
            for ($t = $idx + 1; $t < count($defs_noeuds); $t++) {
                if ((int) ($defs_noeuds[$t]['est_etiquette_qr'] ?? 0) === 1) {
                    $defs_enfants[] = $defs_noeuds[$t];
                } else {
                    break;
                }
            }
        } else {
            $defs_enfants = [$suivant];
        }
    }
} elseif ($etage_courant !== null) {
    if (isset($defs_noeuds[0])) {
        $defs_enfants = [$defs_noeuds[0]];
    }
}
$def_enfants = $defs_enfants[0] ?? null;

// Les enfants eux-mêmes (de TOUS les niveaux proposés à ce cran), triés par
// niveau puis numéro pour un affichage stable.
$enfants = [];
if ($etage_courant !== null && $defs_enfants !== []) {
    foreach ($defs_enfants as $de) {
        $lot = entrepot_noeud_liste((int) $etage_courant['id'], (int) $de['id'],
            $courant !== null ? (int) $courant['id'] : 0);
        foreach ($lot as $n) {
            $enfants[] = $n;
        }
    }
}

/** Sous-arbre + pièces d'un nœud (pour les compteurs de lignes). */
$compte_noeud = function ($noeud_id) {
    $ids = entrepot_noeud_collect_ids_avec_descendants([(int) $noeud_id]);

    return ['descendants' => max(0, count($ids) - 1), 'pieces' => entrepot_noeud_compter_produits($ids), 'ids' => $ids];
};

/* La FICHE du niveau ouvert : récap par niveau + pièces + barres. */
$recap = [];
$pieces = null;
$barres_fiche = null;
$sous_ids = [];
$slug_barre_id = 0;
foreach ($defs_noeuds as $d) {
    if ((string) $d['slug'] === 'barre') {
        $slug_barre_id = (int) $d['id'];
    }
}
if ($courant !== null || $etage_courant !== null) {
    global $db;
    if ($courant !== null) {
        $sous_ids = entrepot_noeud_collect_ids_avec_descendants([(int) $courant['id']]);
    } else {
        try {
            $st = $db->prepare('SELECT id FROM entrepot_hierarchie_noeud WHERE etage_id = :e AND sync_deleted_at IS NULL');
            $st->execute(['e' => (int) $etage_courant['id']]);
            $sous_ids = array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN));
        } catch (PDOException $e) {
            $sous_ids = [];
        }
    }
    if ($sous_ids !== []) {
        // Le récapitulatif compte le CONTENU — jamais le niveau ouvert lui-même.
        $ids_contenu = $courant !== null
            ? array_values(array_diff($sous_ids, [(int) $courant['id']]))
            : $sous_ids;
        try {
            $recap = [];
            if ($ids_contenu !== []) {
                $ph = implode(',', array_fill(0, count($ids_contenu), '?'));
                $st = $db->prepare("SELECT v.label, COUNT(*) AS n
                                    FROM entrepot_hierarchie_noeud n
                                    JOIN entrepot_hierarchie_niveau v ON v.id = n.niveau_id
                                    WHERE n.id IN ($ph) AND n.sync_deleted_at IS NULL
                                    GROUP BY v.id, v.label ORDER BY v.ordre, v.id");
                $st->execute($ids_contenu);
                $recap = $st->fetchAll(PDO::FETCH_ASSOC);
            }
        } catch (PDOException $e) {
            $recap = [];
        }
        $recap_pieces = entrepot_noeud_compter_produits($sous_ids);

        // LES PIÈCES rangées dans ce sous-arbre — une ligne par pièce, paginée
        $par_p = fpl_par_page('structure_pieces', 10);
        $page_p = max(1, (int) ($_GET['pp'] ?? 1));
        try {
            $ph = implode(',', array_fill(0, count($sous_ids), '?'));
            $st = $db->prepare("SELECT COUNT(*) FROM produits WHERE entrepot_noeud_id IN ($ph) AND sync_deleted_at IS NULL");
            $st->execute($sous_ids);
            $total_p = (int) $st->fetchColumn();
            $derniere_p = max(1, (int) ceil($total_p / $par_p));
            $page_p = min($page_p, $derniere_p);
            $st = $db->prepare("SELECT p.id, p.nom, p.identifiant_interne, p.stock, p.entrepot_noeud_id
                                FROM produits p
                                WHERE p.entrepot_noeud_id IN ($ph) AND p.sync_deleted_at IS NULL
                                ORDER BY p.nom
                                LIMIT " . (int) $par_p . ' OFFSET ' . (($page_p - 1) * $par_p));
            $st->execute($sous_ids);
            $pieces = ['lignes' => $st->fetchAll(PDO::FETCH_ASSOC), 'total' => $total_p,
                'page' => $page_p, 'par' => $par_p, 'derniere' => $derniere_p];
        } catch (PDOException $e) {
            $pieces = null;
        }

        // LES BARRES du sous-arbre — quand on est AU-DESSUS d'elles
        if ($slug_barre_id > 0 && ($courant === null || (int) $courant['niveau_id'] !== $slug_barre_id)) {
            $par_b = fpl_par_page('structure_barres', 10);
            $page_b = max(1, (int) ($_GET['pb'] ?? 1));
            try {
                $ph = implode(',', array_fill(0, count($sous_ids), '?'));
                $params_b = $sous_ids;
                $params_b[] = $slug_barre_id;
                $st = $db->prepare("SELECT COUNT(*) FROM entrepot_hierarchie_noeud
                                    WHERE id IN ($ph) AND niveau_id = ? AND sync_deleted_at IS NULL");
                $st->execute($params_b);
                $total_b = (int) $st->fetchColumn();
                if ($total_b > 0) {
                    $derniere_b = max(1, (int) ceil($total_b / $par_b));
                    $page_b = min($page_b, $derniere_b);
                    $st = $db->prepare("SELECT * FROM entrepot_hierarchie_noeud
                                        WHERE id IN ($ph) AND niveau_id = ? AND sync_deleted_at IS NULL
                                        ORDER BY numero, nom
                                        LIMIT " . (int) $par_b . ' OFFSET ' . (($page_b - 1) * $par_b));
                    $st->execute($params_b);
                    $barres_fiche = ['lignes' => $st->fetchAll(PDO::FETCH_ASSOC), 'total' => $total_b,
                        'page' => $page_b, 'par' => $par_b, 'derniere' => $derniere_b];
                }
            } catch (PDOException $e) {
                $barres_fiche = null;
            }
        }
    } else {
        $recap_pieces = 0;
    }
}

/* LA RECHERCHE GLOBALE (racine seulement) */
$q = isset($_GET['q']) ? trim((string) $_GET['q']) : '';
$resultats = [];
if ($q !== '' && $courant === null && $etage_courant === null) {
    global $db;
    try {
        $st = $db->prepare("SELECT n.*, v.label AS niveau_label
                            FROM entrepot_hierarchie_noeud n
                            JOIN entrepot_hierarchie_niveau v ON v.id = n.niveau_id
                            WHERE n.sync_deleted_at IS NULL
                              AND (n.nom LIKE :q1 OR n.code_scan LIKE :q2 OR CAST(n.numero AS CHAR) = :q3)
                            ORDER BY v.ordre, n.numero, n.nom
                            LIMIT 30");
        $st->execute(['q1' => '%' . $q . '%', 'q2' => '%' . $q . '%', 'q3' => $q]);
        $resultats = $st->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $resultats = [];
    }
}

$success_message = $_SESSION['success_message'] ?? null;
unset($_SESSION['success_message']);

$fpl_titre_page = 'Structure de l\'entrepôt';
/* LE RETOUR REMONTE D'UN CRAN — comme le fil d'Ariane, jamais un saut sec
 * vers Mon travail. Foré dans un nœud → son parent (ou l'étage s'il n'en a
 * pas) ; posé sur un étage → la racine de l'entrepôt ; à la racine seulement
 * → Mon travail. */
if ($courant !== null) {
    $fpl_retour_page = !empty($courant['parent_id'])
        ? 'structure-entrepot.php?loc=' . (int) $courant['parent_id']
        : 'structure-entrepot.php?etage=' . (int) $courant['etage_id'];
} elseif ($etage_courant !== null) {
    $fpl_retour_page = 'structure-entrepot.php';
} else {
    $fpl_retour_page = 'mon-travail.php';
}
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <?php include __DIR__ . '/../../includes/favicon.php'; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Structure de l'entrepôt — Administration</title>
    <?php require_once __DIR__ . '/../../includes/asset_version.php'; ?>
<?php include __DIR__ . '/../includes/fpl_head.php'; ?>
    <?php fpl_css_link('admin-produits-index.css'); ?>
</head>

<body class="fpl-catalogue">
    <?php include '../includes/nav.php'; ?>

    <div class="page-produits-admin">

    <?php if ($success_message) : ?>
      <div class="alert alert-success" role="status"><?php echo e($success_message); ?></div>
    <?php endif; ?>
    <?php foreach ($erreurs_page as $err) : ?>
      <div class="alert alert-error" role="alert"><?php echo e($err); ?></div>
    <?php endforeach; ?>

    <?php // ===== LES NIVEAUX DE RANGEMENT — la chaîne, en lecture ===== ?>
    <div class="card" style="margin-bottom:var(--s4)">
      <div class="card-head">
        <h2>Niveaux de rangement</h2>
        <a href="../parametres/hierarchie-entrepot.php" class="btn btn-blue btn-sm">
          <?php echo fpl_icone('settings', 13); ?> Gérer les niveaux (renommer, réordonner, ajouter…)
        </a>
      </div>
      <div class="level-chain">
        <?php $fil_labels = [];
        if ($etage_courant !== null) { $fil_labels[] = 'etage'; }
        foreach ($fil as $f) { $fil_labels[] = (int) $f['niveau_id']; } ?>
        <span class="level-chip <?php echo $etage_courant !== null ? 'on' : ''; ?>"><?php echo e($label_etage); ?></span>
        <?php foreach ($defs_noeuds as $i => $d) : ?>
          <?php echo fpl_icone('chevron-right', 12); ?>
          <span class="level-chip <?php echo in_array((int) $d['id'], $fil_labels, true) ? 'on' : ''; ?>"><?php echo fpl_e($d['label']); ?></span>
        <?php endforeach; ?>
      </div>
    </div>

    <?php // ===== LE FIL D'ARIANE ===== ?>
    <?php if ($etage_courant !== null) : ?>
      <div class="fpl-fil" style="margin-bottom:var(--s3)">
        <a href="structure-entrepot.php">Entrepôt</a>
        <span class="fpl-fil__sep">›</span>
        <?php if ($courant === null) : ?>
          <strong><?php echo fpl_e($etage_courant['nom']); ?></strong>
        <?php else : ?>
          <a href="structure-entrepot.php?etage=<?php echo (int) $etage_courant['id']; ?>"><?php echo fpl_e($etage_courant['nom']); ?></a>
          <?php foreach ($fil as $i => $f) : ?>
            <span class="fpl-fil__sep">›</span>
            <?php if ($i === count($fil) - 1) : ?>
              <strong><?php echo fpl_e($f['nom']); ?></strong>
            <?php else : ?>
              <a href="structure-entrepot.php?loc=<?php echo (int) $f['id']; ?>"><?php echo fpl_e($f['nom']); ?></a>
            <?php endif; ?>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
    <?php endif; ?>

    <?php // ===== LA RACINE : recherche + les étages ===== ?>
    <?php if ($etage_courant === null) : ?>
      <div class="card">
        <div class="card-head">
          <h2><?php echo count($etages); ?> <?php echo e(mb_strtolower($label_etage)); ?><?php echo count($etages) > 1 ? 's' : ''; ?> — Entrepôt</h2>
        </div>

        <form method="GET" action="structure-entrepot.php" class="scan-bar" style="margin-bottom:var(--s3)">
          <span style="color:var(--blue-600); display:flex"><?php echo fpl_icone('search', 18); ?></span>
          <input type="text" name="q" value="<?php echo e($q); ?>" placeholder="Chercher un nom, un numéro ou un code dans tout l'entrepôt…">
        </form>

        <?php if ($q !== '') : ?>
          <div class="muted" style="margin-bottom:var(--s2)"><?php echo count($resultats); ?> résultat(s) pour « <?php echo e($q); ?> » — <a href="structure-entrepot.php">effacer</a></div>
          <?php if ($resultats !== []) : ?>
            <div class="table-wrap" style="margin-bottom:var(--s4)">
              <table>
                <thead><tr><th>Niveau</th><th>Nom</th><th>Emplacement complet</th><th style="width:110px"></th></tr></thead>
                <tbody>
                  <?php foreach ($resultats as $r) : ?>
                    <tr>
                      <td class="muted"><?php echo fpl_e($r['niveau_label']); ?></td>
                      <td><strong><?php echo fpl_e($r['nom']); ?></strong></td>
                      <td class="muted"><?php echo fpl_e(entrepot_noeud_chemin_libelle((int) $r['id'])); ?></td>
                      <td><a href="structure-entrepot.php?loc=<?php echo (int) $r['id']; ?>" class="btn btn-outline btn-sm">Ouvrir</a></td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          <?php endif; ?>
        <?php endif; ?>

        <?php if ($etages === []) : ?>
          <div class="empty">
            <span class="big"><?php echo fpl_icone('layers', 32); ?></span>
            Aucun <?php echo e(mb_strtolower($label_etage)); ?> — créez le premier ci-dessous.
          </div>
        <?php else : ?>
          <div class="table-wrap">
            <table>
              <thead>
                <tr>
                  <th style="width:70px">Lettre</th>
                  <th>Nom</th>
                  <th>Contenu</th>
                  <th class="num">Pièces rangées</th>
                  <th style="width:110px"></th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($etages as $e_ligne) : ?>
                  <?php
                  global $db;
                  $ids_etage = [];
                  try {
                      $st = $db->prepare('SELECT id FROM entrepot_hierarchie_noeud WHERE etage_id = :e AND sync_deleted_at IS NULL');
                      $st->execute(['e' => (int) $e_ligne['id']]);
                      $ids_etage = array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN));
                  } catch (PDOException $ex) {
                  }
                  $pieces_etage = $ids_etage !== [] ? entrepot_noeud_compter_produits($ids_etage) : 0;
                  ?>
                  <tr>
                    <td><span class="chip-code" style="font-size:14px"><?php echo e((string) $e_ligne['code_abrege']); ?></span></td>
                    <td><a class="cell-title" href="structure-entrepot.php?etage=<?php echo (int) $e_ligne['id']; ?>" style="color:var(--ink)"><?php echo fpl_e($e_ligne['nom']); ?></a></td>
                    <td class="muted"><?php echo count($ids_etage); ?> emplacement(s)</td>
                    <td class="num"><?php echo (int) $pieces_etage; ?></td>
                    <td><a href="structure-entrepot.php?etage=<?php echo (int) $e_ligne['id']; ?>" class="btn btn-outline btn-sm">Ouvrir</a></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>

        <?php // Création simple : un nom, une lettre ?>
        <form method="POST" action="structure-entrepot.php" class="create-bar" style="margin-top:var(--s4)">
          <input type="hidden" name="csrf_token" value="<?php echo e($_SESSION['admin_csrf']); ?>">
          <input type="hidden" name="action" value="etage_creer">
          <div class="cb-field" style="flex:2; min-width:170px">
            <label>Nom du nouvel <?php echo e(mb_strtolower($label_etage)); ?></label>
            <input type="text" name="nom" required maxlength="60" placeholder="Ex. Étage 2">
          </div>
          <div class="cb-field" style="width:120px">
            <label>Sa lettre</label>
            <input type="text" name="lettre" required maxlength="3" placeholder="C" style="text-transform:uppercase">
          </div>
          <button type="submit" class="btn btn-primary"><?php echo fpl_icone('plus', 14); ?> Créer</button>
        </form>
      </div>
    <?php endif; ?>

    <?php // ===== LA FICHE DU NIVEAU OUVERT (étage ou nœud) ===== ?>
    <?php if ($etage_courant !== null) : ?>
      <div class="card" style="margin-bottom:var(--s4)">
        <div class="card-head" style="display:block">
          <h2>
            <?php if ($courant === null) : ?>
              <?php echo e($label_etage); ?> — <?php echo fpl_e($etage_courant['nom']); ?>
            <?php else : ?>
              <?php $def_courant = null;
              foreach ($defs_noeuds as $d) { if ((int) $d['id'] === (int) $courant['niveau_id']) { $def_courant = $d; break; } } ?>
              <?php echo fpl_e($def_courant !== null ? $def_courant['label'] : 'Emplacement'); ?> — <?php echo fpl_e($courant['nom']); ?>
            <?php endif; ?>
          </h2>
          <div class="muted" style="font-size:13px; margin-top:2px">Renommez-le ici même<?php echo $courant === null ? ' — la lettre ouvre les libellés d\'étiquettes' : ''; ?>.</div>
        </div>

        <?php if ($courant === null) : ?>
          <form method="POST" action="structure-entrepot.php?etage=<?php echo (int) $etage_courant['id']; ?>" class="create-bar">
            <input type="hidden" name="csrf_token" value="<?php echo e($_SESSION['admin_csrf']); ?>">
            <input type="hidden" name="action" value="etage_maj">
            <input type="hidden" name="id" value="<?php echo (int) $etage_courant['id']; ?>">
            <div class="cb-field" style="flex:2; min-width:190px">
              <label>Nom</label>
              <input type="text" name="nom" value="<?php echo fpl_e($etage_courant['nom']); ?>" required maxlength="60">
            </div>
            <div class="cb-field" style="width:110px">
              <label>Lettre</label>
              <input type="text" name="lettre" value="<?php echo e((string) $etage_courant['code_abrege']); ?>" required maxlength="3" style="text-transform:uppercase">
            </div>
            <button type="submit" class="btn btn-primary"><?php echo fpl_icone('save', 14); ?> Enregistrer</button>
          </form>
        <?php else : ?>
          <form method="POST" action="structure-entrepot.php?loc=<?php echo (int) $courant['id']; ?>" class="create-bar">
            <input type="hidden" name="csrf_token" value="<?php echo e($_SESSION['admin_csrf']); ?>">
            <input type="hidden" name="action" value="noeud_maj">
            <input type="hidden" name="id" value="<?php echo (int) $courant['id']; ?>">
            <div class="cb-field" style="flex:2; min-width:190px">
              <label>Nom</label>
              <input type="text" name="nom" value="<?php echo fpl_e($courant['nom']); ?>" required maxlength="120">
            </div>
            <div class="cb-field" style="width:110px">
              <label>Numéro</label>
              <input type="number" name="numero" value="<?php echo (int) $courant['numero']; ?>" min="1" step="1">
            </div>
            <button type="submit" class="btn btn-primary"><?php echo fpl_icone('save', 14); ?> Enregistrer</button>
            <?php if ((int) $courant['niveau_id'] === $slug_barre_id) : ?>
              <a href="etiquette-barre.php?id=<?php echo (int) $courant['id']; ?>" class="btn btn-outline">
                <?php echo fpl_icone('tag', 13); ?> Étiquette
              </a>
            <?php endif; ?>
          </form>
          <?php // La suppression : un formulaire À PART (jamais imbriqué). ?>
          <form method="POST" action="structure-entrepot.php?loc=<?php echo (int) $courant['id']; ?>" style="margin-top:var(--s3)"
                onsubmit="return confirm('Supprimer « <?php echo e(addslashes(fpl_texte($courant['nom']))); ?> » et tout ce qu\'il contient ?\n\nLes pièces rangées dedans seront détachées (sans emplacement).')">
            <input type="hidden" name="csrf_token" value="<?php echo e($_SESSION['admin_csrf']); ?>">
            <input type="hidden" name="action" value="noeud_supprimer">
            <input type="hidden" name="id" value="<?php echo (int) $courant['id']; ?>">
            <input type="hidden" name="retour_parent" value="1">
            <button type="submit" class="btn btn-outline btn-sm" style="color:var(--danger, #B23A31)">
              <?php echo fpl_icone('x', 13); ?> Supprimer cet emplacement
            </button>
          </form>
        <?php endif; ?>

        <?php if ($recap !== [] || ($recap_pieces ?? 0) > 0) : ?>
          <div class="fiche-facts" style="margin-top:var(--s3); display:flex; gap:var(--s4); flex-wrap:wrap">
            <?php foreach ($recap as $r) : ?>
              <div>
                <div class="muted" style="font-size:11px; text-transform:uppercase; letter-spacing:.06em"><?php echo fpl_e($r['label']); ?></div>
                <div style="font-size:20px; font-weight:750; color:var(--navy)"><?php echo (int) $r['n']; ?></div>
              </div>
            <?php endforeach; ?>
            <div>
              <div class="muted" style="font-size:11px; text-transform:uppercase; letter-spacing:.06em">Pièces rangées</div>
              <div style="font-size:20px; font-weight:750; color:var(--navy)"><?php echo (int) ($recap_pieces ?? 0); ?></div>
            </div>
          </div>
        <?php endif; ?>
      </div>
    <?php endif; ?>

    <?php // ===== LES ENFANTS DU NIVEAU OUVERT ===== ?>
    <?php if ($etage_courant !== null) : ?>
      <div class="card" style="margin-bottom:var(--s4)">
        <div class="card-head">
          <h2>
            <?php if ($def_enfants !== null) : ?>
              <?php echo count($enfants); ?> <?php echo fpl_e(mb_strtolower($def_enfants['label'])); ?>(s)
              dans <?php echo fpl_e($courant !== null ? $courant['nom'] : $etage_courant['nom']); ?>
            <?php else : ?>
              Dernier niveau atteint — les pièces se rangent ici
            <?php endif; ?>
          </h2>
        </div>

        <?php if ($def_enfants !== null) : ?>
          <?php // Création simple + EN SÉRIE : un nom, combien — rien d'autre ?>
          <form method="POST" action="<?php echo $courant !== null ? 'structure-entrepot.php?loc=' . (int) $courant['id'] : 'structure-entrepot.php?etage=' . (int) $etage_courant['id']; ?>" class="create-bar">
            <input type="hidden" name="csrf_token" value="<?php echo e($_SESSION['admin_csrf']); ?>">
            <input type="hidden" name="action" value="noeud_creer">
            <input type="hidden" name="etage_id" value="<?php echo (int) $etage_courant['id']; ?>">
            <input type="hidden" name="parent_id" value="<?php echo $courant !== null ? (int) $courant['id'] : 0; ?>">
            <?php if (count($defs_enfants) > 1) : ?>
              <div class="cb-field" style="width:150px">
                <label>Type</label>
                <select name="niveau_id">
                  <?php foreach ($defs_enfants as $de) : ?>
                    <option value="<?php echo (int) $de['id']; ?>"><?php echo fpl_e($de['label']); ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
            <?php else : ?>
              <input type="hidden" name="niveau_id" value="<?php echo (int) $def_enfants['id']; ?>">
            <?php endif; ?>
            <div class="cb-field" style="flex:2; min-width:170px">
              <label>Nom <span class="muted">(en série : « B » donne B1, B2…)</span></label>
              <input type="text" name="nom" required placeholder="<?php echo fpl_e($def_enfants['label']); ?> 1">
            </div>
            <div class="cb-field" style="width:110px">
              <label>Combien ?</label>
              <input type="number" name="combien" value="1" min="1" max="50" step="1">
            </div>
            <button type="submit" class="btn btn-primary"><?php echo fpl_icone('plus', 14); ?> Créer</button>
          </form>

          <?php if ($enfants === []) : ?>
            <div class="empty" style="margin-top:var(--s3)">
              <span class="big"><?php echo fpl_icone('layers', 30); ?></span>
              Rien ici pour l'instant — créez le premier <?php echo fpl_e(mb_strtolower($def_enfants['label'])); ?> ci-dessus.
            </div>
          <?php else : ?>
            <div class="table-wrap" style="margin-top:var(--s3)">
              <table>
                <thead>
                  <tr>
                    <th style="width:80px" class="num">N°</th>
                    <th>Nom</th>
                    <th>Contenu</th>
                    <th class="num">Pièces</th>
                    <th style="width:210px"></th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($enfants as $enfant) : ?>
                    <?php $c = $compte_noeud((int) $enfant['id']); ?>
                    <tr>
                      <td class="num muted"><?php echo (int) $enfant['numero']; ?></td>
                      <td><a class="cell-title" href="structure-entrepot.php?loc=<?php echo (int) $enfant['id']; ?>" style="color:var(--ink)"><?php echo fpl_e($enfant['nom']); ?></a></td>
                      <td class="muted"><?php echo $c['descendants'] > 0 ? $c['descendants'] . ' sous-emplacement(s)' : '—'; ?></td>
                      <td class="num"><?php echo (int) $c['pieces']; ?></td>
                      <td>
                        <div class="row-actions">
                          <a href="structure-entrepot.php?loc=<?php echo (int) $enfant['id']; ?>" class="btn btn-outline btn-sm">Ouvrir</a>
                          <?php if ((int) $enfant['niveau_id'] === $slug_barre_id) : ?>
                            <a href="etiquette-barre.php?id=<?php echo (int) $enfant['id']; ?>" class="btn btn-outline btn-sm btn-icon" title="Étiquette de la barre">
                              <?php echo fpl_icone('tag', 13); ?>
                            </a>
                          <?php endif; ?>
                          <form method="POST" action="<?php echo $courant !== null ? 'structure-entrepot.php?loc=' . (int) $courant['id'] : 'structure-entrepot.php?etage=' . (int) $etage_courant['id']; ?>" style="display:inline"
                                onsubmit="return confirm('Supprimer « <?php echo e(addslashes(fpl_texte($enfant['nom']))); ?> »<?php echo $c['descendants'] > 0 ? ' et ses ' . $c['descendants'] . ' sous-emplacement(s)' : ''; ?> ?<?php echo $c['pieces'] > 0 ? '\n\n' . $c['pieces'] . ' pièce(s) rangée(s) dedans seront détachées.' : ''; ?>')">
                            <input type="hidden" name="csrf_token" value="<?php echo e($_SESSION['admin_csrf']); ?>">
                            <input type="hidden" name="action" value="noeud_supprimer">
                            <input type="hidden" name="id" value="<?php echo (int) $enfant['id']; ?>">
                            <button type="submit" class="btn btn-outline btn-sm btn-icon" title="Supprimer" style="color:var(--danger, #B23A31)">
                              <?php echo fpl_icone('x', 13); ?>
                            </button>
                          </form>
                        </div>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          <?php endif; ?>
        <?php else : ?>
          <div class="muted">C'est ici que les pièces se rangent — la liste est en dessous.</div>
        <?php endif; ?>
      </div>
    <?php endif; ?>

    <?php // ===== LES BARRES DU SOUS-ARBRE (au-dessus d'elles) ===== ?>
    <?php if ($barres_fiche !== null) : ?>
      <div class="card" style="margin-bottom:var(--s4)" id="barres">
        <div class="card-head" style="display:block">
          <h2>Les <?php echo (int) $barres_fiche['total']; ?> barres de <?php echo fpl_e($courant !== null ? $courant['nom'] : $etage_courant['nom']); ?></h2>
          <div class="muted" style="font-size:13px; margin-top:2px">Le bouton étiquette ouvre celle de la barre.</div>
        </div>
        <?php echo fpl_tablebar_haut($barres_fiche, 'barres'); ?>
        <div class="table-wrap">
          <table>
            <thead><tr><th class="num" style="width:80px">N°</th><th>Nom</th><th>Emplacement complet</th><th style="width:150px"></th></tr></thead>
            <tbody>
              <?php foreach ($barres_fiche['lignes'] as $b) : ?>
                <tr>
                  <td class="num muted"><?php echo (int) $b['numero']; ?></td>
                  <td><strong><?php echo fpl_e($b['nom']); ?></strong></td>
                  <td class="muted"><?php echo fpl_e(entrepot_noeud_chemin_libelle((int) $b['id'])); ?></td>
                  <td>
                    <div class="row-actions">
                      <a href="structure-entrepot.php?loc=<?php echo (int) $b['id']; ?>" class="btn btn-outline btn-sm">Ouvrir</a>
                      <a href="etiquette-barre.php?id=<?php echo (int) $b['id']; ?>" class="btn btn-blue btn-sm btn-icon" title="Étiquette">
                        <?php echo fpl_icone('tag', 13); ?>
                      </a>
                    </div>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <?php echo fpl_pager($barres_fiche); ?>
      </div>
    <?php endif; ?>

    <?php // ===== LES PIÈCES RANGÉES DANS CE SOUS-ARBRE ===== ?>
    <?php if ($pieces !== null && $pieces['total'] > 0) : ?>
      <div class="card" id="pieces">
        <div class="card-head" style="display:block">
          <h2>Les pièces rangées dans <?php echo fpl_e($courant !== null ? $courant['nom'] : $etage_courant['nom']); ?></h2>
          <div class="muted" style="font-size:13px; margin-top:2px">Une ligne par pièce, avec sa position exacte.</div>
        </div>
        <?php echo fpl_tablebar_haut($pieces, 'pièces'); ?>
        <div class="table-wrap">
          <table>
            <thead><tr><th>Pièce</th><th>Position exacte</th><th class="num">En stock</th><th style="width:110px"></th></tr></thead>
            <tbody>
              <?php foreach ($pieces['lignes'] as $p) : ?>
                <tr>
                  <td>
                    <div class="cell-title"><?php echo fpl_e($p['nom']); ?></div>
                    <div class="cell-sub"><span class="chip-code"><?php echo e(fpl_code_afficher((string) $p['identifiant_interne'])); ?></span></div>
                  </td>
                  <td class="muted"><?php echo fpl_e(entrepot_noeud_chemin_libelle((int) $p['entrepot_noeud_id'])); ?></td>
                  <td class="num" style="font-weight:700"><?php echo (int) $p['stock']; ?></td>
                  <td><a href="ajuster-stock.php?id=<?php echo (int) $p['id']; ?>" class="btn btn-outline btn-sm">Détails</a></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <?php echo fpl_pager($pieces); ?>
      </div>
    <?php endif; ?>

    </div><!-- .page-produits-admin -->

<style>
  .level-chain { display: flex; align-items: center; gap: 6px; flex-wrap: wrap; }
  .level-chip {
    background: var(--surface); border: 1px solid var(--line); border-radius: 20px;
    padding: 4px 12px; font-size: 12.5px; font-weight: 650; color: var(--slate);
  }
  .level-chip.on { background: var(--navy); border-color: var(--navy); color: #fff; }
  .fpl-fil { display: flex; align-items: center; gap: 7px; font-size: 13.5px; color: var(--slate); flex-wrap: wrap; }
  .fpl-fil a { color: var(--blue-600); }
  .fpl-fil strong { color: var(--navy); }
  .create-bar { display: flex; gap: var(--s3); align-items: flex-end; flex-wrap: wrap; }
  .cb-field { display: flex; flex-direction: column; gap: 4px; }
  .cb-field label { font-size: 12.5px; font-weight: 650; color: var(--slate); }
  .cb-field input {
    padding: 9px 11px; border: 1.5px solid var(--line); border-radius: var(--r-sm);
    font-family: inherit; font-size: 14.5px; width: 100%;
  }
  .row-actions { display: flex; gap: 6px; align-items: center; flex-wrap: wrap; }
  .row-actions form { margin: 0; }
</style>

    <?php include '../includes/footer.php'; ?>
