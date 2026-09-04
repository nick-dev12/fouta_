#!/usr/bin/env bash
# LA VITRINE CLIENT /p/{code} — la page du QR des étiquettes de pièce (04/09/2026).
#
# À jouer contre le serveur de dev :  php -S 127.0.0.1:8080 routeur-dev.php
# (le .htaccess ne vit que sous Apache — leçon connue du dépôt).
#
# La règle la plus importante est la DERNIÈRE : pas un seul mot interne
# (stock, emplacement, fournisseur, grossiste…) ne doit fuir sur la page.

set -u
BASE="${1:-http://127.0.0.1:8080}"
PHP="${PHP_BIN:-php}"
ICI="$(cd "$(dirname "$0")" && pwd)"
RACINE="$(dirname "$ICI")"
OK=0; KO=0

dit() { printf '%s\n' "$*"; }
verifie() { # verifie "libellé" commande…
    local lib="$1"; shift
    if "$@" >/dev/null 2>&1; then OK=$((OK+1)); dit "  OK  $lib";
    else KO=$((KO+1)); dit "  KO  $lib"; fi
}

# --- la pièce de démonstration : id 2, la seule avec prix ET promo -----------
EAN=$("$PHP" -r 'require $argv[1]."/includes/produit_vitrine.php"; echo fpl_vitrine_ean13_pour_produit(["id"=>2,"identifiant_interne"=>"FPL001004648"]);' "$RACINE")
[ -n "$EAN" ] || { dit "impossible de composer l'EAN de démonstration"; exit 1; }
dit "pièce témoin : FPL001004648 → EAN $EAN"

PAGE=$(curl -s "$BASE/p/$EAN")

dit "— la page vit et dit vrai —"
verifie "/p/{ean13} répond 200" test "$(curl -s -o /dev/null -w '%{http_code}' "$BASE/p/$EAN")" = 200
verifie "le nom de la pièce s'affiche" grep -qi "filotre a air" <<<"$PAGE"
verifie "la référence aérée s'affiche (FPL 001 004 648)" grep -q "FPL 001 004 648" <<<"$PAGE"
verifie "le numéro du code-barres s'affiche ($EAN)" grep -q "$EAN" <<<"$PAGE"
verifie "l'identité maison est là" grep -q "FOUTA POIDS LOURDS" <<<"$PAGE"
verifie "le slogan manuscrit est posé" grep -q "slogan-manuscrit.png" <<<"$PAGE"
verifie "le prix promo s'affiche (1 500 FCFA)" grep -q "1 500 FCFA" <<<"$PAGE"
verifie "l'ancien prix barré s'affiche (1 999 FCFA)" grep -q "1 999 FCFA" <<<"$PAGE"
verifie "WhatsApp est branché (wa.me)" grep -q "wa.me/221773938484" <<<"$PAGE"
verifie "le canonique pointe /p/{ean13}" grep -q "canonical\" href=\".*/p/$EAN" <<<"$PAGE"

dit "— toutes les portes mènent à la même pièce —"
verifie "/p/FPL001004648 (identifiant) répond 200" test "$(curl -s -o /dev/null -w '%{http_code}' "$BASE/p/FPL001004648")" = 200
verifie "/p/fpl001004648 (minuscules) répond 200" test "$(curl -s -o /dev/null -w '%{http_code}' "$BASE/p/fpl001004648")" = 200
verifie "/p/FPL%20001%20004%20648 (espaces) répond 200" test "$(curl -s -o /dev/null -w '%{http_code}' "$BASE/p/FPL%20001%20004%20648")" = 200
verifie "/p/001004648 (9 chiffres nus) répond 200" test "$(curl -s -o /dev/null -w '%{http_code}' "$BASE/p/001004648")" = 200

