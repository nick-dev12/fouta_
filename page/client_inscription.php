<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>connexion</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"
        integrity="sha512-iecdLmaskl7CVkqkXNQ/ZH/XLlvWZOJyj7Yy7tcenmpD1ypASozpmT/E0iPtmFIB46ZmdtAc9eNBvH0H/ZpiBw=="
        crossorigin="anonymous" referrerpolicy="no-referrer" />
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="/css/connextion.css">
</head>

<body>
    <nav>
        <a class="logo" href="/index.html"><img src="../logo.png" alt=""></a>
        <div class="container">
            <div class="box">
                <a href="/page/connexion.html"><button>connexion</button></a>
                <button>boutique</button>
            </div>
            <form action="" method="post">
                <input type="search" name="search" id="search">
                <label for="submit"><i class="fa-solid fa-magnifying-glass fa-2xs"></i></label>
                <input type="submit" value="" id="submit" name="submit">
            </form>

            <div class="box1">
                <span><i class="fa-solid fa-cart-shopping fa-xs"></i></span>
            </div>
        </div>
    </nav>

    <section class="section1">
        <div>
            <span><i class="fa-solid fa-bars"></i></span>
        </div>
        <a href="">femme</a>
        <a href="">homme</a>
        <a href="">enfant</a>
        <a href="">Téléphones portables et télécommunications</a>
        <a href="">Ordinateur et bureautique</a>
        <a href="">Electronique</a>
        <a href="">Bijoux et Accessoires</a>
        <a href="">sacs et chaussurs</a>
    </section>


    <section class="section2">

        <div class="formulaire1">
            <img src="/undraw_login_re_4vu2.svg" alt="">
            <form action="">
                <h3>Compte client</h3>

                <div class="box1">
                    <label for="nom">Nom complet</label>
                    <input type="text" name="nom" id="nom">
                </div>

                <div class="box1">
                    <label for="mail">Adress mail</label>
                    <input type="text" name="mail" id="mail">
                </div>

                <div class="box1">
                    <label for="tell">N-telephone</label>
                    <input type="number" name="tell" id="tell">
                </div>

                <div class="box1">
                    <label for="ville">ville</label>
                    <input type="text" name="ville" id="ville">
                </div>

                <div class="box1">
                    <label for="pass">Mot de passe</label>
                    <input type="password" name="passe" id="pass">
                </div>

                <div class="box1">
                    <label for="cpass">Confirmation du mot de passe</label>
                    <input type="password" name="cpasse" id="cpass">
                </div>

                <input type="submit" name="valider" value="valider" id="valider">
            </form>
        </div>
    </section>

    <footer>
        <span>developper par @nick jomas</span>
    </footer>


</body>

</html>