import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_inappwebview/flutter_inappwebview.dart';
import 'package:permission_handler/permission_handler.dart';
import 'package:image_picker/image_picker.dart';
import 'package:geolocator/geolocator.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'package:firebase_core/firebase_core.dart';
import 'package:firebase_messaging/firebase_messaging.dart';
import 'package:app_links/app_links.dart';
import 'dart:convert';
import 'dart:collection';
import 'dart:async';
import 'services/fcm_service.dart';
import 'services/social_auth_service.dart';
import 'services/permission_rationale_service.dart';

/// URL du site chargé dans la WebView (production — aligné avec le site officiel).
const String kMarketplaceBaseUrl = 'https://www.e.foutapoidslourds.com/';
// Palette `css/variables.css` (couleurs de base du site).
const Color kBleuPrincipal = Color(0xFF3564A6);
const Color kBleuPrincipalHover = Color(0xFF2D5690);
const Color kBlancSite = Color(0xFFFFFFFF);
const Color kNoirSite = Color(0xFF0D0D0D);
const Color kOrangePromo = Color(0xFFFF6B35);
/// Durée max de l'écran de chargement initial
const Duration kInitialLoaderMaxDuration = Duration(seconds: 5);

bool _isMarketplaceHost(String host) {
  final h = host.toLowerCase();
  return h == 'e.foutapoidslourds.com' ||
      h == 'www.e.foutapoidslourds.com';
}

String resolveRelativeMarketUrl(String href) {
  if (href.isEmpty) {
    return kMarketplaceBaseUrl;
  }
  if (href.startsWith('http://') || href.startsWith('https://')) {
    return href;
  }
  final base = kMarketplaceBaseUrl.replaceAll(RegExp(r'/$'), '');
  final path = href.startsWith('/') ? href : '/$href';
  return '$base$path';
}

// Handler pour les notifications en arrière-plan
@pragma('vm:entry-point')
Future<void> firebaseMessagingBackgroundHandler(RemoteMessage message) async {
  await Firebase.initializeApp();
  print(
    '📬 Notification reçue en arrière-plan: ${message.notification?.title}',
  );
}

void main() async {
  WidgetsFlutterBinding.ensureInitialized();

  print('🔥 Démarrage de l\'application...');

  try {
    // Initialiser Firebase
    print('🔥 Initialisation de Firebase...');
    await Firebase.initializeApp();
    print('✅ Firebase initialisé avec succès');
  } catch (e) {
    print('❌ ERREUR lors de l\'initialisation Firebase: $e');
  }

  try {
    // Configurer le handler pour les notifications en arrière-plan
    print('🔥 Configuration du handler en arrière-plan...');
    FirebaseMessaging.onBackgroundMessage(firebaseMessagingBackgroundHandler);
    print('✅ Handler en arrière-plan configuré');
  } catch (e) {
    print('❌ ERREUR lors de la configuration du handler: $e');
  }

  runApp(const FoutaPoidsLourdsApp());
}

class FoutaPoidsLourdsApp extends StatelessWidget {
  const FoutaPoidsLourdsApp({super.key});

  @override
  Widget build(BuildContext context) {
    return MaterialApp(
      title: 'FOUTA POIDS LOURDS',
      debugShowCheckedModeBanner: false,
      theme: ThemeData(
        colorScheme: ColorScheme.fromSeed(
          seedColor: kBleuPrincipal,
          primary: kBleuPrincipal,
          secondary: kOrangePromo,
          surface: kBlancSite,
        ),
        useMaterial3: true,
      ),
      home: const WebViewScreen(),
    );
  }
}

class WebViewScreen extends StatefulWidget {
  const WebViewScreen({super.key});

  @override
  State<WebViewScreen> createState() => _WebViewScreenState();
}

