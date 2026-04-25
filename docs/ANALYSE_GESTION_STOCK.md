# Analyse : Approche Stock Articles → Produits

## Votre approche implémentée

Vous avez demandé une séparation entre :
1. **Articles en stock** (table `stock_articles`) : inventaire physique (nom, image, quantité, catégorie)
2. **Produits** (table `produits`) : catalogue e-commerce (prix, description, variantes, etc.)
3. **Lien** : un produit peut être lié à un article en stock via `stock_article_id`

### Flux implémenté

- **Admin Stock** : ajout d’articles via un modal plein écran (nom, image, quantité, catégorie)
- **Ajout de produit** : étape 1 = recherche + sélection d’un article en stock → étape 2 = formulaire classique (description, prix, variantes, etc.) pré-rempli
- **Modification de produit** : sélecteur pour lier/délier un article en stock
- **Affichage du stock** : si le produit est lié à un article, le stock affiché provient de l’article en temps réel

---

## Avantages de cette approche

| Avantage | Détail |
|----------|--------|
| **Séparation claire** | Inventaire physique (stock) vs catalogue commercial (produits) |
| **Réutilisation** | Un même article peut servir de base à plusieurs produits (ex. même miel vendu en différents formats) |
| **Traçabilité** | On sait quels produits sont rattachés à quel stock physique |
| **Cohérence** | Le stock affiché pour un produit lié reflète toujours l’article en stock |
| **Workflow logique** | Réception de stock → création d’articles → publication en produits |

---

## Inconvénients / limites

| Limite | Détail |
|--------|--------|
| **Complexité** | Deux tables à gérer, plus de requêtes (JOIN) |
| **Redondance** | Nom, image, catégorie dupliqués entre article et produit |
| **Synchronisation** | Si on modifie l’article (nom, image), le produit garde ses propres valeurs tant qu’on ne les met pas à jour |
| **Décrémentation** | Actuellement, le stock n’est pas décrémenté automatiquement à la validation d’une commande (ni sur `produits`, ni sur `stock_articles`) |

---

## Alternative : approche simplifiée (stock sur produit uniquement)

Pour une petite structure, on peut garder uniquement le stock sur la table `produits` :

- Pas de table `stock_articles`
- Stock géré directement sur chaque produit
- Moins de complexité, moins de requêtes

**Intérêt de votre approche par rapport à cette alternative :**
- Vous distinguez bien l’inventaire physique du catalogue
- Vous pouvez gérer des entrées/sorties de stock sans toucher aux fiches produits
- Vous préparez une évolution vers des mouvements de stock (entrées, sorties, inventaires)

---

## Recommandation

Votre approche est adaptée si :

- Vous recevez du stock physique avant de créer les fiches produits
- Vous voulez une vraie gestion d’inventaire (entrées, sorties, inventaires)
- Vous envisagez plusieurs produits à partir d’un même article (ex. même miel en 250 g et 500 g)

Elle est moins pertinente si :

- Vous vendez surtout des produits uniques, sans notion d’inventaire central
- Vous préférez une solution très simple avec peu de tables

---

## Améliorations possibles

1. **Décrémentation automatique du stock à la commande**  
   Lors de la validation d’une commande, décrémenter :
   - `stock_articles.quantite` si le produit est lié à un article
   - `produits.stock` sinon

2. **Synchronisation optionnelle**  
   Bouton « Synchroniser avec l’article » sur la fiche produit pour recopier nom, image, catégorie et stock depuis l’article lié.

3. **Mouvements de stock**  
   Table `stock_mouvements` (entrée, sortie, inventaire) pour tracer les variations de quantité.

4. **Un article → plusieurs produits**  
   Actuellement, un article ne peut être lié qu’à un seul produit. On pourrait autoriser plusieurs produits par article (ex. même miel en plusieurs conditionnements).
