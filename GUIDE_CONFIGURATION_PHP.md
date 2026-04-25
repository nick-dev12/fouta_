# Guide de Configuration PHP pour l'Upload de Vidéos

## Problème
Vous recevez l'erreur : "Le fichier est trop volumineux. La taille maximale autorisée par PHP (post_max_size) est: 8M"

## Solution

### Étape 1 : Vérifier la configuration actuelle
1. Ouvrez votre navigateur et allez à : `http://localhost/samba-market/check_php_config.php`
2. Notez les valeurs affichées pour `post_max_size` et `upload_max_filesize`

### Étape 2 : Modifier le fichier php.ini pour Apache

**IMPORTANT** : Le fichier `c:\wamp64\bin\php\php8.3.14\php.ini` est pour CLI/FCGI.
Pour Apache, vous devez modifier le fichier utilisé par Apache.

#### Option A : Via WampServer (Recommandé)
1. Cliquez sur l'icône WampServer dans la barre des tâches
2. Allez dans **PHP** → **php.ini**
3. Recherchez les lignes suivantes et modifiez-les :
   ```
   post_max_size = 1000M
   upload_max_filesize = 1000M
   max_execution_time = 600
   max_input_time = 600
   memory_limit = 512M
   ```
4. **SAUVEZ le fichier**
5. **REDÉMARREZ Apache** (WampServer → Apache → Service → Restart Service)

#### Option B : Modifier directement le fichier
1. Le fichier php.ini pour Apache se trouve généralement dans :
   - `c:\wamp64\bin\apache\apache2.4.62.1\bin\php.ini` (si ce fichier existe)
   - OU `c:\wamp64\bin\php\php8.3.14\php.ini` (si Apache l'utilise)

2. Recherchez et modifiez ces lignes :
   ```
   post_max_size = 1000M
   upload_max_filesize = 1000M
   max_execution_time = 600
   max_input_time = 600
   memory_limit = 512M
   ```

3. **SAUVEZ le fichier**
4. **REDÉMARREZ Apache**

### Étape 3 : Vérifier que les modifications sont appliquées
1. Allez à nouveau sur : `http://localhost/samba-market/check_php_config.php`
2. Vérifiez que `post_max_size` et `upload_max_filesize` sont maintenant à 1000M ou plus

### Étape 4 : Fichier .htaccess (Déjà créé)
Un fichier `.htaccess` a été créé dans le dossier du projet pour forcer ces valeurs.
Si les modifications du php.ini ne fonctionnent pas, le fichier `.htaccess` devrait prendre le relais.

### Notes importantes
- **post_max_size** doit être **supérieur ou égal** à `upload_max_filesize`
- Pour une vidéo de 250MB, recommandez au moins **300M** pour avoir une marge
- Après chaque modification du php.ini, **REDÉMARREZ TOUJOURS Apache**
- Si vous utilisez WampServer, utilisez l'interface graphique pour modifier le php.ini (c'est plus sûr)

### Valeurs recommandées pour upload de vidéos volumineuses
```
post_max_size = 1000M
upload_max_filesize = 1000M
max_execution_time = 600
max_input_time = 600
memory_limit = 512M
```