class _WebViewScreenState extends State<WebViewScreen>
    with WidgetsBindingObserver {
  InAppWebViewController? webViewController;
  bool isLoading = true;
  bool isInitialLoad = true; // Pour distinguer le premier chargement
  double progress = 0;
  double _simulatedProgress = 0;
  String? _currentUrl; // Sauvegarder l'URL actuelle
  String? _deepLinkInitUrl; // URL reçue via deep link (prioritaire)
  Timer? _loaderMaxTimer;
  Timer? _progressSimTimer;
  StreamSubscription<Uri>? _deepLinkSub; // Abonnement aux deep links entrants

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addObserver(this);
    _startInitialLoadTimers();
    _initializeFCM();
    // Les deep links sont initialisés EN PREMIER pour prendre priorité
    _initDeepLinks().then((_) => _loadSavedUrl());
  }

  void _startInitialLoadTimers() {
    _loaderMaxTimer = Timer(kInitialLoaderMaxDuration, _forceFinishInitialLoad);
    _progressSimTimer = Timer.periodic(const Duration(milliseconds: 50), (_) {
      if (!mounted || !isInitialLoad) return;
      setState(() {
        if (_simulatedProgress < 0.9) {
          _simulatedProgress = (_simulatedProgress + 0.018).clamp(0.0, 0.9);
        }
      });
    });
  }

  double get _loaderProgress {
    return progress > _simulatedProgress ? progress : _simulatedProgress;
  }

  void _forceFinishInitialLoad() {
    if (!mounted || !isInitialLoad) return;
    _finishInitialLoad();
  }

  void _finishInitialLoad() {
    _loaderMaxTimer?.cancel();
    _progressSimTimer?.cancel();
    if (!mounted) return;
    setState(() {
      isLoading = false;
      isInitialLoad = false;
      progress = 1.0;
      _simulatedProgress = 1.0;
    });
  }

  Future<void> _postLoadSetup() async {
    await _injectJavaScript();
    await _registerFCMTokenInWebView();
  }

  @override
  void dispose() {
    _loaderMaxTimer?.cancel();
    _progressSimTimer?.cancel();
    _deepLinkSub?.cancel();
    WidgetsBinding.instance.removeObserver(this);
    super.dispose();
  }

  // Observer le cycle de vie de l'application
  @override
  void didChangeAppLifecycleState(AppLifecycleState state) {
    super.didChangeAppLifecycleState(state);
    if (state == AppLifecycleState.paused) {
      // Application en arrière-plan - sauvegarder l'URL
      _saveCurrentUrl();
    } else if (state == AppLifecycleState.resumed) {
      // Application revenue au premier plan - restaurer l'URL si nécessaire
      _restoreUrlIfNeeded();
    }
  }

  // Sauvegarder l'URL actuelle
  Future<void> _saveCurrentUrl() async {
    if (webViewController != null) {
      try {
        final currentUrl = await webViewController!.getUrl();
        if (currentUrl != null) {
          _currentUrl = currentUrl.toString();
          final prefs = await SharedPreferences.getInstance();
          await prefs.setString('last_webview_url', _currentUrl!);
        }
      } catch (e) {
        print('Erreur lors de la sauvegarde de l\'URL: $e');
      }
    }
  }

  // Initialiser la gestion des deep links (Android App Links + iOS Universal Links)
  Future<void> _initDeepLinks() async {
    final appLinks = AppLinks();

    // Lien ayant lancé l'app à froid (cold start)
    try {
      final initialUri = await appLinks.getInitialLink();
      if (initialUri != null && _isMarketplaceHost(initialUri.host)) {
        _deepLinkInitUrl = initialUri.toString();
        _currentUrl = _deepLinkInitUrl;
      }
    } catch (_) {}

    // Liens reçus quand l'app est déjà ouverte (warm/hot start)
    _deepLinkSub = appLinks.uriLinkStream.listen((uri) {
      if (_isMarketplaceHost(uri.host)) {
        final url = uri.toString();
        if (webViewController != null) {
          webViewController!.loadUrl(
            urlRequest: URLRequest(url: WebUri(url)),
          );
        } else {
          // L'app vient de démarrer : on mémorise l'URL pour qu'elle soit
          // chargée dès que la WebView sera prête
          _currentUrl = url;
          _deepLinkInitUrl = url;
        }
      }
    }, onError: (_) {});
  }

  // Charger l'URL sauvegardée (invalide l'ancien domaine Aria)
  // Ne s'applique que si aucun deep link n'a été reçu au lancement
  Future<void> _loadSavedUrl() async {
    // Un deep link est prioritaire sur l'URL sauvegardée
    if (_deepLinkInitUrl != null) return;
    try {
      final prefs = await SharedPreferences.getInstance();
      final savedUrl = prefs.getString('last_webview_url');
      if (savedUrl != null && savedUrl.isNotEmpty) {
        if (savedUrl.contains('aria-edu.com') ||
            savedUrl.contains('samapiece.it.com') ||
            savedUrl.contains('colobanes.com')) {
          await prefs.remove('last_webview_url');
          _currentUrl = null;
        } else {
          try {
            final u = Uri.parse(savedUrl);
            if (u.hasAuthority && _isMarketplaceHost(u.host)) {
              _currentUrl = savedUrl;
            } else {
              await prefs.remove('last_webview_url');
              _currentUrl = null;
            }
          } catch (_) {
            await prefs.remove('last_webview_url');
            _currentUrl = null;
          }
        }
      }
    } catch (e) {
      print('Erreur lors du chargement de l\'URL sauvegardée: $e');
    }
  }

  // Restaurer l'URL si nécessaire (seulement si différente de l'URL actuelle)
  Future<void> _restoreUrlIfNeeded() async {
    if (webViewController != null && _currentUrl != null) {
      try {
        final currentUrl = await webViewController!.getUrl();
        // Si la WebView est toujours sur la bonne page, ne rien faire
        if (currentUrl != null && currentUrl.toString() == _currentUrl) {
          // La page est déjà chargée, pas besoin de recharger
          return;
        }
        // Sinon, recharger l'URL sauvegardée (utilise le cache si disponible)
        await webViewController!.loadUrl(
          urlRequest: URLRequest(url: WebUri(_currentUrl!)),
        );
      } catch (e) {
        print('Erreur lors de la restauration de l\'URL: $e');
      }
    }
  }

  // Initialiser Firebase Cloud Messaging
  Future<void> _initializeFCM() async {
    print('🔥 Début de l\'initialisation FCM...');
    try {
      if (mounted) {
        final notifStatus = await Permission.notification.status;
        if (!notifStatus.isGranted) {
          final accepted = await PermissionRationaleService.confirmRationale(
            context,
            title: PermissionRationaleService.notificationsTitle,
            message: PermissionRationaleService.notificationsMessage,
            confirmLabel: 'Activer les notifications',
            cancelLabel: 'Plus tard',
          );
          if (!accepted) {
            print('ℹ️ Notifications refusées par l\'utilisateur (pré-prompt)');
          }
        }
      }

      // Définir le callback pour la navigation depuis les notifications
      print('🔥 Configuration du callback de navigation...');
      FCMService.setNotificationTapCallback((url) {
        if (webViewController != null && url.isNotEmpty) {
          final fullUrl = resolveRelativeMarketUrl(url);
          webViewController?.loadUrl(
            urlRequest: URLRequest(url: WebUri(fullUrl)),
          );
        }
      });
      print('✅ Callback de navigation configuré');

      print('🔥 Initialisation de FCMService avec URL: $kMarketplaceBaseUrl');
      final token = await FCMService.initialize(kMarketplaceBaseUrl);

      if (token != null) {
        print(
          '✅ FCM initialisé avec succès, token: ${token.substring(0, 20)}...',
        );
      } else {
        print('⚠️ FCM initialisé mais token non obtenu');
      }

      // Configurer le rafraîchissement automatique du token
      print('🔥 Configuration du rafraîchissement automatique du token...');
      FCMService.setupTokenRefresh();
      print('✅ Rafraîchissement automatique configuré');

      print('✅ FCM complètement initialisé avec succès');
    } catch (e, stackTrace) {
      print('❌ ERREUR lors de l\'initialisation FCM: $e');
      print('❌ Stack trace: $stackTrace');
    }
  }

  // Gérer les messages JavaScript depuis la WebView
  void _setupJavaScriptHandlers() {
    webViewController?.addJavaScriptHandler(
      handlerName: 'requestCamera',
      callback: (args) async {
        return await _handleCameraRequest();
      },
    );

    webViewController?.addJavaScriptHandler(
      handlerName: 'requestLocation',
      callback: (args) async {
        return await _handleLocationRequest();
      },
    );

    webViewController?.addJavaScriptHandler(
      handlerName: 'requestGallery',
      callback: (args) async {
        return await _handleGalleryRequest();
      },
    );

    webViewController?.addJavaScriptHandler(
      handlerName: 'saveData',
      callback: (args) async {
        if (args.isNotEmpty) {
          final data = args[0];
          return await _handleSaveData(data);
        }
        return {'success': false, 'error': 'No data provided'};
      },
    );

    webViewController?.addJavaScriptHandler(
      handlerName: 'getData',
      callback: (args) async {
        if (args.isNotEmpty) {
          final key = args[0] as String;
          return await _handleGetData(key);
        }
        return {'success': false, 'error': 'No key provided'};
      },
    );

    webViewController?.addJavaScriptHandler(
      handlerName: 'showNotification',
      callback: (args) async {
        if (args.length >= 2) {
          final title = args[0] as String;
          final body = args[1] as String;
          return await _handleShowNotification(title, body);
        }
        return {'success': false, 'error': 'Invalid parameters'};
      },
    );

    webViewController?.addJavaScriptHandler(
      handlerName: 'signInWithGoogle',
      callback: (args) async {
        return SocialAuthService.signInWithGoogle();
      },
    );
  }

  // Gérer la demande de caméra
  Future<Map<String, dynamic>> _handleCameraRequest() async {
    try {
      if (!mounted) {
        return {'success': false, 'error': 'Contexte indisponible.'};
      }

      final cameraStatus = await PermissionRationaleService.requestWithRationale(
        context,
        Permission.camera,
        title: PermissionRationaleService.cameraTitle,
        message: PermissionRationaleService.cameraMessage,
      );

      if (!cameraStatus.isGranted) {
        return {
          'success': false,
          'error': cameraStatus.isPermanentlyDenied
              ? 'L\'accès à la caméra est refusé. Activez-le dans les paramètres de l\'application.'
              : 'L\'accès à la caméra a été refusé.',
        };
      }

      final ImagePicker picker = ImagePicker();
      final XFile? image = await picker.pickImage(
        source: ImageSource.camera,
        imageQuality: 85,
      );

      if (image != null) {
        final bytes = await image.readAsBytes();
        final base64Image = base64Encode(bytes);
        return {
          'success': true,
          'image': 'data:image/jpeg;base64,$base64Image',
          'path': image.path,
        };
      }
      return {'success': false, 'error': 'Aucune image sélectionnée'};
    } catch (e) {
      return {'success': false, 'error': e.toString()};
    }
  }

  // Importer une photo depuis la galerie (stockage / photothèque)
  Future<Map<String, dynamic>> _handleGalleryRequest() async {
    try {
      if (!mounted) {
        return {'success': false, 'error': 'Contexte indisponible.'};
      }

      final photosStatus = await PermissionRationaleService.requestWithRationale(
        context,
        Permission.photos,
        title: PermissionRationaleService.photosTitle,
        message: PermissionRationaleService.photosMessage,
      );

      if (!photosStatus.isGranted && !photosStatus.isLimited) {
        return {
          'success': false,
          'error': photosStatus.isPermanentlyDenied
              ? 'L\'accès aux photos est refusé. Activez-le dans les paramètres de l\'application.'
              : 'L\'accès à la galerie a été refusé.',
        };
      }

      final ImagePicker picker = ImagePicker();
      final XFile? image = await picker.pickImage(
        source: ImageSource.gallery,
        imageQuality: 85,
      );

      if (image != null) {
        final bytes = await image.readAsBytes();
        final base64Image = base64Encode(bytes);
        return {
          'success': true,
          'image': 'data:image/jpeg;base64,$base64Image',
          'path': image.path,
        };
      }
      return {'success': false, 'error': 'Aucune image sélectionnée'};
    } catch (e) {
      return {'success': false, 'error': e.toString()};
    }
  }

  // Gérer la demande de localisation
  Future<Map<String, dynamic>> _handleLocationRequest() async {
    try {
      if (!mounted) {
        return {'success': false, 'error': 'Contexte indisponible.'};
      }

      bool serviceEnabled = await Geolocator.isLocationServiceEnabled();
      if (!serviceEnabled) {
        return {
          'success': false,
          'error': 'Les services de localisation sont désactivés sur l\'appareil.',
        };
      }

      LocationPermission permission = await Geolocator.checkPermission();
      if (permission == LocationPermission.denied) {
        final accepted = await PermissionRationaleService.confirmRationale(
          context,
          title: PermissionRationaleService.locationTitle,
          message: PermissionRationaleService.locationMessage,
          confirmLabel: 'Autoriser la localisation',
        );
        if (!accepted) {
          return {
            'success': false,
            'error': 'Autorisation de localisation refusée.',
          };
        }
        permission = await Geolocator.requestPermission();
        if (!mounted) {
          return {'success': false, 'error': 'Contexte indisponible.'};
        }
        if (permission == LocationPermission.denied) {
          return {
            'success': false,
            'error': 'Autorisation de localisation refusée.',
          };
        }
      }

      if (permission == LocationPermission.deniedForever) {
        if (mounted) {
          await PermissionRationaleService.requestWithRationale(
            context,
            Permission.locationWhenInUse,
            title: PermissionRationaleService.locationTitle,
            message: PermissionRationaleService.locationMessage,
          );
        }
        return {
          'success': false,
          'error':
              'Autorisation de localisation refusée de façon permanente. Activez-la dans les paramètres.',
        };
      }

      Position position = await Geolocator.getCurrentPosition(
        desiredAccuracy: LocationAccuracy.high,
      );

      return {
        'success': true,
        'latitude': position.latitude,
        'longitude': position.longitude,
        'accuracy': position.accuracy,
        'altitude': position.altitude,
        'speed': position.speed,
        'timestamp': position.timestamp.toIso8601String(),
      };
    } catch (e) {
      return {'success': false, 'error': e.toString()};
    }
  }

  // Sauvegarder des données localement
  Future<Map<String, dynamic>> _handleSaveData(dynamic data) async {
    try {
      final prefs = await SharedPreferences.getInstance();
      if (data is Map) {
        for (var entry in data.entries) {
          final key = entry.key.toString();
          final value = entry.value;
          if (value is String) {
            await prefs.setString(key, value);
          } else if (value is int) {
            await prefs.setInt(key, value);
          } else if (value is double) {
            await prefs.setDouble(key, value);
          } else if (value is bool) {
            await prefs.setBool(key, value);
          } else {
            await prefs.setString(key, jsonEncode(value));
          }
        }
        return {'success': true};
      }
      return {'success': false, 'error': 'Invalid data format'};
    } catch (e) {
      return {'success': false, 'error': e.toString()};
    }
  }

  // Récupérer des données localement
  Future<Map<String, dynamic>> _handleGetData(String key) async {
    try {
      final prefs = await SharedPreferences.getInstance();
      final value = prefs.get(key);
      if (value != null) {
        return {'success': true, 'data': value};
      }
      return {'success': false, 'error': 'Key not found'};
    } catch (e) {
      return {'success': false, 'error': e.toString()};
    }
  }

  // Afficher une notification
  Future<Map<String, dynamic>> _handleShowNotification(
    String title,
    String body,
  ) async {
    try {
      // Ici vous pouvez intégrer flutter_local_notifications
      // Pour l'instant, on retourne un succès
      // Vous pouvez aussi afficher un snackbar ou dialog
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text('$title: $body'),
            duration: const Duration(seconds: 3),
          ),
        );
      }
      return {'success': true};
    } catch (e) {
      return {'success': false, 'error': e.toString()};
    }
  }

  // Injecter le code JavaScript pour la communication
  Future<void> _injectJavaScript() async {
    const jsCode = '''
      (function() {
        window.__COLOBANES_NATIVE_APP = true;
        window.__FOUTA_NATIVE_APP = true;
        window.ColobanesNative = {
          // Demander l'accès à la caméra
          requestCamera: function() {
            return new Promise((resolve, reject) => {
              window.flutter_inappwebview.callHandler('requestCamera')
                .then(result => {
                  if (result.success) {
                    resolve(result);
                  } else {
                    reject(new Error(result.error || 'Camera request failed'));
                  }
                })
                .catch(error => reject(error));
            });
          },

          requestGallery: function() {
            return new Promise((resolve, reject) => {
              window.flutter_inappwebview.callHandler('requestGallery')
                .then(result => {
                  if (result.success) {
                    resolve(result);
                  } else {
                    reject(new Error(result.error || 'Gallery request failed'));
                  }
                })
                .catch(error => reject(error));
            });
          },
          
          // Demander la localisation
          requestLocation: function() {
            return new Promise((resolve, reject) => {
              window.flutter_inappwebview.callHandler('requestLocation')
                .then(result => {
                  if (result.success) {
                    resolve(result);
                  } else {
                    reject(new Error(result.error || 'Location request failed'));
                  }
                })
                .catch(error => reject(error));
            });
          },
          
          // Sauvegarder des données
          saveData: function(data) {
            return new Promise((resolve, reject) => {
              window.flutter_inappwebview.callHandler('saveData', data)
                .then(result => {
                  if (result.success) {
                    resolve(result);
                  } else {
                    reject(new Error(result.error || 'Save failed'));
                  }
                })
                .catch(error => reject(error));
            });
          },
          
          // Récupérer des données
          getData: function(key) {
            return new Promise((resolve, reject) => {
              window.flutter_inappwebview.callHandler('getData', key)
                .then(result => {
                  if (result.success) {
                    resolve(result.data);
                  } else {
                    reject(new Error(result.error || 'Get failed'));
                  }
                })
                .catch(error => reject(error));
            });
          },
          
          // Afficher une notification
          showNotification: function(title, body) {
            return new Promise((resolve, reject) => {
              window.flutter_inappwebview.callHandler('showNotification', title, body)
                .then(result => {
                  if (result.success) {
                    resolve(result);
                  } else {
                    reject(new Error(result.error || 'Notification failed'));
                  }
                })
                .catch(error => reject(error));
            });
          },

          // Connexion Google native (contourne le blocage WebView 403)
          signInWithGoogle: function() {
            return new Promise((resolve, reject) => {
              window.flutter_inappwebview.callHandler('signInWithGoogle')
                .then(result => {
                  if (result && result.success && result.idToken) {
                    resolve(result);
                  } else {
                    reject(new Error((result && result.error) ? result.error : 'Connexion Google impossible'));
                  }
                })
                .catch(error => reject(error));
            });
          },

          isNativeApp: function() {
            return true;
          }
        };
        window.FoutaNative = window.ColobanesNative;
        window.AriaNative = window.ColobanesNative;

        // Intercepter Google AVANT le JS du site (évite signInWithPopup bloqué en WebView)
        if (document.documentElement.getAttribute('data-colobanes-google-hook') !== '1') {
          document.documentElement.setAttribute('data-colobanes-google-hook', '1');
          document.addEventListener('click', function(event) {
            var googleBtn = event.target.closest('.google-auth-btn');
            if (!googleBtn || !window.flutter_inappwebview) return;

            event.preventDefault();
            event.stopImmediatePropagation();

            var wrap = googleBtn.closest('.social-auth');
            var msgEl = wrap ? wrap.querySelector('.social-auth-message') : null;
            var accountType = googleBtn.getAttribute('data-social-auth-type')
              || googleBtn.getAttribute('data-google-auth-type') || 'auto';
            var redirect = googleBtn.getAttribute('data-social-auth-redirect')
              || googleBtn.getAttribute('data-google-auth-redirect') || '';
            var originalHtml = googleBtn.innerHTML;

            function setMsg(text, isError) {
              if (!msgEl) return;
              msgEl.textContent = text || '';
              msgEl.classList.toggle('is-error', !!isError);
            }

            if (wrap) {
              wrap.querySelectorAll('.google-auth-btn, .apple-auth-btn').forEach(function(btn) {
                btn.disabled = true;
              });
            }
            setMsg('', false);

            window.flutter_inappwebview.callHandler('signInWithGoogle')
              .then(function(result) {
                if (!result || !result.success || !result.idToken) {
                  throw new Error((result && result.error) ? result.error : 'Connexion Google impossible.');
                }
                return fetch('/auth-firebase-callback.php', {
                  method: 'POST',
                  headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json'
                  },
                  credentials: 'same-origin',
                  body: JSON.stringify({
                    idToken: result.idToken,
                    accountType: accountType,
                    redirect: redirect,
                    provider: 'google'
                  })
                });
              })
              .then(function(response) {
                return response.json().catch(function() {
                  throw new Error('Réponse serveur invalide.');
                });
              })
              .then(function(data) {
                if (!data || !data.success || !data.redirect) {
                  throw new Error((data && data.message) ? data.message : 'Connexion refusée.');
                }
                window.location.href = data.redirect;
              })
              .catch(function(error) {
                setMsg(error && error.message ? error.message : 'Connexion annulée ou impossible.', true);
                if (wrap) {
                  wrap.querySelectorAll('.google-auth-btn, .apple-auth-btn').forEach(function(btn) {
                    btn.disabled = false;
                  });
                }
                googleBtn.innerHTML = originalHtml;
              });
          }, true);
          console.log('API native FOUTA / Colobanes (Google hook) active');
        }
        
        console.log('API native injectée : FoutaNative / ColobanesNative');
      })();
    ''';

    await webViewController?.evaluateJavascript(source: jsCode);
  }

  // Enregistrer le token FCM dans la WebView (utilise la session authentifiée)
  Future<void> _registerFCMTokenInWebView() async {
    try {
      final fcmToken = FCMService.getToken();
      if (fcmToken != null) {
        // Injecter le script pour enregistrer le token via la WebView
        final script = FCMService.getTokenRegistrationScript(fcmToken);
        await webViewController?.evaluateJavascript(source: script);
        print('📤 Token FCM envoyé via WebView');
      }
    } catch (e) {
      print('❌ Erreur lors de l\'enregistrement du token FCM: $e');
    }
  }

  // Gérer le bouton retour Android
  Future<bool> _handleBackButton() async {
    if (webViewController != null) {
      // Vérifier si la WebView peut revenir en arrière
      final canGoBack = await webViewController!.canGoBack();
      if (canGoBack) {
        // Revenir à la page précédente dans l'historique
        await webViewController!.goBack();
        return false; // Empêcher la fermeture de l'app
      }
    }
    // Si on ne peut pas revenir en arrière, fermer l'application
    return true; // Permettre la fermeture de l'app
  }

  @override
  Widget build(BuildContext context) {
    return PopScope(
      canPop: false, // Empêcher la fermeture automatique
      onPopInvoked: (didPop) async {
        if (!didPop) {
          final shouldPop = await _handleBackButton();
          if (shouldPop && context.mounted) {
            SystemNavigator.pop(); // Fermer l'application
          }
        }
      },
      child: Scaffold(
        // Désactiver la résize automatique pour améliorer les performances du clavier
        resizeToAvoidBottomInset: false,
        body: Stack(
          children: [
            SafeArea(
              child: InAppWebView(
                initialUrlRequest: URLRequest(
                  url: WebUri(_currentUrl ?? kMarketplaceBaseUrl),
                ),
                initialUserScripts: UnmodifiableListView<UserScript>([
                  UserScript(
                    source:
                        'window.__COLOBANES_NATIVE_APP = true; window.__FOUTA_NATIVE_APP = true;',
                    injectionTime: UserScriptInjectionTime.AT_DOCUMENT_START,
                  ),
                ]),
                initialSettings: InAppWebViewSettings(
                  applicationNameForUserAgent: 'FOUTA_POIDS_LOURDS_App',
                  javaScriptEnabled: true,
                  domStorageEnabled: true,
                  databaseEnabled: true,
                  useShouldOverrideUrlLoading: true,
                  mediaPlaybackRequiresUserGesture: false,
                  allowsInlineMediaPlayback: true,
                  iframeAllow: "camera",
                  iframeAllowFullscreen: true,
                  // Désactivé pour améliorer les performances
                  useOnLoadResource: false,
                  useOnDownloadStart: false,
                  useShouldInterceptRequest: false,
                  thirdPartyCookiesEnabled: true,
                  cacheEnabled: true,
                  clearCache: false,
                  transparentBackground: false,
                  supportZoom: true,
                  builtInZoomControls: false,
                  displayZoomControls: false,
                  // Optimisations pour le clavier
                  verticalScrollBarEnabled: true,
                  horizontalScrollBarEnabled: true,
                  // Désactiver les animations inutiles
                  disableVerticalScroll: false,
                  disableHorizontalScroll: false,
                ),
                onWebViewCreated: (controller) {
                  webViewController = controller;
                  _setupJavaScriptHandlers();
                },
                onLoadStart: (controller, url) {
                  setState(() {
                    isLoading = true;
                  });
                },
                onLoadStop: (controller, url) async {
                  if (url != null) {
                    _currentUrl = url.toString();
                    unawaited(_saveCurrentUrl());
                  }
                  _finishInitialLoad();
                  unawaited(_postLoadSetup());
                },
                onProgressChanged: (controller, progress) {
                  // Ne mettre à jour que si la différence est significative pour éviter trop de rebuilds
                  final newProgress = progress / 100;
                  if ((newProgress - this.progress).abs() > 0.01) {
                    setState(() {
                      this.progress = newProgress;
                    });
                  }
                },
                onPermissionRequest: (controller, request) async {
                  // Caméra uniquement si le site la demande ; pas de micro
                  final allowed = request.resources.where((r) {
                    final name = r.toString().toLowerCase();
                    return name.contains('camera');
                  }).toList();
                  if (allowed.isEmpty) {
                    return PermissionResponse(
                      resources: request.resources,
                      action: PermissionResponseAction.DENY,
                    );
                  }
                  return PermissionResponse(
                    resources: allowed,
                    action: PermissionResponseAction.GRANT,
                  );
                },
                onReceivedError: (controller, request, error) {
                  print('WebView Error: ${error.description}');
                },
                shouldOverrideUrlLoading: (controller, navigationAction) async {
                  return NavigationActionPolicy.ALLOW;
                },
              ),
            ),
            // Loader plein écran uniquement au lancement
            if (isLoading && isInitialLoad)
              _MarketplaceLoader(progress: _loaderProgress),
            // Barre de progression discrète en haut pour les navigations
            if (isLoading && !isInitialLoad)
              _TopProgressBar(progress: progress),
          ],
        ),
      ),
    );
  }
}

