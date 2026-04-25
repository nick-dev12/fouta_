# Correction de l'erreur "API key not valid"

L'erreur **"API key not valid. Please pass a valid API key"** signifie que la clé API Firebase est invalide ou a des restrictions qui bloquent l'accès.

## Solution : Configurer la clé API dans Google Cloud Console

### Étape 1 : Accéder aux identifiants

1. Allez sur [Google Cloud Console - Credentials](https://console.cloud.google.com/apis/credentials)
2. Sélectionnez le projet **sugar-paper**
3. Dans la liste des clés API, trouvez la clé utilisée par votre app web (ou celle avec la restriction "Browser key")

### Étape 2 : Modifier les restrictions

Cliquez sur la clé API (ou sur l'icône crayon pour modifier), puis :

#### A) Restrictions d'application (Application restrictions)

- **Option recommandée pour le développement** : Sélectionnez **"Aucune"** (Don't restrict key)
- **Ou** si vous utilisez "Référents HTTP" :
  - Ajoutez : `http://localhost:5000/*`
  - Ajoutez : `http://localhost:*/*` (pour tout port localhost)
  - Pour la production : `https://votredomaine.com/*`

#### B) Restrictions d'API (API restrictions)

- **Option recommandée** : Sélectionnez **"Ne pas restreindre la clé"** (Don't restrict key)
- **Ou** si vous restreignez : assurez-vous d'inclure :
  - `Firebase Installations API` (firebaseinstallations.googleapis.com)
  - `Firebase Cloud Messaging API`
  - Toutes les APIs Firebase nécessaires

### Étape 3 : Activer les APIs requises

1. Allez sur [APIs & Services - Enabled APIs](https://console.cloud.google.com/apis/dashboard)
2. Cliquez sur **"+ ENABLE APIS AND SERVICES"**
3. Recherchez et activez :
   - **Firebase Installations API**
   - **Firebase Cloud Messaging API** (si pas déjà activé)

### Étape 4 : Récupérer une nouvelle clé (si nécessaire)

Si le problème persiste, récupérez une nouvelle clé :

1. [Firebase Console](https://console.firebase.google.com/) → Projet **sugar-paper**
2. Paramètres du projet (icône engrenage) → **Paramètres du projet**
3. Onglet **Général** → section **Vos applications**
4. Cliquez sur votre application Web
5. Copiez la configuration (apiKey, etc.)
6. Mettez à jour les fichiers : `admin/dashboard.php`, `user/mon-compte.php`, `firebase-messaging-sw.js`, `config/firebase_config.php`

---

## Vérification rapide

Après modification, attendez 1-2 minutes que les changements se propagent, puis réessayez d'activer les notifications.
