// Tests sans montage de la WebView (incompatible avec l'environnement de test).
import 'package:flutter_test/flutter_test.dart';

import 'package:colobanes_marketplace/main.dart' as app;

void main() {
  test('resolveRelativeMarketUrl gère URL absolue', () {
    expect(
      app.resolveRelativeMarketUrl(
          'https://www.e.foutapoidslourds.com/panier/'),
      'https://www.e.foutapoidslourds.com/panier/',
    );
  });

  test('resolveRelativeMarketUrl gère chemin relatif', () {
    expect(
      app.resolveRelativeMarketUrl('/produits/'),
      'https://www.e.foutapoidslourds.com/produits/',
    );
  });

  test('constante base pointe vers la boutique en ligne', () {
    expect(app.kMarketplaceBaseUrl, 'https://www.e.foutapoidslourds.com/');
  });
}