dit "— et refusent les faux codes —"
MAUVAISE_CLE=$(( ( ${EAN:12:1} + 1 ) % 10 )); FAUX="${EAN:0:12}${MAUVAISE_CLE}"
verifie "EAN à mauvaise clé → 404" test "$(curl -s -o /dev/null -w '%{http_code}' "$BASE/p/$FAUX")" = 404
verifie "référence inconnue → 404" test "$(curl -s -o /dev/null -w '%{http_code}' "$BASE/p/FPL999999999")" = 404
verifie "le 404 reste accueillant" curl -s "$BASE/p/FPL999999999" -o /dev/null -w '' # forme
P404=$(curl -s "$BASE/p/FPL999999999")
verifie "…avec le mot d'accueil" grep -q "aucune pièce de notre catalogue" <<<"$P404"
verifie "…et les coordonnées de la maison" grep -q "foutapoidslourds.com" <<<"$P404"

dit "— RIEN d'interne ne fuit (la règle d'or) —"
for MOT in "stock" "mplacement" "fournisseur" "grossiste" "revient" "entreprise" "seuil" "rayon" "chiffre"; do
    verifie "le mot « $MOT » est absent de la page" bash -c '! grep -qi "$1" <<<"$2"' _ "$MOT" "$PAGE"
done

dit "— les anciens QR imprimés survivent —"
R=$(curl -s -o /dev/null -w '%{http_code} %{redirect_url}' "$BASE/stock-info.php?id=2")
verifie "stock-info.php?id=2 → 301 vers /p/$EAN" grep -q "301 .*/p/$EAN" <<<"$R"
verifie "stock-info.php sans id → retour à l'accueil" bash -c 'curl -s -o /dev/null -w "%{redirect_url}" "$1/stock-info.php" | grep -qE "/$"' _ "$BASE"
verifie "l'ancienne page (origine) exige une session admin" bash -c 'curl -s -o /dev/null -w "%{redirect_url}" "$1/stock-info-fouta-origine.php?id=2" | grep -q "stock-info.php"' _ "$BASE"

dit "— le QR et le code-barres portent le même contenu —"
QRT=$("$PHP" -r 'require $argv[1]."/conn/conn.php"; require $argv[1]."/includes/etiquette_fpl70.php";
$st=$db->prepare("SELECT * FROM produits WHERE id=2"); $st->execute();
$d=etiquette70_donnees_pour_produit($st->fetch(PDO::FETCH_ASSOC));
echo $d["qr_texte"], "|", $d["ean12"];' "$RACINE")
verifie "le QR encode …/p/{ean13}" grep -q "/p/$EAN|" <<<"$QRT"
verifie "l'EAN de l'étiquette = les 12 premiers chiffres du QR" test "${QRT##*|}" = "${EAN:0:12}"
SCANS=$("$PHP" -r 'require $argv[1]."/includes/produit_emplacement_entrepot.php";
echo produit_emplacement_extraire_fpl_du_scan("https://e.foutapoidslourds.com/p/".$argv[2]), "|",
     produit_emplacement_extraire_fpl_du_scan("http://192.168.1.196/p/FPL001004648"), "|",
     produit_emplacement_extraire_fpl_du_scan($argv[2]);' "$RACINE" "$EAN")
verifie "douchette sur le QR (URL ean13) → FPL001004648" test "$(cut -d'|' -f1 <<<"$SCANS")" = "FPL001004648"
verifie "douchette sur un QR /p/FPL… → FPL001004648" test "$(cut -d'|' -f2 <<<"$SCANS")" = "FPL001004648"
verifie "douchette sur le code-barres nu → FPL001004648" test "$(cut -d'|' -f3 <<<"$SCANS")" = "FPL001004648"

dit "— fidélisation —"
VC=$(curl -s "$BASE/p/$EAN?vcard=1")
verifie "la vCard se télécharge (BEGIN:VCARD)" grep -q "BEGIN:VCARD" <<<"$VC"
verifie "…au nom de la maison" grep -q "FN:FOUTA POIDS LOURDS" <<<"$VC"

dit "— non-régression du site —"
verifie "l'accueil répond" test "$(curl -s -o /dev/null -w '%{http_code}' "$BASE/")" = 200
verifie "la fiche boutique répond" test "$(curl -s -o /dev/null -w '%{http_code}' "$BASE/produit.php?id=2")" = 200
verifie "l'admin redirige vers la connexion" test "$(curl -s -o /dev/null -w '%{http_code}' "$BASE/admin/")" = 302

dit ""
dit "BILAN : $OK OK · $KO KO"
[ "$KO" = 0 ]
