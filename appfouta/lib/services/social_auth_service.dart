import 'package:firebase_auth/firebase_auth.dart';
import 'package:google_sign_in/google_sign_in.dart';

import '../config/firebase_auth_config.dart';

/// Connexion Google native (hors WebView) → token Firebase pour le site PHP.
class SocialAuthService {
  static final GoogleSignIn _googleSignIn = GoogleSignIn(
    scopes: ['email', 'profile'],
    serverClientId: kFirebaseWebClientId.contains('REMPLACEZ')
        ? null
        : kFirebaseWebClientId,
  );

  static Future<Map<String, dynamic>> signInWithGoogle() async {
    if (kFirebaseWebClientId.contains('REMPLACEZ')) {
      return {
        'success': false,
        'error':
            'Web Client ID Firebase non configuré dans firebase_auth_config.dart',
      };
    }

    try {
      await _googleSignIn.signOut();

      final GoogleSignInAccount? account = await _googleSignIn.signIn();
      if (account == null) {
        return {'success': false, 'error': 'Connexion Google annulée.'};
      }

      final GoogleSignInAuthentication auth = await account.authentication;
      final String? idToken = auth.idToken;
      final String? accessToken = auth.accessToken;

      if (idToken == null || idToken.isEmpty) {
        return {
          'success': false,
          'error':
              'Token Google introuvable. Vérifiez le Web Client ID et la SHA-1 dans Firebase.',
        };
      }

      final OAuthCredential credential = GoogleAuthProvider.credential(
        accessToken: accessToken,
        idToken: idToken,
      );

      final UserCredential userCredential =
          await FirebaseAuth.instance.signInWithCredential(credential);
      final User? user = userCredential.user;

      if (user == null) {
        return {'success': false, 'error': 'Compte Firebase introuvable.'};
      }

      final String firebaseIdToken = await user.getIdToken() ?? '';
      if (firebaseIdToken.isEmpty) {
        return {'success': false, 'error': 'Token Firebase introuvable.'};
      }

      return {
        'success': true,
        'idToken': firebaseIdToken,
        'email': user.email ?? '',
        'displayName': user.displayName ?? '',
      };
    } on FirebaseAuthException catch (e) {
      return {
        'success': false,
        'error': 'Firebase Auth : ${e.message ?? e.code}',
      };
    } catch (e) {
      return {'success': false, 'error': e.toString()};
    }
  }
}
