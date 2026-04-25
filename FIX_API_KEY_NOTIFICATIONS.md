# Corriger l'erreur "API key not valid" pour les notifications

## Méthode 1 : Désactiver les restrictions de la clé API (recommandé pour le développement)

### Étape 1 : Trouver votre clé API dans Google Cloud

1. Allez sur : **https://console.cloud.google.com/apis/credentials?project=sugar-paper**
2. Dans la section **"Clés API"**, cherchez la clé dont la valeur commence par `AIzaSyAOGTcYf7i...` (ou cliquez sur chaque clé pour voir sa valeur)
3. Cliquez sur l'icône **crayon** (modifier) à droite de cette clé

### Étape 2 : Supprimer les restrictions

1. **Restrictions relatives aux applications** : Laissez **"Aucun"** (déjà correct)
2. **Restrictions relatives aux API** : Sélectionnez **"Ne pas restreindre la clé"** (Don't restrict key)
3. Cliquez sur **"Enregistrer"**
4. Attendez **2 à 5 minutes** que les changements se propagent

### Étape 3 : Activer Firebase Installations API

1. Allez sur : **https://console.cloud.google.com/apis/library?project=sugar-paper**
2. Recherchez **"Firebase Installations API"**
3. Cliquez dessus, puis sur **"Activer"** (si pas déjà activé)
4. Vérifiez aussi que **"Firebase Cloud Messaging API"** est activée

---

## Méthode 2 : Récupérer une nouvelle configuration depuis Firebase Console

Si la méthode 1 ne fonctionne pas, récupérez une configuration fraîche :

1. Allez sur : **https://console.firebase.google.com/project/sugar-paper/settings/general**
2. Descendez jusqu'à la section **"Vos applications"**
3. Si vous n'avez pas d'app **Web** : cliquez sur **"</>"** pour en ajouter une
4. Si vous avez déjà une app Web : cliquez dessus
5. Dans le panneau **"Configuration du SDK"**, sélectionnez **"Config"**
6. **Copiez tout l'objet** `firebaseConfig` affiché
7. Mettez à jour les fichiers du projet avec ces valeurs (voir ci-dessous)

### Fichiers à mettre à jour avec la nouvelle config

- `admin/dashboard.php` (lignes ~202-208)
- `user/mon-compte.php` (lignes ~174-179)
- `firebase-messaging-sw.js` (lignes 6-12)
- `config/firebase_config.php`

---

## Méthode 3 : Créer une nouvelle clé API sans restrictions

1. Allez sur : **https://console.cloud.google.com/apis/credentials?project=sugar-paper**
2. Cliquez sur **"+ Créer des identifiants"** → **"Clé API"**
3. Une nouvelle clé est créée - **copiez sa valeur**
4. Cliquez sur **"Restreindre la clé"** (pour la configurer)
5. **Restrictions d'application** : Aucun
6. **Restrictions d'API** : **Ne pas restreindre la clé**
7. Enregistrez
8. Remplacez `apiKey` dans tous les fichiers par cette nouvelle clé

---

## Vérification

Après toute modification, attendez 2-5 minutes, puis :
1. Fermez complètement le navigateur (toutes les fenêtres)
2. Rouvrez et allez sur http://localhost:5000/admin/dashboard.php
3. Cliquez sur "Activer les notifications"
