<?php
session_start();
if (file_exists(__DIR__ . '/controllers/controller_commerce_users.php')) {
    require_once __DIR__ . '/controllers/controller_commerce_users.php';
}




?>




<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <script src="https://unpkg.com/scrollreveal"></script>
  <!-- Link Swiper's CSS -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swiper@10/swiper-bundle.min.css" />
  <?php if (file_exists(__DIR__ . '/includes/asset_version.php')) { require_once __DIR__ . '/includes/asset_version.php'; } ?>
  <link rel="stylesheet" href="/css/variables.css<?php echo function_exists('asset_version_query') ? asset_version_query() : ''; ?>" />
  <link rel="stylesheet" href="./css/a_style.css" />
  <title>commerçant</title>
</head>

<body>

  <?php include ('nav_bar.php') ?>

  <section class="section">
    <div class="auth_container">
      <div class="auth_img">
        <img src="./image/auth-image.png" alt="" class="auth_image" />
      </div>
      <div class="auth_content">
        <form method="post" action="" class="auth_form">
          <h2 class="form_title">Connectez-vous à votre compte</h2>
          <p class="auth_p">Entrez vos coordonnées ci-dessous</p>
          <?php if (isset ($erreurs)): ?>
          <div class="erreur">
            <?php echo $erreurs; ?>
          </div>
        <?php endif; ?>
          <div class="form_group">
            <input type="email" name="mail" placeholder="Email" class="form_input" />
          </div>
          <div class="form_group form_pass">
            <input name="pass" type="password" placeholder="Password" class="form_input" />
          </div>
          <div class="form_group">
              <input  class="form_btn form_link" type="submit" value="Connexion" name="validers">
          </div>
          <div class="form_group">
            <span>vous n'avez pas de compte ?
              <a href="./sign-up.html" class="form_auth_link">Inscription</a></span>
          </div>
        </form>
      </div>
    </div>
  </section>
  
</body>

</html>