import 'package:flutter/material.dart';
import 'package:permission_handler/permission_handler.dart';

/// Affiche une explication **avant** la boîte de dialogue système (exigence Apple 5.1.1).
class PermissionRationaleService {
  static const Color _bleu = Color(0xFF3564A6);
  static const Color _orange = Color(0xFFFF6B35);

  /// Dialogue informatif : l'utilisateur choisit s'il veut continuer vers la demande système.
  static Future<bool> confirmRationale(
    BuildContext context, {
    required String title,
    required String message,
    String confirmLabel = 'Continuer',
    String cancelLabel = 'Annuler',
  }) async {
    if (!context.mounted) return false;

    final result = await showDialog<bool>(
      context: context,
      barrierDismissible: false,
      builder: (ctx) => AlertDialog(
        icon: const Icon(Icons.privacy_tip_outlined, color: _bleu, size: 32),
        title: Text(title, style: const TextStyle(fontWeight: FontWeight.w700)),
        content: SingleChildScrollView(
          child: Text(message, style: const TextStyle(height: 1.5)),
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.of(ctx).pop(false),
            child: Text(cancelLabel),
          ),
          FilledButton(
            style: FilledButton.styleFrom(
              backgroundColor: _bleu,
              foregroundColor: Colors.white,
            ),
            onPressed: () => Navigator.of(ctx).pop(true),
            child: Text(confirmLabel),
          ),
        ],
      ),
    );

    return result ?? false;
  }

  /// Demande une permission Android/iOS après explication ; gère le refus permanent.
  static Future<PermissionStatus> requestWithRationale(
    BuildContext context,
    Permission permission, {
    required String title,
    required String message,
  }) async {
    var status = await permission.status;
    if (!context.mounted) return status;

    if (status.isGranted || status.isLimited) {
      return status;
    }

    if (status.isPermanentlyDenied) {
      await _showOpenSettingsDialog(
        context,
        title: title,
        message:
            'L\'accès a été refusé précédemment. Pour l\'activer, ouvrez les paramètres de l\'application FOUTA PL et autorisez la permission concernée.',
      );
      return status;
    }

    final accepted = await confirmRationale(
      context,
      title: title,
      message: message,
      confirmLabel: 'Autoriser l\'accès',
    );
    if (!accepted || !context.mounted) {
      return status;
    }

    status = await permission.request();
    if (!context.mounted) return status;

    if (status.isPermanentlyDenied) {
      await _showOpenSettingsDialog(
        context,
        title: title,
        message:
            'L\'accès a été refusé. Vous pouvez l\'activer ultérieurement dans les paramètres de l\'application.',
      );
    }
    return status;
  }

  static Future<void> _showOpenSettingsDialog(
    BuildContext context, {
    required String title,
    required String message,
  }) async {
    if (!context.mounted) return;

    await showDialog<void>(
      context: context,
      builder: (ctx) => AlertDialog(
        title: Text(title),
        content: Text(message),
        actions: [
          TextButton(
            onPressed: () => Navigator.of(ctx).pop(),
            child: const Text('Fermer'),
          ),
          FilledButton(
            style: FilledButton.styleFrom(
              backgroundColor: _orange,
              foregroundColor: Colors.white,
            ),
            onPressed: () {
              Navigator.of(ctx).pop();
              openAppSettings();
            },
            child: const Text('Ouvrir les paramètres'),
          ),
        ],
      ),
    );
  }

  /// Textes alignés sur Info.plist / Play Console / pages légales du site.
  static const String cameraTitle = 'Accès à la caméra';
  static const String cameraMessage =
      'FOUTA POIDS LOURDS utilise la caméra uniquement lorsque vous appuyez sur « Prendre une photo » sur le site.\n\n'
      'Finalité : photographier des pièces détachées et produits pour camions (fiches catalogue, stock) ou la photo d\'un employé (profil RH).\n\n'
      'Exemple : prendre la photo d\'un amortisseur châssis à ajouter à une fiche produit, ou la photo d\'identité d\'un collaborateur.\n\n'
      'Aucune capture sans votre action explicite.';

  static const String locationTitle = 'Accès à la localisation';
  static const String locationMessage =
      'FOUTA POIDS LOURDS utilise votre position uniquement lorsque vous activez la localisation sur le site.\n\n'
      'Finalité : organiser et suivre les livraisons de pièces, confirmer une adresse de livraison client ou afficher un point sur la carte.\n\n'
      'Exemple : indiquer le lieu de livraison d\'une commande ou la position d\'un véhicule lors d\'une tournée.\n\n'
      'La position n\'est pas suivie en arrière-plan.';

  static const String photosTitle = 'Accès à la galerie et au stockage';
  static const String photosMessage =
      'FOUTA POIDS LOURDS accède à votre galerie ou à vos fichiers uniquement si vous choisissez « Importer » ou « Téléverser » un document.\n\n'
      'Finalité : téléverser la photo d\'un employé, un contrat de travail, un CV ou tout autre document RH ou administratif.\n\n'
      'Exemple : joindre le CV ou le contrat d\'un nouvel employé depuis votre téléphone.\n\n'
      'Aucun fichier n\'est lu sans votre sélection.';

  static const String notificationsTitle = 'Notifications';
  static const String notificationsMessage =
      'FOUTA POIDS LOURDS peut vous envoyer des notifications pour le suivi de vos commandes, les mises à jour de statut et les messages liés à votre compte professionnel.\n\n'
      'Vous pourrez désactiver les notifications à tout moment dans les paramètres de votre téléphone.';
}