// Écran de chargement initial — fond blanc, logo, barre orange, textes bleu
class _MarketplaceLoader extends StatefulWidget {
  final double progress;

  const _MarketplaceLoader({required this.progress});

  @override
  State<_MarketplaceLoader> createState() => _MarketplaceLoaderState();
}

class _MarketplaceLoaderState extends State<_MarketplaceLoader>
    with TickerProviderStateMixin {
  static const double _ringSize = 196;
  static const double _circleSize = 158;

  late AnimationController _entryController;
  late AnimationController _pulseController;
  late Animation<double> _fadeAnimation;
  late Animation<double> _entryScaleAnimation;
  late Animation<double> _pulseScaleAnimation;
  late Animation<Offset> _slideAnimation;
  double _displayProgress = 0;
  AnimationController? _progressAnimController;

  @override
  void initState() {
    super.initState();
    _displayProgress = widget.progress.clamp(0.0, 1.0);
    _entryController = AnimationController(
      vsync: this,
      duration: const Duration(milliseconds: 800),
    );
    _pulseController = AnimationController(
      vsync: this,
      duration: const Duration(milliseconds: 1400),
    )..repeat(reverse: true);

    _fadeAnimation = CurvedAnimation(
      parent: _entryController,
      curve: Curves.easeOutCubic,
    );
    _entryScaleAnimation = Tween<double>(begin: 0.88, end: 1.0).animate(
      CurvedAnimation(parent: _entryController, curve: Curves.easeOutBack),
    );
    _pulseScaleAnimation = Tween<double>(begin: 0.9, end: 1.1).animate(
      CurvedAnimation(parent: _pulseController, curve: Curves.easeInOut),
    );
    _slideAnimation = Tween<Offset>(
      begin: const Offset(0, 0.05),
      end: Offset.zero,
    ).animate(
      CurvedAnimation(parent: _entryController, curve: Curves.easeOutCubic),
    );
    _entryController.forward();
  }

  @override
  void didUpdateWidget(covariant _MarketplaceLoader oldWidget) {
    super.didUpdateWidget(oldWidget);
    final target = widget.progress.clamp(0.0, 1.0);
    if ((target - _displayProgress).abs() > 0.001) {
      _animateProgressTo(target);
    }
  }

  void _animateProgressTo(double target) {
    _progressAnimController?.dispose();
    final begin = _displayProgress;
    final controller = AnimationController(
      vsync: this,
      duration: const Duration(milliseconds: 420),
    );
    _progressAnimController = controller;
    final animation = CurvedAnimation(
      parent: controller,
      curve: Curves.easeOutCubic,
    );
    animation.addListener(() {
      if (!mounted) return;
      setState(() {
        _displayProgress = begin + (target - begin) * animation.value;
      });
    });
    controller.addStatusListener((status) {
      if (status == AnimationStatus.completed ||
          status == AnimationStatus.dismissed) {
        controller.dispose();
        if (_progressAnimController == controller) {
          _progressAnimController = null;
        }
      }
    });
    controller.forward();
  }

  @override
  void dispose() {
    _progressAnimController?.dispose();
    _entryController.dispose();
    _pulseController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final percent = (_displayProgress * 100).round().clamp(0, 100);

    return ColoredBox(
      color: kBlancSite,
      child: SafeArea(
        child: FadeTransition(
          opacity: _fadeAnimation,
          child: SlideTransition(
            position: _slideAnimation,
            child: Center(
              child: Padding(
                padding: const EdgeInsets.symmetric(horizontal: 32),
                child: Column(
                  mainAxisAlignment: MainAxisAlignment.center,
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    ScaleTransition(
                      scale: _entryScaleAnimation,
                      child: SizedBox(
                        width: _ringSize,
                        height: _ringSize,
                        child: Stack(
                          alignment: Alignment.center,
                          children: [
                            SizedBox(
                              width: _ringSize,
                              height: _ringSize,
                              child: CircularProgressIndicator(
                                value: _displayProgress.clamp(0.02, 1.0),
                                strokeWidth: 5.5,
                                strokeCap: StrokeCap.round,
                                backgroundColor:
                                    kBleuPrincipal.withValues(alpha: 0.14),
                                valueColor: const AlwaysStoppedAnimation<Color>(
                                  kOrangePromo,
                                ),
                              ),
                            ),
                            Container(
                              width: _circleSize,
                              height: _circleSize,
                              decoration: BoxDecoration(
                                shape: BoxShape.circle,
                                color: kBleuPrincipal.withValues(alpha: 0.12),
                                border: Border.all(
                                  color: kBleuPrincipal.withValues(alpha: 0.22),
                                  width: 1.5,
                                ),
                              ),
                              alignment: Alignment.center,
                              child: ScaleTransition(
                                scale: _pulseScaleAnimation,
                                child: Padding(
                                  padding: const EdgeInsets.all(22),
                                  child: Image.asset(
                                    'assets/images/app_icon.png',
                                    fit: BoxFit.contain,
                                    errorBuilder:
                                        (context, error, stackTrace) {
                                      return Icon(
                                        Icons.storefront_rounded,
                                        size: 72,
                                        color: kBleuPrincipal.withValues(
                                          alpha: 0.9,
                                        ),
                                      );
                                    },
                                  ),
                                ),
                              ),
                            ),
                          ],
                        ),
                      ),
                    ),
                    const SizedBox(height: 36),
                    Text(
                      'FOUTA POIDS LOURDS',
                      textAlign: TextAlign.center,
                      style: TextStyle(
                        fontSize: 30,
                        fontWeight: FontWeight.w800,
                        height: 1.15,
                        letterSpacing: 0.2,
                        color: kNoirSite,
                      ),
                    ),
                    const SizedBox(height: 10),
                    Text(
                      'Pièces véhicules poids lourds',
                      textAlign: TextAlign.center,
                      style: TextStyle(
                        fontSize: 18,
                        fontWeight: FontWeight.w600,
                        color: kBleuPrincipal,
                        letterSpacing: 0.3,
                      ),
                    ),
                    const SizedBox(height: 28),
                    AnimatedSwitcher(
                      duration: const Duration(milliseconds: 260),
                      transitionBuilder: (child, animation) {
                        return FadeTransition(
                          opacity: animation,
                          child: ScaleTransition(
                            scale: animation,
                            child: child,
                          ),
                        );
                      },
                      child: Text(
                        '$percent%',
                        key: ValueKey<int>(percent),
                        style: TextStyle(
                          fontSize: 40,
                          fontWeight: FontWeight.w800,
                          color: kBleuPrincipalHover,
                          fontFeatures: const [FontFeature.tabularFigures()],
                          height: 1,
                        ),
                      ),
                    ),
                    const SizedBox(height: 10),
                    Text(
                      'Chargement en cours…',
                      style: TextStyle(
                        fontSize: 15,
                        fontWeight: FontWeight.w500,
                        color: kBleuPrincipal.withValues(alpha: 0.62),
                      ),
                    ),
                  ],
                ),
              ),
            ),
          ),
        ),
      ),
    );
  }
}

// Barre de progression discrète en haut pour les navigations suivantes
class _TopProgressBar extends StatelessWidget {
  final double progress;

  const _TopProgressBar({required this.progress});

  @override
  Widget build(BuildContext context) {
    final p = progress.clamp(0.0, 1.0);

    return Positioned(
      top: 0,
      left: 0,
      right: 0,
      child: TweenAnimationBuilder<double>(
        tween: Tween<double>(end: p),
        duration: const Duration(milliseconds: 280),
        curve: Curves.easeOutCubic,
        builder: (context, animatedP, _) {
          return SizedBox(
            height: 3,
            child: Align(
              alignment: Alignment.centerLeft,
              child: FractionallySizedBox(
                widthFactor: animatedP.clamp(0.0, 1.0),
                child: Container(
                  decoration: BoxDecoration(
                    color: kOrangePromo,
                    boxShadow: [
                      BoxShadow(
                        color: kOrangePromo.withValues(alpha: 0.35),
                        blurRadius: 4,
                        offset: const Offset(0, 1),
                      ),
                    ],
                  ),
                ),
              ),
            ),
          );
        },
      ),
    );
  }
}
