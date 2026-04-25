# Guide d'installation de FFmpeg pour la génération de thumbnails vidéo

## Pourquoi FFmpeg ?

FFmpeg est nécessaire pour générer automatiquement des images de prévisualisation (thumbnails) à partir des vidéos uploadées. Ces thumbnails servent d'aperçu visuel dans l'interface d'administration.

## Installation sur Windows (WampServer)

### Méthode 1 : Installation manuelle (Recommandée)

1. **Télécharger FFmpeg** :
   - Allez sur https://www.ffmpeg.org/download.html
   - Cliquez sur "Windows builds" puis sur "Windows builds by BtbN"
   - Téléchargez la version "release build" (par exemple : `ffmpeg-release-essentials.zip`)

2. **Extraire FFmpeg** :
   - Extrayez l'archive dans `C:\ffmpeg\` (créer le dossier si nécessaire)
   - Vous devriez avoir un dossier `C:\ffmpeg\bin\` contenant `ffmpeg.exe`

3. **Ajouter au PATH (optionnel mais recommandé)** :
   - Ouvrez les variables d'environnement Windows
   - Ajoutez `C:\ffmpeg\bin` à la variable PATH
   - Redémarrez votre ordinateur ou relancez WampServer

### Méthode 2 : Installation dans le dossier WampServer

Si vous préférez installer FFmpeg dans le dossier WampServer :

1. Créez le dossier `C:\wamp64\bin\ffmpeg\bin\`
2. Extrayez `ffmpeg.exe` dans ce dossier
3. Le système le détectera automatiquement

### Méthode 3 : Installation via Chocolatey (si installé)

```powershell
choco install ffmpeg
```

## Vérification de l'installation

Pour vérifier que FFmpeg est bien installé et accessible :

1. Ouvrez une invite de commande (cmd ou PowerShell)
2. Tapez : `ffmpeg -version`
3. Vous devriez voir la version de FFmpeg s'afficher

## Configuration dans l'application

L'application essaie automatiquement de trouver FFmpeg dans les emplacements suivants :

1. Dans le PATH système (`ffmpeg`)
2. `C:\ffmpeg\bin\ffmpeg.exe`
3. `C:\wamp64\bin\ffmpeg\bin\ffmpeg.exe`
4. `C:\Program Files\ffmpeg\bin\ffmpeg.exe`

Si FFmpeg n'est pas trouvé, la génération de thumbnail sera ignorée mais l'upload de vidéo fonctionnera toujours normalement.

## Note importante

Si FFmpeg n'est pas installé, les vidéos seront uploadées avec succès mais **aucune thumbnail ne sera générée**. L'interface affichera la vidéo directement avec les contrôles de lecture, ce qui reste fonctionnel.

## Support

Si vous rencontrez des problèmes :
1. Vérifiez que `ffmpeg.exe` existe bien dans l'un des emplacements mentionnés
2. Vérifiez les permissions d'exécution
3. Consultez les logs d'erreur PHP pour plus de détails
