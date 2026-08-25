# Les polices de l'étiquette

Ce dossier porte **une seule police**, et elle n'est pas versionnée.

## `script.ttf` — l'écriture manuscrite

Le dessin d'étiquette du 14/08/2026 écrit deux lignes à la main :
« The Solution » sous le logo, et le slogan bilingue
« Conduire avec assurance / ndakh jombtukay you worr ».

Aucune police livrée avec dompdf n'est cursive : les trois familles
DejaVu sont des linéales et des romaines. Il faut donc en déposer une ici,
sous le nom **`script.ttf`**.

**Sur cette machine**, c'est *Freestyle Script* (`C:\Windows\Fonts\FREESCPT.TTF`)
— c'est celle dont le trait ressemble le plus à la maquette.

## `condense.ttf` et `condense-gras.ttf` — l'étroite

Le titre de la maquette (« XOTTU SETTU ») est écrit dans une linéale
**très condensée** : onze capitales tiennent sur 30 % de la largeur. Avec
une Helvetica ou une DejaVu, qui sont de chasse normale, le même texte à
la même hauteur déborderait de moitié — il faudrait le rapetisser, et le
titre perdrait sa force.

**Sur cette machine** : *Arial Narrow* (`ARIALN.TTF`) et *Arial Narrow
Bold* (`ARIALNB.TTF`).

Remplacement libre le jour venu : **Oswald**, **Archivo Narrow** ou
**Barlow Condensed** (licence OFL).

## Pourquoi elles ne sont pas dans le dépôt

Les polices de Windows appartiennent à Microsoft et à Monotype : les
copier dans un dépôt, c'est les redistribuer. Elles restent donc ici, sur
le poste qui les possède déjà.

**Le jour où l'étiquette se générera sur un serveur Linux**, il faudra
déposer à la place des polices libres — *Great Vibes* / *Dancing Script*
pour la manuscrite, *Oswald* / *Archivo Narrow* pour l'étroite (licence
OFL, redistribuables) — sous les mêmes noms de fichiers. Rien d'autre à
changer : le code cherche ces trois fichiers, pas ces polices-là.

## Et si le fichier manque ?

L'étiquette sort quand même. Sans la manuscrite, les deux lignes du
slogan s'écrivent en DejaVu Serif italique ; sans l'étroite, le titre
s'écrit en Helvetica grasse et se rapetisse tout seul pour tenir dans sa
laisse. Le dessin est moins juste, il n'est jamais cassé.
