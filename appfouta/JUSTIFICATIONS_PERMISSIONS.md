# Justifications des permissions — FOUTA POIDS LOURDS

Application WebView + pont natif. Usage réel documenté dans `lib/main.dart`, `lib/services/permission_rationale_service.dart` et `ios/Runner/Info.plist`.

## Apple App Store (chaînes Info.plist)

Voir `ios/Runner/Info.plist` — chaque clé inclut finalité + exemple (exigence 5.1.1).

## Google Play Console

### CAMERA

```
FOUTA POIDS LOURDS utilise la caméra lorsque l'utilisateur appuie sur « Prendre une photo » pour photographier des pièces détachées et produits pour camions (fiches catalogue, stock) ou la photo d'un employé (profil RH). Exemple : photo d'un amortisseur châssis ou photo d'identité d'un collaborateur.
```

### READ_MEDIA_IMAGES / READ_EXTERNAL_STORAGE (galerie / stockage)

```
FOUTA POIDS LOURDS accède à la galerie ou au stockage lorsque l'utilisateur choisit d'importer ou téléverser un fichier : photo d'employé, contrat de travail, CV ou document administratif. Exemple : joindre le CV d'un nouvel employé depuis le téléphone.
```

### ACCESS_FINE_LOCATION / ACCESS_COARSE_LOCATION

```
FOUTA POIDS LOURDS utilise la localisation lorsque l'utilisateur l'active pour organiser et suivre les livraisons de pièces, confirmer une adresse client ou afficher un point sur la carte. Exemple : lieu de livraison d'une commande. Pas de suivi en arrière-plan.
```

### POST_NOTIFICATIONS (Android 13+)

```
Notifications liées au suivi des commandes, des livraisons et du compte (ex. commande expédiée, statut mis à jour). L'utilisateur peut refuser dans les paramètres système.
```

## Permissions non utilisées

- **Microphone** : non demandé dans la WebView (non déclaré pour un usage général).
