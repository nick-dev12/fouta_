# Soumission App Store — FOUTA POIDS LOURDS (iOS)

## Rejet Guideline 5.1.1(ii) — chaînes d'objectif (Purpose Strings)

Apple exige que chaque `NS*UsageDescription` dans `Info.plist` explique **comment** et **pourquoi** l'app utilise la ressource, avec un **exemple concret**. Les formulations du type « l'application a besoin d'accéder à… » sont refusées.

Les textes ont été mis à jour dans `ios/Runner/Info.plist` pour **FOUTA POIDS LOURDS**.

Référence : [Human Interface Guidelines — Privacy](https://developer.apple.com/design/human-interface-guidelines/privacy#Requesting-permission)

## Identifiants

| Plateforme | Identifiant |
|------------|-------------|
| iOS (App Store Connect) | `com.fouta.app` |
| Android | `com.fouta.app` |
| Firebase `GoogleService-Info.plist` | `BUNDLE_ID` = `com.fouta.app` |

## Build sur Mac (Xcode)

### Prérequis

- macOS avec Xcode 15+
- Flutter SDK stable (`flutter doctor`)
- Compte Apple Developer + certificats de distribution
- Fichier `ios/Runner/GoogleService-Info.plist` présent (projet Firebase **foouta-d1782**)

### Étapes

```bash
cd appfouta
flutter pub get
cd ios
pod install
cd ..
flutter build ipa --release
```

Ou ouvrir **`ios/Runner.xcworkspace`** dans Xcode :

1. Sélectionner la cible **Runner** → **Signing & Capabilities** : équipe + bundle **`com.fouta.app`**
2. Vérifier que **GoogleService-Info.plist** apparaît dans le groupe Runner (coche « Target Membership » Runner)
3. **Product → Archive** → distribuer vers App Store Connect

### Firebase (notifications)

- `firebase_core` et `firebase_messaging` sont initialisés dans `lib/main.dart`
- Demande de permission : `FCMService.requestNotificationPermission()` (dialogue iOS / Android 13+)
- `Info.plist` : `UIBackgroundModes` → `remote-notification`
- `Runner.entitlements` : `aps-environment` (passer à **`production`** avant l’archive App Store si besoin), domaines associés **`e.foutapoidslourds.com`** / **`www.e.foutapoidslourds.com`**
- Dans Xcode : cible **Runner** → **Signing & Capabilities** → ajouter **Push Notifications**
- Console Firebase : clé **APNs** (fichier .p8) uploadée pour l’app iOS **`com.fouta.app`**
- `FirebaseAppDelegateProxyEnabled` = `false` dans `Info.plist` (gestion via plugins Flutter)

> Sur iOS, il n’existe pas de clé `NS*UsageDescription` pour les notifications push : le système affiche sa propre boîte de dialogue lors de `requestPermission()`.

### App Store Connect — confidentialité

Déclarer notamment :

- Données de localisation (coarse/precise) — liées à la livraison, sur action utilisateur
- Photos — contenu fourni par l'utilisateur
- Identifiants (jeton push FCM/APNs)

URL politique de confidentialité (site en ligne) : `https://www.e.foutapoidslourds.com/politique-confidentialite.php`  
URL CGU : `https://www.e.foutapoidslourds.com/conditions-utilisation.php`

