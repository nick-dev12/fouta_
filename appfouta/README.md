# FOUTA POIDS LOURDS — Application Flutter

Application mobile **Flutter** qui charge le site **FOUTA POIDS LOURDS** (`https://www.e.foutapoidslourds.com/`) dans une WebView, avec accès aux fonctionnalités natives (caméra, géolocalisation, notifications Firebase, etc.).

---

## Sommaire

1. [Prérequis](#prérequis)
2. [Installation et développement](#installation-et-développement)
3. [Structure du projet](#structure-du-projet)
4. [Configuration importante](#configuration-importante)
5. [Firebase Android (configuration pas à pas)](#firebase-android-configuration-pas-à-pas)
6. [Tests](#tests)
7. [Créer un keystore (Android)](#créer-un-keystore-android)
8. [Build release Android & Google Play](#build-release-android--google-play)
9. [Publication sur l’App Store (iOS)](#publication-sur-lapp-store-ios)
10. [Versioning (`pubspec.yaml`)](#versioning-pubspecyaml)
11. [Ressources](#ressources)

---

## Prérequis

| Outil | Usage |
|--------|--------|
| [Flutter SDK](https://docs.flutter.dev/get-started/install) | Aligné sur `environment.sdk` dans `pubspec.yaml` |
| Android Studio / SDK Android | Builds Android, émulateur |
| Xcode (macOS uniquement) | Builds et publication iOS |
| Compte [Google Play Console](https://play.google.com/console) | Publication Android |
| Compte [Apple Developer](https://developer.apple.com) | Publication iOS |

Vérifier l’environnement :

```bash
flutter doctor -v
```

---

## Installation et développement

À la racine du dossier **`appfouta`** :

```bash
cd appfouta
flutter pub get
```

Lancer l’app en debug (appareil ou émulateur connecté, ou navigateur pour `flutter run -d chrome` si activé) :

```bash
flutter run
```

Build debug sans installation :

```bash
flutter build apk --debug
```

Logs en continu :

```bash
flutter logs
```

Nettoyer les artefacts de build :

```bash
flutter clean
flutter pub get
```

---

## Structure du projet

```
appfouta/
├── lib/
│   ├── main.dart              # Point d’entrée, WebView, loader, URL marketplace
│   └── services/
│       └── fcm_service.dart   # Firebase Cloud Messaging / script JS
├── android/                   # Projet Gradle, signature release, Firebase
├── ios/                       # Projet Xcode
├── assets/images/             # Icônes, splash (ex. app_icon.png)
├── pubspec.yaml               # Dépendances, version, assets
├── test/                      # Tests unitaires / widget
└── README.md                  # Ce fichier
```

Documentation complémentaire dans le dépôt : fichiers `*_EXPLICATION.md`, `CHECKLIST_PRODUCTION_GOOGLE_PLAY.md`, etc.

---

## Configuration importante

### URL de la marketplace

La constante **`kMarketplaceBaseUrl`** dans `lib/main.dart` définit le site chargé dans la WebView (production : `https://www.e.foutapoidslourds.com/`).

Les URLs sauvegardées localement ne sont conservées que pour **`e.foutapoidslourds.com`** / **`www.e.foutapoidslourds.com`**.

### Firebase & Google Sign-In

- Proj. Firebase **`foouta-d1782`**, bundle / package **`com.fouta.app`**.
- Dans `lib/config/firebase_auth_config.dart`, renseignez l’**ID client OAuth Web** (console Firebase / Google Cloud) pour que la connexion Google native fonctionne.
- La clé **VAPID** pour le Web Push est stockée sous `kFirebaseMessagingVapidKey` (réutilisable côté site si besoin).

### Signature Android

- Fichier **`android/key.properties`** : mots de passe et chemin du keystore (voir section [Créer un keystore](#créer-un-keystore-android)).
- **Ne jamais committer** `key.properties` ni les fichiers `.jks` / `.keystore`.

---

## Firebase Android (configuration pas à pas)

**Objectif** : lier l’app **`com.fouta.app`** au projet Firebase **foouta-d1782**, avec FCM et Analytics côté Android natif.

### 1. Console Firebase

1. Ouvrez [Firebase Console](https://console.firebase.google.com/) → projet **foouta-d1782**.
2. Application Android **`com.fouta.app`** déjà configurée avec **`google-services.json`** dans **`appfouta/android/app/`**.
3. **Android package name** : **`com.fouta.app`** (identique à `applicationId` dans `android/app/build.gradle.kts`).

### 2. Gradle — niveau projet (`android/settings.gradle.kts`)

Le plug-in Google Services est déclaré **avec version** et **`apply false`** (il est appliqué uniquement sur le module `app`) :

```kotlin
plugins {
    // ...
    id("com.google.gms.google-services") version "4.4.4" apply false
}
```

### 3. Gradle — module application (`android/app/build.gradle.kts`)

- Dans le bloc **`plugins`** : `id("com.google.gms.google-services")` (sans version : elle vient du projet).
- Dans **`dependencies`** : Bill of Materials Firebase + produits utilisés, par exemple :

```kotlin
implementation(platform("com.google.firebase:firebase-bom:34.13.0"))
implementation("com.google.firebase:firebase-analytics")
implementation("com.google.firebase:firebase-messaging")
```

Les versions des bibliothèques Firebase **ne doivent pas** être fixées une par une lorsque vous utilisez la BoM (sauf cas exceptionnel documenté par Google).

### 4. Côté Flutter (`pubspec.yaml`)

Les paquets **`firebase_core`** et **`firebase_messaging`** restent nécessaires pour le code Dart. Après toute modification Gradle, exécutez :

```bash
cd appfouta
flutter pub get
flutter clean   # si le build échoue après un gros changement Firebase
flutter pub get
```

### 5. Vérification

```bash
flutter build apk --debug
```

Si FCM ne reçoit pas les messages : vérifiez les empreintes SHA pour **`com.fouta.app`**, que **`google-services.json`** correspond bien au projet **foouta-d1782**, et la configuration Cloud Messaging dans la console.

### Sécurité

- **`google-services.json`** contient des clés API restreintes par le package Android, mais il ne doit pas être exposé publiquement (évitez les dépôts publics).
- Ne partagez pas ce fichier dans des captures d’écran ou discussions publiques ; en cas de fuite, vous pouvez régénérer / restreindre les clés dans [Google Cloud Console](https://console.cloud.google.com/) liée au projet Firebase.

## Tests

### Tests automatiques (widget / unitaires)

```bash
cd appfouta
flutter test
```

Un seul fichier :

```bash
flutter test test/widget_test.dart
```

### Analyse statique

```bash
flutter analyze
```

### Tests sur appareil réel

1. Activer les **options développeur** et le **débogage USB** sur le téléphone.
2. Brancher en USB ; accepter la clé RSA si demandé.
3. `flutter devices` puis `flutter run`.

### Tests manuels avant publication (checklist rapide)

- Premier lancement : splash / loader / chargement du site.
- Connexion compte sur le site dans la WebView.
- Permissions : caméra, localisation, notifications (Android 13+).
- Réception d’une notification push si Firebase est configuré.
- Mise en arrière-plan et retour sur l’app (restauration d’URL si implémentée).

---

## Créer un keystore (Android)

Le keystore sert à **signer** l’application en mode **release**. Sans lui (ou sans équivalent Play App Signing bien configuré), vous ne pouvez pas publier des mises à jour cohérentes avec votre première version publiée.

### Une application peut utiliser plusieurs keystores ?

Un même fichier `.jks` peut contenir **plusieurs clés** (alias). Pour **plusieurs apps différentes**, vous pouvez utiliser le même keystore ou des keystores séparés ; en pratique on utilise souvent **un keystore par produit** pour limiter les risques en cas de perte ou de fuite.

Pour **une même app** (même `applicationId`), vous devez **garder la même logique de signature** attendue par Google Play pour toutes les mises à jour.

### Commande `keytool` (JDK livré avec Android Studio)

Sous **Windows** (PowerShell ou CMD), depuis le dossier où vous voulez créer le fichier (ex. `appfouta/android`) :

```bash
keytool -genkey -v -keystore upload-keystore.jks -storetype JKS -keyalg RSA -keysize 2048 -validity 10000 -alias upload
```

- **`upload-keystore.jks`** : nom du fichier ; vous pouvez le renommer (évitez les espaces).
- **`upload`** : alias de la clé ; notez-le pour `key.properties`.
- **`validity 10000`** : environ 27 ans ; ajustez selon votre politique.

Réponses aux questions interactives : nom, organisation, pays (ex. `SN`), puis **deux mots de passe** (souvent identiques pour store et key pour simplifier).

Si `keytool` n’est pas reconnu : utilisez le chemin complet, par exemple :

```text
"C:\Program Files\Android\Android Studio\jbr\bin\keytool.exe" -genkey ...
```

### Placer les fichiers

Exemple recommandé :

1. Copier **`upload-keystore.jks`** dans le dossier **`appfouta/android/`** (à côté de `key.properties`).
2. Créer **`appfouta/android/key.properties`** (ne pas committer) :

```properties
storePassword=<mot_de_passe_du_keystore>
keyPassword=<mot_de_passe_de_la_clé>
keyAlias=upload
storeFile=../upload-keystore.jks
```

Le chemin **`storeFile`** est relatif au module **`android/app/`** (comme utilisé dans `android/app/build.gradle.kts`), d’où le **`../`** pour remonter vers `android/upload-keystore.jks`.

### Sauvegarde

Conservez une copie **chiffrée / hors ligne** du `.jks` et les mots de passe dans un gestionnaire de secrets. **Sans keystore + mots de passe**, vous ne pourrez plus signer les mises à jour sous le même identifiant d’app sans procédure de réinitialisation limitée dans Play Console.

---

## Build release Android & Google Play

### 1. Mettre à jour la version

Dans **`pubspec.yaml`** :

```yaml
version: 1.0.3+6
```

Format : **`versionName+versionCode`**. Sur chaque upload Play Store, **`versionCode`** (nombre après `+`) doit être **strictement supérieur** au précédent.

Alternative sans modifier le fichier :

```bash
flutter build appbundle --release --build-name=1.0.3 --build-number=6
```

### 2. Vérifier la signature

Assurez-vous que **`android/key.properties`** existe et pointe vers un keystore valide. Sinon la release peut être signée en **debug** et **refusée** par Google Play.

### 3. Générer le bundle (.aab)

```bash
cd appfouta
flutter pub get
flutter build appbundle --release
```

Fichier attendu :

```text
build/app/outputs/bundle/release/app-release.aab
```

Selon la configuration Gradle du projet, un script peut aussi renommer le fichier dans le même dossier ; vérifiez après le build.

### 4. Téléversement Play Console

1. Créez une application ou ouvrez une version (**interne**, **fermée**, **ouverte** ou **production**).
2. Importez le **`.aab`**.
3. Remplissez la **fiche Play**, **declarations de données**, **politique de confidentialité** si requis.
4. Activez ou confirmez **Play App Signing** (recommandé).

### APK (tests hors Play uniquement)

```bash
flutter build apk --release
```

Sortie typique : `build/app/outputs/flutter-apk/app-release.apk`.

---

## Publication sur l’App Store (iOS)

Résumé des étapes (sur **macOS**) :

1. Ouvrir **`ios/Runner.xcworkspace`** dans Xcode.
2. **Signing & Capabilities** : équipe Apple Developer, bundle identifier unique.
3. Version / build dans Xcode alignés avec la stratégie de release.
4. Menu **Product → Archive**, puis distribute via **App Store Connect**.
5. Compléter métadonnées, captures d’écran et conformité dans [App Store Connect](https://appstoreconnect.apple.com).

Commande Flutter :

```bash
flutter build ipa --release
```

puis finalisation dans Xcode / Transporter selon votre flux.

---

## Versioning (`pubspec.yaml`)

| Champ Android | Source Flutter |
|----------------|----------------|
| `versionName` | Partie avant `+` (ex. `1.0.3`) |
| `versionCode` | Partie après `+` (ex. `6`) |

Règle Play Store : incrémenter **`versionCode`** à chaque nouvel artefact uploadé.

---

## Ressources

- [Flutter — Déploiement](https://docs.flutter.dev/deployment)
- [Flutter — Signer l’app Android](https://docs.flutter.dev/deployment/android#sign-the-app)
- [Google Play Console](https://play.google.com/console)
- [Documentation flutter_inappwebview](https://inappwebview.dev/)

---

## Licence et usage

Projet privé — usage conforme à la politique de l’éditeur FOUTA POIDS LOURDS / propriétaire du dépôt.
