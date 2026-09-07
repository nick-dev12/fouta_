<?php
/**
 * L'ÉTIQUETTE DE BARRE PASSE AU FORMAT 150 × 60 mm (07/09/2026).
 *
 * Consigne de la direction : « la taille doit être 150 × 60 au lieu de
 * 90 × 40 ». Deux réglages font le format PAR DÉFAUT d'une étiquette de barre :
 *  1. la table des formats (etiquette_formats, type 'barre') — l'écran et le
 *     PDF prennent le format dont les mm sont ceux des réglages d'entrepôt,
 *     sinon le premier de la liste ;
 *  2. les réglages d'entrepôt (entrepot_etiquette_parametres, id 1).
 *
 * La migration : (a) crée le format « 150 × 60 mm » s'il manque (uuid posé
 * pour la sync), le met en tête (ordre 0) et recule les autres formats de
 * barre ; (b) règle les dimensions d'entrepôt sur 150 × 60 (QR et texte
 * conservés, bornés par le modèle) ; (c) PROUVE que le format par défaut est
 * désormais 150 × 60. L'ancien 90 × 40 reste disponible dans la liste des
 * formats (rien n'est supprimé). Idempotente : rejouable sans effet.
 *
 * Usage : php migrations/run_format_barre_150x60.php
 */
require_once dirname(__DIR__) . '/conn/conn.php';
require_once dirname(__DIR__) . '/models/model_entrepot_etiquette_parametres.php';
require_once dirname(__DIR__) . '/models/model_produit_etiquette_parametres.php';
require_once dirname(__DIR__) . '/models/model_etiquettes_fpl.php';

$L = 150.0;
$H = 60.0;

/** @var PDO $db */
if (!fpl_etiquette_formats_table_ok()) {
    fwrite(STDERR, "La table etiquette_formats manque : lancer d'abord migrations/run_etiquette_formats.php\n");
    exit(1);
}

try {
    $db->beginTransaction();

    // (a) le format de barre 150 × 60
    $st = $db->prepare("SELECT id, ordre FROM etiquette_formats
                        WHERE type = 'barre' AND sync_deleted_at IS NULL
                          AND ABS(largeur_mm - :l) < 0.01 AND ABS(hauteur_mm - :h) < 0.01
                        ORDER BY id LIMIT 1");
    $st->execute([':l' => $L, ':h' => $H]);
    $existant = $st->fetch(PDO::FETCH_ASSOC);

    if ($existant) {
        $id150 = (int) $existant['id'];
        echo "Format 150 × 60 mm déjà présent (id $id150).\n";
    } else {
        $db->prepare("INSERT INTO etiquette_formats
            (nom, type, largeur_mm, hauteur_mm, est_systeme, ordre, date_creation, date_modification, sync_uuid)
            VALUES ('150 × 60 mm', 'barre', :l, :h, 1, 0, NOW(), NOW(), UUID())")
           ->execute([':l' => $L, ':h' => $H]);
        $id150 = (int) $db->lastInsertId();
        echo "Format 150 × 60 mm créé (id $id150).\n";
    }

    // en tête de liste : 150 × 60 à l'ordre 0, les autres barres derrière (ordre conservé entre elles)
    $autres = $db->prepare("SELECT id FROM etiquette_formats WHERE type = 'barre' AND sync_deleted_at IS NULL AND id <> :id ORDER BY ordre, id");
    $autres->execute([':id' => $id150]);
    $maj = $db->prepare("UPDATE etiquette_formats SET ordre = :o, date_modification = NOW() WHERE id = :id AND (ordre IS NULL OR ordre <> :o2)");
    $maj->execute([':o' => 0, ':id' => $id150, ':o2' => 0]);
    $o = 1;
    foreach ($autres->fetchAll(PDO::FETCH_COLUMN) as $aid) {
        $maj->execute([':o' => $o, ':id' => (int) $aid, ':o2' => $o]);
        $o++;
    }

    // (b) les réglages d'entrepôt : 150 × 60, QR et texte tels quels (bornés par le modèle)
    $dims = entrepot_etiquette_dims();
    if (abs((float) $dims['largeur_mm'] - $L) < 0.01 && abs((float) $dims['hauteur_mm'] - $H) < 0.01) {
        echo "Réglages d'entrepôt déjà à 150 × 60 mm.\n";
    } else {
        $res = entrepot_etiquette_parametres_save([
            'largeur_mm' => $L, 'hauteur_mm' => $H,
            'qr_mm' => $dims['qr_mm'], 'texte_mm' => $dims['texte_mm'],
        ]);
        if (empty($res['success'])) {
            throw new RuntimeException('Réglages d\'entrepôt : ' . ($res['message'] ?? '?'));
        }
        echo "Réglages d'entrepôt : " . $dims['largeur_mm'] . " × " . $dims['hauteur_mm'] . " → 150 × 60 mm.\n";
    }

    // (c) la preuve : le format par défaut est 150 × 60
    $defaut = etiquette_format_barre_defaut();
    if ($defaut === false || abs((float) $defaut['largeur_mm'] - $L) > 0.01 || abs((float) $defaut['hauteur_mm'] - $H) > 0.01) {
        throw new RuntimeException('Le format de barre par défaut n\'est pas 150 × 60 après migration : '
            . ($defaut === false ? 'aucun' : $defaut['largeur_mm'] . ' × ' . $defaut['hauteur_mm']));
    }
    $db->commit();
    echo "Preuve : format de barre par défaut = " . $defaut['nom'] . " (id " . $defaut['id'] . ").\n";
    echo "Migration format barre 150 × 60 : OK\n";
} catch (Throwable $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    fwrite(STDERR, 'ÉCHEC (rien n\'est modifié) : ' . $e->getMessage() . "\n");
    exit(1);
}
